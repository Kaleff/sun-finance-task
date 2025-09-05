<?php

use App\Models\Payment;
use App\Models\Refund;
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
        Schema::create(Refund::REFUNDS_TABLE, function (Blueprint $table) {
            $table->id();
            $table->string(Refund::COLUMN_PAYMENT_REFERENCE);
            $table->decimal(Refund::COLUMN_AMOUNT, 10, 2);
            $table->enum(Refund::COLUMN_STATUS, [Refund::STATUS_PENDING, Refund::STATUS_COMPLETED, Refund::STATUS_FAILED])
                ->default(Refund::STATUS_PENDING);
            $table->timestamps();
        });

        Schema::table(Refund::REFUNDS_TABLE, function (Blueprint $table) {
            $table->foreign(Refund::COLUMN_PAYMENT_REFERENCE)->references(Payment::COLUMN_PAYMENT_REFERENCE)->on(Payment::PAYMENTS_TABLE)->onDelete('cascade');
            $table->index([Refund::COLUMN_PAYMENT_REFERENCE, Refund::COLUMN_STATUS]);
        });

        Schema::create(Refund::REFUNDS_TABLE_TESTING, function (Blueprint $table) {
            $table->id();
            $table->string(Refund::COLUMN_PAYMENT_REFERENCE);
            $table->decimal(Refund::COLUMN_AMOUNT, 10, 2);
            $table->enum(Refund::COLUMN_STATUS, [Refund::STATUS_PENDING, Refund::STATUS_COMPLETED, Refund::STATUS_FAILED])
                ->default(Refund::STATUS_PENDING);
            $table->timestamps();
        });

        Schema::table(Refund::REFUNDS_TABLE_TESTING, function (Blueprint $table) {
            $table->foreign(Refund::COLUMN_PAYMENT_REFERENCE)->references(Payment::COLUMN_PAYMENT_REFERENCE)->on(Payment::PAYMENTS_TABLE_TESTING)->onDelete('cascade');
            $table->index([Refund::COLUMN_PAYMENT_REFERENCE, Refund::COLUMN_STATUS]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(Refund::REFUNDS_TABLE, function (Blueprint $table) {
            $table->dropForeign([Refund::COLUMN_PAYMENT_REFERENCE]);
            $table->dropIndex([Refund::COLUMN_PAYMENT_REFERENCE, Refund::COLUMN_STATUS]);
        });
        Schema::dropIfExists(Refund::REFUNDS_TABLE);

        Schema::table(Refund::REFUNDS_TABLE_TESTING, function (Blueprint $table) {
            $table->dropForeign([Refund::COLUMN_PAYMENT_REFERENCE]);
            $table->dropIndex([Refund::COLUMN_PAYMENT_REFERENCE, Refund::COLUMN_STATUS]);
        });
        Schema::dropIfExists(Refund::REFUNDS_TABLE_TESTING);
    }
};
