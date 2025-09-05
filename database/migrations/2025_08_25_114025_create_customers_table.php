<?php

use App\Models\Customer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(Customer::CUSTOMERS_TABLE, function (Blueprint $table) {
            $table->uuid(Customer::COLUMN_ID)->primary();
            $table->string(Customer::COLUMN_FIRST_NAME);
            $table->string(Customer::COLUMN_LAST_NAME);
            $table->string(Customer::COLUMN_SSN);
            $table->string(Customer::COLUMN_EMAIL)->unique()->nullable();
            $table->string(Customer::COLUMN_PHONE)->unique()->nullable();
            $table->timestamps();
        });
        // I didn't create indexes for the other columns since the data is not really requested yet.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(Customer::CUSTOMERS_TABLE);
    }
};
