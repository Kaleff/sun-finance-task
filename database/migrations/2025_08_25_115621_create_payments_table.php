<?php

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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('payer_name');
            $table->string('payer_surname');
            $table->decimal('amount', 10, 2);
            $table->string('ssn'); // nationalSecurityNumber in csv file
            $table->string('loan_reference'); // Description column in csv file
            $table->string('payment_reference')->unique();
            $table->enum('state', ['ASSIGNED', 'PARTIALLY_ASSIGNED', 'REJECTED']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
