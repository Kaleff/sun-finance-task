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
            $table->string('ssn')->nullable(); // nationalSecurityNumber and nullable in csv file, not present in API
            $table->string('loan_reference'); // Description column in csv file and api
            $table->string('payment_reference')->unique(); // RefId in api, paymentReference in csv file
            $table->enum('state', ['ASSIGNED', 'PARTIALLY_ASSIGNED', 'REJECTED']);
            $table->string('rejection_reason')->nullable(); // Reason for rejection
            $table->enum('source', ['api', 'csv']);
            $table->dateTime('payment_date');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreign('loan_reference')->references('reference')->on('loans')->onDelete('cascade');
            $table->index(['ssn', 'loan_reference', 'state', 'source']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['loan_reference']);
            $table->dropIndex(['ssn', 'loan_reference', 'state', 'source']);
        });
        Schema::dropIfExists('payments');
    }
};
