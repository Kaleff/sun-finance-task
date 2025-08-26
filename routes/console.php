<?php

use App\Http\Controllers\CsvController;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('csv:import', function () {
    $controller = new CsvController();
    $data = $controller->import();
    // Create a table from data
    $headers = array_keys($data[0]);
    $rows = array_map('array_values', $data);
    $this->table($headers, $rows);
})->purpose('Import CSV files');
