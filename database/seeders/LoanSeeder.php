<?php

namespace Database\Seeders;

use App\Models\Loan;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LoanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $loan = new Loan();
        $loan->id = '51ed9314-955c-4014-8be2-b0e2b13588a5';
        $loan->customer_id = 'c539792e-7773-4a39-9cf6-f273b2581438';
        $loan->reference = "LN12345678";
        $loan->state = 'ACTIVE';
        $loan->amount_issued = 100.00;
        $loan->amount_to_pay = 120.00;
        $loan->save();

        $loan = new Loan();
        $loan->id = 'a54b0796-2fcb-4547-b23d-125786600ec3';
        $loan->customer_id = 'c539792e-7773-4a39-9cf6-f273b2581438';
        $loan->reference = "LN22345678";
        $loan->state = 'ACTIVE';
        $loan->amount_issued = 200.00;
        $loan->amount_to_pay = 250.00;
        $loan->save();

        $loan = new Loan();
        $loan->id = 'f7f81281-64a9-47a7-af60-5c6896896d1f';
        $loan->customer_id = 'd275ce5e-91c8-49fe-9407-1700b59efe80';
        $loan->reference = 'LN55522533';
        $loan->state = 'ACTIVE';
        $loan->amount_issued = 50.00;
        $loan->amount_to_pay = 70.00;
        $loan->save();

        $loan = new Loan();
        $loan->id = "b8d26e7b-1607-441d-8bb0-87517a874572";
        $loan->customer_id = 'c5c05eeb-ff02-4de6-b92e-a1b7f02320df';
        $loan->reference = 'LN20221212';
        $loan->state = 'ACTIVE';
        $loan->amount_issued = 66.00;
        $loan->amount_to_pay = 100.00;
        $loan->save();
    }
}
