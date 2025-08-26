<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CsvController extends Controller
{
    private $error_codes = [
        'payment_reference' => 1, // Duplicate entry
        'amount' => 2, // Negative amount, or amount is less than 0.01
        'payment_date' => 3, // Invalid payment date
        'description' => 4, // Invalid loan reference, if there is no matching loan found
    ];

    public function import()
    {
        // Get CSV File contents, hard-coded file location as a placeholder for potential csv from external sources
        $csvFile = storage_path('external/payments.csv');
        $contents = $this->readCSV($csvFile);
        $table_contents = [];
        $batch_payment_references = [];
        foreach ($contents as $payment) {
            // Check for duplicates in the batch before external validation
            if (in_array($payment['payment_reference'], $batch_payment_references)) {
                $payment['state'] = 'REJECTED';
                $payment['code'] = $this->error_codes['payment_reference'];
                $duplicate_in_batch = true;
            } else {
                $batch_payment_references[] = $payment['payment_reference'];
                $duplicate_in_batch = false;
            }

            // If the payment_date is in YYYYMMDDHHMMSS format, convert it to Carbon
            if (preg_match('/^\d{14}$/', $payment['payment_date'])) {
                $payment['payment_date'] = Carbon::createFromFormat('YmdHis', $payment['payment_date'])->toDateTimeString();
            }

            $validator = Validator::make($payment, [
                'payment_date' => 'required|date',
                'amount' => 'required|numeric|min:0.01',
                'description' => 'required|string|exists:loans,reference', // Loan reference
                'payment_reference' => 'required|string|unique:payments,payment_reference',
            ]);

            if($validator->fails()) {
                $payment['state'] = 'REJECTED';
                $error_column = isset($validator->errors()->messages()['payment_reference'])
                    ? 'payment_reference'
                    : array_key_first($validator->errors()->messages());
                $payment['code'] = $this->error_codes[$error_column] ?? 99; // 99 for unknown error
            } elseif (!$duplicate_in_batch) {
                $loan = Loan::where('reference', $payment['description'])->first();
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
            $table_contents[] = $payment;
        }

        return $table_contents;
    }

    private function readCSV($csvFile, $delimiter = ',')
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
            $contents[] = array_combine($column_names, $csvRow);
        }
        fclose($file_handle);
        return $contents;
    }
}
