<?php

use App\Models\Payment;
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
        Schema::create(Payment::PAYMENTS_TABLE, function (Blueprint $table) {
            $table->id();
            $table->string(Payment::COLUMN_PAYER_NAME);
            $table->string(Payment::COLUMN_PAYER_SURNAME);
            $table->decimal(Payment::COLUMN_AMOUNT, 10, 2);
            $table->string(Payment::COLUMN_SSN)->nullable(); // nationalSecurityNumber and nullable in csv file, not present in API
            $table->string(Payment::COLUMN_LOAN_REFERENCE); // Description column in csv file and api
            $table->string(Payment::COLUMN_PAYMENT_REFERENCE)->unique(); // RefId in api, paymentReference in csv file
            $table->enum(Payment::COLUMN_STATE, [Payment::STATE_ASSIGNED, Payment::STATE_PARTIALLY_ASSIGNED, Payment::STATE_REJECTED]);
            $table->integer(Payment::COLUMN_CODE)->nullable(); // 0 for success, everything else is an error
            $table->enum(Payment::COLUMN_SOURCE, [Payment::SOURCE_API, Payment::SOURCE_CSV]);
            $table->dateTimeTz(Payment::COLUMN_PAYMENT_DATE);
        });

        Schema::table(Payment::PAYMENTS_TABLE, function (Blueprint $table) {
            $table->foreign(Payment::COLUMN_LOAN_REFERENCE)->references('reference')->on('loans')->onDelete('cascade');
            $table->index([Payment::COLUMN_LOAN_REFERENCE, Payment::COLUMN_PAYMENT_REFERENCE]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(Payment::PAYMENTS_TABLE, function (Blueprint $table) {
            $table->dropForeign([Payment::COLUMN_LOAN_REFERENCE]);
            $table->dropIndex([Payment::COLUMN_LOAN_REFERENCE, Payment::COLUMN_PAYMENT_REFERENCE]);
        });
        Schema::dropIfExists(Payment::PAYMENTS_TABLE);
    }
};
