<?php

use App\Http\Controllers\CsvController;
use App\Services\PaymentImportService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('csv:import', function () {
    $start = microtime(true); // Start timer
    $payment_service = new PaymentImportService();
    $data = $payment_service->import();
    // Create a table from data
    $headers = array_keys($data[0]);
    $rows = array_map('array_values', $data);
    $this->table($headers, $rows);

    $duration = round((microtime(true) - $start) * 1000);
    $this->info("Completed in {$duration} ms.");
})->purpose('Import CSV files');
