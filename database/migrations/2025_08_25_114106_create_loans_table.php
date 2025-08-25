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
        Schema::create('loans', function (Blueprint $table) {
            $table->string('id');
            $table->string('customer_id');
            $table->string('reference');
            $table->enum('state', ['ACTIVE', 'PAID']);
            $table->decimal('amount_issued', 10, 2);
            $table->decimal('amount_to_pay', 10, 2);
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->timestamps();
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->index(['customer_id', 'reference']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropIndex(['customer_id', 'reference']);
        });
        Schema::dropIfExists('loans');
    }
};
