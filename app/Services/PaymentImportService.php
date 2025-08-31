<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\Payment;
use App\Models\Refund;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PaymentImportService
{
    private const CHUNK_SIZE = 1000;
    private const PAYMENT_FILE_PATH = 'external/payments.csv';

    // CSV columns to DB mapping
    private const COLUMN_MAPPING = [
        'paymentDate' => Payment::COLUMN_PAYMENT_DATE,
        'payerName' => Payment::COLUMN_PAYER_NAME,
        'payerSurname' => Payment::COLUMN_PAYER_SURNAME,
        'amount' => Payment::COLUMN_AMOUNT,
        'nationalSecurityNumber' => Payment::COLUMN_SSN,
        'description' => Payment::COLUMN_LOAN_REFERENCE,
        'paymentReference' => Payment::COLUMN_PAYMENT_REFERENCE,
    ];

    /**
     * Import payments from a CSV file in chunks. Yields chunked data, is a generator.
     *
     * @return array
     */
    public function import()
    {
        $csv_file = storage_path(self::PAYMENT_FILE_PATH);

        foreach ($this->readCsvChunked($csv_file, ',', self::CHUNK_SIZE) as $payments) {
            if (empty($payments)) {
                continue;
            }

            $loan_references = $this->extractLoanReferences($payments);
            $loans = $this->fetchActiveLoans($loan_references);
            $active_loan_references = $loans->keys()->all();

            $csv_payment_references = $this->extractPaymentReferences($payments);
            $existing_payment_references = $this->fetchExistingPaymentReferences($csv_payment_references);

            $validated_payments = $this->validatePayments($payments, $active_loan_references, $existing_payment_references);

            ['payments' => $processed_payments, 'loan_updates' => $processed_loan_updates, 'refunds' => $refunds] = $this->processPayments($validated_payments, $loans);

            ['valid_payments' => $valid_payments, 'rejected_payments' => $rejected_payments] = $this->preparePaymentsForTransaction($processed_payments);

            try {
                ['stored_payments' => $stored_payments, 'updated_loans' => $updated_loans, 'created_refunds' => $created_refunds] = $this->createMassTransaction(
                    valid_payments: $valid_payments,
                    loan_updates: $processed_loan_updates,
                    refunds: $refunds
                );
                yield [
                    'data' => [
                        'payments' => $stored_payments,
                        'loan_updates' => $updated_loans,
                        'refunds' => $created_refunds,
                        'rejected_payments' => $rejected_payments
                    ],
                    'error' => null,
                    'message' => 'Payment import success'
                ];
            } catch (\Exception $e) {
                Log::error('Payment import failed: ' . $e->getMessage());
                yield [
                    'data' => null,
                    'error' => 'Payment import fail',
                    'message' => $e->getMessage()
                ];
            }
        }
    }

    /**
     * Get payments by date.
     */
    public function getPaymentsByDate(string $date): array
    {
        return Payment::whereDate('payment_date', $date)->get()->toArray();
    }

    /**
     * Read a CSV file and yield its contents in chunks.
     */
    private function readCsvChunked($csv_file, $delimiter = ',', $chunk_size = self::CHUNK_SIZE)
    {
        if (!file_exists($csv_file) || !is_readable($csv_file)) {
            Log::error("CSV file not found or not readable: $csv_file");
            return;
        }

        $file_handle = fopen($csv_file, 'r');
        if (!$file_handle) {
            Log::error("Failed to open CSV file: $csv_file");
            return;
        }

        try {
            $row_number = 0;
            $column_names = [];
            while (!feof($file_handle)) {
                $chunk = [];
                while (count($chunk) < $chunk_size && ($csv_row = fgetcsv($file_handle, null, $delimiter)) !== false) {
                    if ($row_number === 0) {
                        $column_names = array_map(
                            fn($name) => self::COLUMN_MAPPING[$name] ?? $name,
                            $csv_row
                        );
                        $row_number++;
                        continue;
                    }
                    $row = array_combine($column_names, $csv_row);
                    if (!$row) {
                        Log::warning("Malformed CSV row at line $row_number");
                        $row_number++;
                        continue;
                    }
                    // Accept both date and YYYYMMDDHHMMSS formats
                    if (preg_match('/^\d{14}$/', $row[Payment::COLUMN_PAYMENT_DATE])) {
                        $row[Payment::COLUMN_PAYMENT_DATE] = Carbon::createFromFormat('YmdHis', $row[Payment::COLUMN_PAYMENT_DATE])->toDateTimeString();
                    } else {
                        try {
                            $row[Payment::COLUMN_PAYMENT_DATE] = Carbon::parse($row[Payment::COLUMN_PAYMENT_DATE])->toDateTimeString();
                        } catch (\Exception $e) {
                            $row[Payment::COLUMN_PAYMENT_DATE] = null;
                        }
                    }
                    $chunk[] = $row;
                    $row_number++;
                }
                if (!empty($chunk)) {
                    yield $chunk;
                }
            }
        } finally {
            fclose($file_handle);
        }
    }

    /**
     * Extract loan references from payments.
     */
    private function extractLoanReferences(array $payments): array
    {
        return collect($payments)->pluck(Payment::COLUMN_LOAN_REFERENCE)->unique()->values()->all();
    }

    /**
     * Extract payment references from payments.
     */
    private function extractPaymentReferences(array $payments): array
    {
        return collect($payments)->pluck(Payment::COLUMN_PAYMENT_REFERENCE)->unique()->values()->all();
    }

    /**
     * Fetch active loans by references.
     */
    private function fetchActiveLoans(array $loan_references)
    {
        return Loan::whereIn(Loan::COLUMN_REFERENCE, $loan_references)
            ->where(Loan::COLUMN_STATE, Loan::STATE_ACTIVE)
            ->get()
            ->keyBy(Loan::COLUMN_REFERENCE);
    }

    /**
     * Fetch existing payment references from the database.
     */
    private function fetchExistingPaymentReferences(array $payment_references): array
    {
        return Payment::whereIn(Payment::COLUMN_PAYMENT_REFERENCE, $payment_references)
            ->pluck(Payment::COLUMN_PAYMENT_REFERENCE)
            ->all();
    }

    /**
     * Validate payments.
     * Returns [payments]
     */
    private function validatePayments(array $payments, array $active_loan_references, array $existing_payment_references): array
    {
        $validator = Validator::make($payments, [
            '*.' . Payment::COLUMN_PAYMENT_DATE => 'required|date',
            '*.' . Payment::COLUMN_AMOUNT => 'required|numeric|min:0.01',
            '*.' . Payment::COLUMN_LOAN_REFERENCE => [
                'required',
                'string',
                Rule::in($active_loan_references),
            ],
            "*." . Payment::COLUMN_PAYMENT_REFERENCE => [
                'required',
                'string',
                Rule::notIn($existing_payment_references),
            ],
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->messages();
            foreach ($errors as $key => $messages) {
                [$index, $field] = explode('.', $key);
                $payments[$index][Payment::COLUMN_STATE] = Payment::STATE_REJECTED;
                // Assign error code, prioritize duplicates
                $is_duplicate = isset($payments[$index][Payment::COLUMN_CODE]) && $payments[$index][Payment::COLUMN_CODE] === Payment::CODE_ERROR_DUPLICATE;
                if (!$is_duplicate) {
                    $payments[$index][Payment::COLUMN_CODE] = match ($field) {
                        Payment::COLUMN_PAYMENT_REFERENCE => Payment::CODE_ERROR_DUPLICATE,
                        Payment::COLUMN_AMOUNT => Payment::CODE_ERROR_AMOUNT,
                        Payment::COLUMN_PAYMENT_DATE => Payment::CODE_ERROR_PAYMENT_DATE,
                        Payment::COLUMN_LOAN_REFERENCE => Payment::CODE_ERROR_LOAN_REFERENCE,
                        default => Payment::CODE_ERROR_UNKNOWN,
                    };
                }
            }
        }

        return $payments;
    }

    /**
     * Process validated payments. Business logic
     * Returns [payments, loan_updates, refunds]
     */
    private function processPayments(array $payments, $loans): array
    {
        $batch_payment_references = [];
        $loan_updates = [];
        $refunds = [];

        foreach ($payments as &$payment) {
            // Ensure amount is numeric (defensive)
            $raw_amount = $payment[Payment::COLUMN_AMOUNT] ?? null;
            $payment_amount = is_numeric($raw_amount) ? (float) $raw_amount : 0.0;
            $payment_amount_cents = (int) round($payment_amount * 100);
            $payment_reference = $payment[Payment::COLUMN_PAYMENT_REFERENCE] ?? null;

            // Batch duplicate check. Add data about already used payment references
            if (
                ($payment_reference && isset($batch_payment_references[$payment_reference])) ||
                (isset($payment[Payment::COLUMN_CODE]) && $payment[Payment::COLUMN_CODE] === Payment::CODE_ERROR_DUPLICATE)
            ) {
                $payment[Payment::COLUMN_STATE] = Payment::STATE_REJECTED;
                $payment[Payment::COLUMN_CODE] = Payment::CODE_ERROR_DUPLICATE;
                Log::warning("Duplicate payment reference found in batch: " . ($payment_reference ?? '[no-ref]') . ", skipping.");
                continue;
            } else {
                if ($payment_reference) {
                    $batch_payment_references[$payment_reference] = true;
                }
            }

            // Skip already rejected payments
            if (isset($payment[Payment::COLUMN_STATE]) && $payment[Payment::COLUMN_STATE] === Payment::STATE_REJECTED) {
                continue;
            }

            // Defensive: Check loan exists
            $loan_reference = $payment[Payment::COLUMN_LOAN_REFERENCE] ?? null;
            if (!$loan_reference || !isset($loans[$loan_reference])) {
                $payment[Payment::COLUMN_STATE] = Payment::STATE_REJECTED;
                $payment[Payment::COLUMN_CODE] = Payment::CODE_ERROR_LOAN_REFERENCE;
                continue;
            }

            // Defensive: Check if loan got paid off by previous payments
            $loan = $loans[$loan_reference];
            if ($loan->{Loan::COLUMN_STATE} === Loan::STATE_PAID) {
                $payment[Payment::COLUMN_STATE] = Payment::STATE_REJECTED;
                $payment[Payment::COLUMN_CODE] = Payment::CODE_ERROR_ALREADY_PAID;
                continue;
            }

            // compute amounts in cents to avoid float precision during calculations
            $loan_amount_paid = (float) $loan->{Loan::COLUMN_AMOUNT_PAID};
            $loan_amount_to_pay = (float) $loan->{Loan::COLUMN_AMOUNT_TO_PAY};
            $loan_amount_paid_cents = (int) round($loan_amount_paid * 100);
            $loan_amount_to_pay_cents = (int) round($loan_amount_to_pay * 100);

            $new_loan_amount_paid_cents = $loan_amount_paid_cents + $payment_amount_cents;

            // Calculate loans and payments
            if ($new_loan_amount_paid_cents > $loan_amount_to_pay_cents) {
                $payment[Payment::COLUMN_STATE] = Payment::STATE_PARTIALLY_ASSIGNED;
                $loan->{Loan::COLUMN_STATE} = Loan::STATE_PAID;

                $refund_cents = $new_loan_amount_paid_cents - $loan_amount_to_pay_cents;
                $refunds[] = [
                    Refund::COLUMN_PAYMENT_REFERENCE => $payment_reference,
                    Refund::COLUMN_AMOUNT => number_format($refund_cents / 100, 2, '.', ''),
                    Refund::COLUMN_STATUS => Refund::STATUS_PENDING,
                    Refund::COLUMN_CREATED_AT => now(),
                    Refund::COLUMN_UPDATED_AT => now(),
                ];

                $loan->{Loan::COLUMN_AMOUNT_PAID} = number_format($new_loan_amount_paid_cents / 100, 2, '.', '');
            } else {
                $payment[Payment::COLUMN_STATE] = Payment::STATE_ASSIGNED;
                $loan->{Loan::COLUMN_AMOUNT_PAID} = number_format($new_loan_amount_paid_cents / 100, 2, '.', '');
                if ($new_loan_amount_paid_cents === $loan_amount_to_pay_cents) {
                    $loan->{Loan::COLUMN_STATE} = Loan::STATE_PAID;
                }
            }

            // Using loan->id as a key for cases where multiple payments target same loan
            $loan_updates[$loan->{Loan::COLUMN_ID}] = [
                Loan::COLUMN_ID => $loan->{Loan::COLUMN_ID},
                Loan::COLUMN_CUSTOMER_ID => $loan->{Loan::COLUMN_CUSTOMER_ID},
                Loan::COLUMN_REFERENCE => $loan->{Loan::COLUMN_REFERENCE},
                Loan::COLUMN_AMOUNT_ISSUED => $loan->{Loan::COLUMN_AMOUNT_ISSUED},
                Loan::COLUMN_AMOUNT_TO_PAY => $loan->{Loan::COLUMN_AMOUNT_TO_PAY},
                Loan::COLUMN_AMOUNT_PAID => $loan->{Loan::COLUMN_AMOUNT_PAID},
                Loan::COLUMN_STATE => $loan->{Loan::COLUMN_STATE},
                Loan::COLUMN_UPDATED_AT => now(),
            ];

            $payment[Payment::COLUMN_CODE] = Payment::CODE_SUCCESS;
        }
        // Unset $payment passing by reference for safety purposes
        unset($payment);

        return ([
            'payments' => $payments,
            'loan_updates' => $loan_updates,
            'refunds' => $refunds
        ]);
    }

    /**
     * Prepare data for transaction.
     * Returns [valid_payments, rejected_payments]
     */
    private function preparePaymentsForTransaction(array $payments): array
    {
        $rejected_payments = [];
        $valid_payments = [];

        // Filter out rejected_payments from being written into a database
        foreach ($payments as $payment) {
            if (
                isset($payment[Payment::COLUMN_CODE]) &&
                $payment[Payment::COLUMN_CODE] !== Payment::CODE_SUCCESS
            ) {
                $rejected_payments[] = $payment;
            } else {
                $valid_payments[] = $payment;
            }
        }

        $valid_payments = array_map(function ($payment) {
            $payment[Payment::COLUMN_SOURCE] = Payment::SOURCE_CSV;
            $payment[Payment::COLUMN_SSN] = empty($payment[Payment::COLUMN_SSN]) ? null : trim((string) $payment[Payment::COLUMN_SSN]);
            return $payment;
        }, $valid_payments);

        return [
            'valid_payments' => $valid_payments,
            'rejected_payments' => $rejected_payments,
        ];
    }

    /*
     * Create a mass transaction for the valid payments, loan updates, and refunds.
     * Personal comment: This method does NOT return fresh instances of the inserted/upserted payments and refunds, the only fresh part is the updated data about loans.
     * This was done to limit calls to the database and improve performance.
     */
    private function createMassTransaction(array $valid_payments, array $loan_updates, array $refunds)
    {
        $updated_loans = [];

        DB::transaction(function () use ($valid_payments, $loan_updates, $refunds, &$updated_loans) {
            if (!empty($valid_payments)) {
                Payment::insert($valid_payments);
            }
            if (!empty($loan_updates)) {
                Loan::upsert(
                    array_values($loan_updates),
                    uniqueBy: [Loan::COLUMN_ID],
                    update: [Loan::COLUMN_AMOUNT_PAID, Loan::COLUMN_STATE, Loan::COLUMN_UPDATED_AT]
                );
                $loan_ids = array_keys($loan_updates);

                $updated_loans = Loan::whereIn(Loan::COLUMN_ID, $loan_ids)->get()->toArray();
            }
            if (!empty($refunds)) {
                Refund::insert($refunds);
            }
        });
        return [
            'stored_payments' => $valid_payments,
            'updated_loans' => $updated_loans,
            'created_refunds' => $refunds
        ];
    }
}
