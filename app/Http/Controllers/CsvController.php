<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CsvController extends Controller
{
    private $error_codes = [
        'payment_reference' => 1, // Duplicate entry
        'amount' => 2, // Negative amount, or amount is less than 0.01
        'payment_date' => 3, // Invalid payment date
        'description' => 4, // No active loan found by loan reference
        'already_paid' => 5 // Error code when attempting to pay already paid loans
    ];

    public function import()
    {
        // Get CSV File contents, hard-coded file location as a placeholder for potential csv from external sources
        $payments = $this->readCSV(storage_path('external/payments.csv'));
        $loan_references = collect($payments)->pluck('description')->all();
        $loans = Loan::whereIn('reference', $loan_references)->where('state', 'ACTIVE')->get()->keyBy('reference');
        $active_loan_references = $loans->keys()->all();

        $csv_payment_references = collect($payments)->pluck('payment_reference')->all();
        $existing_payment_references = Payment::whereIn('payment_reference', $csv_payment_references)
            ->pluck('payment_reference')
            ->all();

        $validator = Validator::make($payments, [
            '*.payment_date' => 'required|date',
            '*.amount' => 'required|numeric|min:0.01',
            '*.description' => [
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
            // Errors are keyed by row index and field, e.g. '0.payment_date'
            $errors = $validator->errors()->messages();
            foreach($errors as $key => $messages) {
                $index = explode('.', $key)[0];
                $field = explode('.', $key)[1];
                $payments[$index]['state'] = 'REJECTED';
                if(!isset($payments[$index]['code']) || $payments[$index]['code'] !== $this->error_codes['payment_reference']) {
                    // Don't overwrite the duplicate error code, as it's a priority over other errors
                    $payments[$index]['code'] = $this->error_codes[$field] ?? 99; // 99 for unknown error
                }
            }
        }

        $batch_payment_references = [];

        foreach ($payments as &$payment) {
            // Check for duplicates in the batch or check if it was already marked as duplicate
            if (in_array(
                $payment['payment_reference'], $batch_payment_references)
                || (isset($payment['code']) && $payment['code'] === $this->error_codes['payment_reference'])
            ) {
                $payment['state'] = 'REJECTED';
                $payment['code'] = $this->error_codes['payment_reference'];
                //Log::warning("Duplicate payment reference found in batch: {$payment['payment_reference']}, skipping.");
                // Skip the iteration of the payment is a batch duplicate, don't save duplicate payment
                continue;
            } else {
                $batch_payment_references[] = $payment['payment_reference'];
            }

            if (!isset($payment['state']) || $payment['state'] !== 'REJECTED') {
                $loan = $loans[$payment['description']];
                if($loan->state === 'PAID') {
                    // Loan already paid by previous batch payments, reject the payment
                    $payment['state'] = 'REJECTED';
                    $payment['code'] = $this->error_codes['already_paid'];
                } else {
                    $loan->amount_paid += $payment['amount'];
                    if($loan->amount_paid > $loan->amount_to_pay) {
                        // Loan overpaid, create a refund
                        $payment['state'] = 'PARTIALLY_ASSIGNED';
                        $loan->status = 'PAID';
                    } else {
                        $payment['state'] = 'ASSIGNED';
                        if($loan->amount_paid == $loan->amount_to_pay) {
                            $loan->status = 'PAID';
                        }
                    }
                    $payment['code'] = 0;
                    //$loan->save();
                }
            }
        }

        return $payments;
    }

    /**
     * Read a CSV file and return its contents as an array.
     *
     * @param string $csvFile
     * @param string $delimiter
     * @return array
     */
    private function readCSV($csvFile, $delimiter = ','): array
    {
        $row_number = 0;
        $file_handle = fopen($csvFile, 'r');
        while ($csvRow = fgetcsv($file_handle, null, $delimiter)) {
            if($row_number === 0) {
                // Get column names from the first row
                $column_names = $csvRow;
                // Convert all column names to snake_case
                $column_names = array_map(fn($name) => strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $name)), $column_names);
                $row_number++;
                continue;
            }
            $row = array_combine($column_names, $csvRow);
            // Convert payment_date to Carbon instance, YYYYMMDDHHMMSS and DateTimeString are accepted
            if (preg_match('/^\d{14}$/', $row['payment_date'])) {
                $row['payment_date'] = Carbon::createFromFormat('YmdHis', $row['payment_date'])->toDateTimeString();
            } else {
                $row['payment_date'] = Carbon::parse($row['payment_date'])->toDateTimeString();
            }
            $payments[] = $row;
        }
        fclose($file_handle);
        return $payments;
    }
}
