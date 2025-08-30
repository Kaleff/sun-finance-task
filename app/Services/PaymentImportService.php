<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\Payment;
use App\Models\Refund;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
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
     * Import payments from a CSV file in chunks.
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

            yield $this->createTransaction(
                payments: $processed_payments,
                loan_updates: $processed_loan_updates,
                refunds: $refunds
            );
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
                $is_duplicate = isset($payments[$index][Payment::COLUMN_CODE]) && $payments[$index][Payment::COLUMN_CODE] === Payment::CODE_ERROR_DUPLICATE;
                if(!$is_duplicate) {
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
     * Process validated payments.
     */
    private function processPayments(array $payments, $loans): array
    {
        $batch_payment_references = [];
        $loan_updates = [];
        $refunds = [];
        foreach ($payments as &$payment) {
            // Batch duplicate check
            if (
                isset($batch_payment_references[$payment[Payment::COLUMN_PAYMENT_REFERENCE]]) ||
                (isset($payment[Payment::COLUMN_CODE]) && $payment[Payment::COLUMN_CODE] === Payment::CODE_ERROR_DUPLICATE)
            ) {
                $payment[Payment::COLUMN_STATE] = Payment::STATE_REJECTED;
                $payment[Payment::COLUMN_CODE] = Payment::CODE_ERROR_DUPLICATE;
                Log::warning("Duplicate payment reference found in batch: ." . $payment[Payment::COLUMN_PAYMENT_REFERENCE] . ", skipping.");
                continue;
            } else {
                $batch_payment_references[$payment[Payment::COLUMN_PAYMENT_REFERENCE]] = true;
            }

            // Skip already rejected payments
            if (isset($payment[Payment::COLUMN_STATE]) && $payment[Payment::COLUMN_STATE] === Payment::STATE_REJECTED) {
                continue;
            }

            // Defensive: Check loan exists
            if (!isset($loans[$payment[Payment::COLUMN_LOAN_REFERENCE]])) {
                $payment[Payment::COLUMN_STATE] = Payment::STATE_REJECTED;
                $payment[Payment::COLUMN_CODE] = Payment::CODE_ERROR_LOAN_REFERENCE;
                continue;
            }

            $loan = $loans[$payment[Payment::COLUMN_LOAN_REFERENCE]];

            if ($loan->state === Loan::STATE_PAID) {
                $payment[Payment::COLUMN_STATE] = Payment::STATE_REJECTED;
                $payment[Payment::COLUMN_CODE] = Payment::CODE_ERROR_ALREADY_PAID;
            } else {
                $loan->amount_paid += $payment[Payment::COLUMN_AMOUNT];
                if ($loan->amount_paid > $loan->amount_to_pay) {
                    $payment[Payment::COLUMN_STATE] = Payment::STATE_PARTIALLY_ASSIGNED;
                    $loan->state = Loan::STATE_PAID;
                    $refunds[] = [
                        Refund::COLUMN_PAYMENT_REFERENCE => $payment[Payment::COLUMN_PAYMENT_REFERENCE],
                        Refund::COLUMN_AMOUNT => $loan->amount_paid - $loan->amount_to_pay,
                        Refund::COLUMN_STATUS => Refund::STATUS_PENDING
                    ];
                } else {
                    $payment[Payment::COLUMN_STATE] = Payment::STATE_ASSIGNED;
                    if ($loan->amount_paid == $loan->amount_to_pay) {
                        $loan->state = Loan::STATE_PAID;
                    }
                }
                // Using loan->id as a key for a case where multiple payments are made towards the same loan
                $loan_updates[$loan->id] = [
                    Loan::COLUMN_ID => $loan->id,
                    Loan::COLUMN_CUSTOMER_ID => $loan->customer_id,
                    Loan::COLUMN_REFERENCE => $loan->reference,
                    Loan::COLUMN_AMOUNT_ISSUED => $loan->amount_issued,
                    Loan::COLUMN_AMOUNT_TO_PAY => $loan->amount_to_pay,
                    Loan::COLUMN_AMOUNT_PAID => $loan->amount_paid,
                    Loan::COLUMN_STATE => $loan->state,
                    Loan::COLUMN_UPDATED_AT => now(),
                ];
                $payment[Payment::COLUMN_CODE] = Payment::CODE_SUCCESS;
            }
        }
        // Break the reference created by foreach loop for safety purposes
        unset($payment);

        return([
            'payments' => $payments,
            'loan_updates' => $loan_updates,
            'refunds' => $refunds
        ]);
    }

    private function createTransaction(array $payments, array $loan_updates, array $refunds)
    {
        $rejected_payments = [];
        $valid_payments = [];

        // Filter out rejected_payments from being written into a database
        foreach ($payments as $payment) {
            if (isset($payment[Payment::COLUMN_CODE]) &&
                $payment[Payment::COLUMN_CODE] !== Payment::CODE_SUCCESS
            ) {
                $rejected_payments[] = $payment;
            } else {
                $valid_payments[] = $payment;
            }
        }

        // Add 'source' => 'csv' to each payment before insert
        $valid_payments = array_map(function ($payment) {
            $payment[Payment::COLUMN_SOURCE] = Payment::SOURCE_CSV;
            return $payment;
        }, $valid_payments);

        try {
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
                'data' => [
                    'payments' => $valid_payments,
                    'loan_updates' => $updated_loans,
                    'refunds' => $refunds,
                    'rejected_payments' => $rejected_payments
                ],
                'error' => null,
                'message' => 'Payment import success'
            ];
        } catch (\Exception $e) {
            // Handle the exception
            Log::error('Payment import failed: ' . $e->getMessage());
            return [
                'data' => null,
                'error' => 'Payment import fail',
                'message' => $e->getMessage()
            ];
        }
    }
}
