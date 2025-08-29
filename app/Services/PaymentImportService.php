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
        'paymentDate' => 'payment_date',
        'payerName' => 'payer_name',
        'payerSurname' => 'payer_surname',
        'amount' => 'amount',
        'nationalSecurityNumber' => 'ssn',
        'description' => 'loan_reference',
        'paymentReference' => 'payment_reference',
    ];

    // Use class constants for error codes
    private const PAYMENT_SUCCESS = 0;
    private const ERROR_DUPLICATE = 1;
    private const ERROR_AMOUNT = 2;
    private const ERROR_PAYMENT_DATE = 3;
    private const ERROR_LOAN_REFERENCE = 4;
    private const ERROR_ALREADY_PAID = 5;
    private const ERROR_UNKNOWN = 99;

    /**
     * Import payments from a CSV file in chunks.
     *
     * @return array
     */
    public function import()
    {
        $csv_file = storage_path(self::PAYMENT_FILE_PATH);
        $results = [];

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
            $this->createTransaction(
                payments: $processed_payments,
                loan_updates: $processed_loan_updates,
                refunds: $refunds
            );

            array_push($results, ...$processed_payments);
        }

        return $results;
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
                    if (preg_match('/^\d{14}$/', $row['payment_date'])) {
                        $row['payment_date'] = Carbon::createFromFormat('YmdHis', $row['payment_date'])->toDateTimeString();
                    } else {
                        try {
                            $row['payment_date'] = Carbon::parse($row['payment_date'])->toDateTimeString();
                        } catch (\Exception $e) {
                            $row['payment_date'] = null;
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
        return collect($payments)->pluck('loan_reference')->unique()->values()->all();
    }

    /**
     * Extract payment references from payments.
     */
    private function extractPaymentReferences(array $payments): array
    {
        return collect($payments)->pluck('payment_reference')->unique()->values()->all();
    }

    /**
     * Fetch active loans by references.
     */
    private function fetchActiveLoans(array $loan_references)
    {
        return Loan::whereIn('reference', $loan_references)
            ->where('state', 'ACTIVE')
            ->get()
            ->keyBy('reference');
    }

    /**
     * Fetch existing payment references from the database.
     */
    private function fetchExistingPaymentReferences(array $payment_references): array
    {
        return Payment::whereIn('payment_reference', $payment_references)
            ->pluck('payment_reference')
            ->all();
    }

    /**
     * Validate payments.
     */
    private function validatePayments(array $payments, array $active_loan_references, array $existing_payment_references): array
    {
        $validator = Validator::make($payments, [
            '*.payment_date' => 'required|date',
            '*.amount' => 'required|numeric|min:0.01',
            '*.loan_reference' => [
                'required',
                'string',
                Rule::in($active_loan_references),
            ],
            '*.payment_reference' => [
                'required',
                'string',
                Rule::notIn($existing_payment_references),
            ],
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->messages();
            foreach ($errors as $key => $messages) {
                [$index, $field] = explode('.', $key);
                $payments[$index]['state'] = Payment::STATE_REJECTED;
                $is_duplicate = isset($payments[$index]['code']) && $payments[$index]['code'] === self::ERROR_DUPLICATE;
                if(!$is_duplicate) {
                    $payments[$index]['code'] = match ($field) {
                        'payment_reference' => self::ERROR_DUPLICATE,
                        'amount' => self::ERROR_AMOUNT,
                        'payment_date' => self::ERROR_PAYMENT_DATE,
                        'loan_reference' => self::ERROR_LOAN_REFERENCE,
                        default => self::ERROR_UNKNOWN,
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
                isset($batch_payment_references[$payment['payment_reference']]) ||
                (isset($payment['code']) && $payment['code'] === self::ERROR_DUPLICATE)
            ) {
                $payment['state'] = Payment::STATE_REJECTED;
                $payment['code'] = self::ERROR_DUPLICATE;
                Log::warning("Duplicate payment reference found in batch: {$payment['payment_reference']}, skipping.");
                continue;
            } else {
                $batch_payment_references[$payment['payment_reference']] = true;
            }

            // Skip already rejected payments
            if (isset($payment['state']) && $payment['state'] === Payment::STATE_REJECTED) {
                continue;
            }

            // Defensive: Check loan exists
            if (!isset($loans[$payment['loan_reference']])) {
                $payment['state'] = Payment::STATE_REJECTED;
                $payment['code'] = self::ERROR_LOAN_REFERENCE;
                continue;
            }

            $loan = $loans[$payment['loan_reference']];

            if ($loan->state === Loan::STATE_PAID) {
                $payment['state'] = Payment::STATE_REJECTED;
                $payment['code'] = self::ERROR_ALREADY_PAID;
            } else {
                $loan->amount_paid += $payment['amount'];
                if ($loan->amount_paid > $loan->amount_to_pay) {
                    $payment['state'] = Payment::STATE_PARTIALLY_ASSIGNED;
                    $loan->state = Loan::STATE_PAID;
                    $refunds[] = [
                        'payment_reference' => $payment['payment_reference'],
                        'amount' => $loan->amount_paid - $loan->amount_to_pay,
                        'status' => Refund::STATUS_PENDING
                    ];
                } else {
                    $payment['state'] = Payment::STATE_ASSIGNED;
                    if ($loan->amount_paid == $loan->amount_to_pay) {
                        $loan->state = Loan::STATE_PAID;
                    }
                }
                $loan_updates[$loan->id] = [
                    'amount_paid' => $loan->amount_paid,
                    'state' => $loan->state,
                    'updated_at' => now(),
                ];
                $payment['code'] = $this::PAYMENT_SUCCESS;
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
        // Add 'source' => 'csv' to each payment before insert
        $payments = array_map(function ($payment) {
            $payment['source'] = Payment::SOURCE_CSV;
            return $payment;
        }, $payments);

        // Filter out duplicates from being written into a database
        $payments = array_filter($payments, function ($payment) {
            return isset($payment['code']) && $payment['code'] !== self::ERROR_DUPLICATE;
        });

        DB::transaction(function () use ($payments, $loan_updates, $refunds) {
            if (!empty($payments)) {
                Payment::insert($payments);
            }
            if (!empty($loan_updates)) {
                Loan::upsert($loan_updates, ['id'], ['amount_paid', 'status', 'updated_at']);
            }
            if (!empty($refunds)) {
                Refund::insert($refunds);
            }
        });
    }
}
