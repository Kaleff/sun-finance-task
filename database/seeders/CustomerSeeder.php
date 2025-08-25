<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $customer = new Customer();
        $customer->id = 'c539792e-7773-4a39-9cf6-f273b2581438';
        $customer->first_name = 'Pupa';
        $customer->last_name = 'Lupa';
        $customer->ssn = '0987654321';
        $customer->email = 'pupa.lupa@example.com';
        $customer->save();

        $customer = new Customer();
        $customer->id = 'd275ce5e-91c8-49fe-9407-1700b59efe80';
        $customer->first_name = 'John';
        $customer->last_name = 'Doe';
        $customer->ssn = '1234509876';
        $customer->phone = '+44123456789';
        $customer->save();

        $customer = new Customer();
        $customer->id = 'a5c50ea9-9a24-4c8b-b4ae-c47ee007081e';
        $customer->first_name = 'Biba';
        $customer->last_name = 'Boba';
        $customer->ssn = '1234567890';
        $customer->email = 'biba@example.com';
        $customer->phone = '+44123456780';
        $customer->save();

        $customer = new Customer();
        $customer->id = 'c5c05eeb-ff02-4de6-b92e-a1b7f02320df';
        $customer->first_name = 'Lorem';
        $customer->last_name = 'Ipsum';
        $customer->ssn = '6789054321';
        $customer->email = 'lorem@ipsum.com';
        $customer->phone = '+481230943320';
        $customer->save();
    }
}
