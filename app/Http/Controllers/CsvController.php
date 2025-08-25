<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CsvController extends Controller
{
    public function import()
    {
        // Get CSV File contents, hard-coded file location as a placeholder for potential csv from external sources
        $csvFile = storage_path('external/payments.csv');
        $contents = $this->readCSV($csvFile);
        dd($contents);
        // Process the CSV contents as needed
    }

    private function readCSV($csvFile, $delimiter = ',')
    {
        $row_number = 0;
        $file_handle = fopen($csvFile, 'r');
        while ($csvRow = fgetcsv($file_handle, null, $delimiter)) {
            if($row_number === 0) {
                // Get column names from the first row
                $column_names = $csvRow;
                $row_number++;
                continue;
            }
            $contents[] = array_combine($column_names, $csvRow);
        }
        fclose($file_handle);
        return $contents;
    }
}
