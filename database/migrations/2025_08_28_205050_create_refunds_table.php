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
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->string('payment_reference');
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['PENDING', 'COMPLETED', 'FAILED'])->default('PENDING');
            $table->timestamps();
        });

        Schema::table('refunds', function (Blueprint $table) {
            $table->foreign('payment_reference')->references('payment_reference')->on('payments')->onDelete('cascade');
            $table->index(['payment_reference', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->dropForeign(['payment_reference']);
            $table->dropIndex(['payment_reference', 'status']);
        });
        Schema::dropIfExists('refunds');
    }
};
