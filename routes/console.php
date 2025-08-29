<?php

use App\Http\Controllers\CsvController;
use App\Models\Loan;
use App\Models\Payment;
use App\Services\PaymentImportService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

use function PHPUnit\Framework\isEmpty;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('csv:import', function () {
    $payment_service = new PaymentImportService();
    $chunk_number = 1;
    foreach ($payment_service->import() as $chunk_result) {
        $start = microtime(true); // Start timer
        if(isset($chunk_result['data']) && !isset($chunk_result['error'])) {
            ['payments' => $processed_payments, 'loan_updates' => $processed_loan_updates, 'refunds' => $refunds, 'rejected_payments' => $rejected_payments] = $chunk_result['data'];
            $this->info("Chunk {$chunk_number} processed:");

            if(!empty($processed_payments)) {
                $payment_headers = array_keys($processed_payments[array_key_first($processed_payments)]);
                $payment_rows = array_map('array_values', $processed_payments);
                $this->info("Processed Payments:");
                $this->table($payment_headers, $payment_rows);
            }

            if(!empty($rejected_payments)) {
                $rejected_headers = array_keys($rejected_payments[array_key_first($rejected_payments)]);
                $rejected_rows = array_map('array_values', $rejected_payments);
                $this->info("Rejected Payments:");
                $this->table($rejected_headers, $rejected_rows);
            }

            if(!empty($processed_loan_updates)) {
                $loan_headers = array_keys($processed_loan_updates[array_key_first($processed_loan_updates)]);
                $loan_rows = array_map('array_values', $processed_loan_updates);
                $this->info("Processed Loan Updates:");
                $this->table($loan_headers, $loan_rows);
            }

            if(!empty($refunds)) {
                $refund_headers = array_keys($refunds[array_key_first($refunds)]);
                $refund_rows = array_map('array_values', $refunds);
                $this->info("Created Refunds:");
                $this->table($refund_headers, $refund_rows);
            }
        } else if(isset($chunk_result['error'])) {
            $this->error("Error processing chunk {$chunk_number}:");
            isset($chunk_result['message']) ? $this->line($chunk_result['message']) : $this->line($chunk_result['error']);
        } else {
            $this->info("Chunk {$chunk_number} processed with no data.");
        }

        $duration = round((microtime(true) - $start) * 1000);
        $this->info("Completed in {$duration} ms.");

        $chunk_number++;
    }
})->purpose('Import CSV files');
