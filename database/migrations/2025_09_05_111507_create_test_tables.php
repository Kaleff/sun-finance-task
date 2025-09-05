<?php

use App\Models\Customer;
use App\Models\Loan;
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
        // Customers
        Schema::create(Customer::CUSTOMERS_TABLE_TESTING, function (Blueprint $table) {
            $table->uuid(Customer::COLUMN_ID)->primary();
            $table->string(Customer::COLUMN_FIRST_NAME);
            $table->string(Customer::COLUMN_LAST_NAME);
            $table->string(Customer::COLUMN_SSN);
            $table->string(Customer::COLUMN_EMAIL)->unique()->nullable();
            $table->string(Customer::COLUMN_PHONE)->unique()->nullable();
            $table->timestamps();
        });

        // Loans
        Schema::create(Loan::LOANS_TABLE_TESTING, function (Blueprint $table) {
            $table->uuid(Loan::COLUMN_ID)->primary()->unique();
            $table->foreignUuid(Loan::COLUMN_CUSTOMER_ID);
            $table->string(Loan::COLUMN_REFERENCE)->unique();
            $table->enum(Loan::COLUMN_STATE, [Loan::STATE_ACTIVE, Loan::STATE_PAID])->default(Loan::STATE_ACTIVE);
            $table->decimal(Loan::COLUMN_AMOUNT_ISSUED, 10, 2);
            $table->decimal(Loan::COLUMN_AMOUNT_TO_PAY, 10, 2);
            $table->decimal(Loan::COLUMN_AMOUNT_PAID, 10, 2)->default(0.00);
            $table->timestamps();
        });

        Schema::table(Loan::LOANS_TABLE_TESTING, function (Blueprint $table) {
            $table->foreign(Loan::COLUMN_CUSTOMER_ID)->references(Customer::COLUMN_ID)->on(Customer::CUSTOMERS_TABLE_TESTING)->onDelete('cascade');
            $table->index([Loan::COLUMN_CUSTOMER_ID, Loan::COLUMN_REFERENCE, Loan::COLUMN_STATE]);
        });

        // Payments
        Schema::create(Payment::PAYMENTS_TABLE_TESTING, function (Blueprint $table) {
            $table->id();
            $table->string(Payment::COLUMN_PAYER_NAME);
            $table->string(Payment::COLUMN_PAYER_SURNAME);
            $table->decimal(Payment::COLUMN_AMOUNT, 10, 2);
            $table->string(Payment::COLUMN_SSN)->nullable()->default(null); // nationalSecurityNumber and nullable in csv file, not present in API
            $table->string(Payment::COLUMN_LOAN_REFERENCE); // Description column in csv file and api
            $table->string(Payment::COLUMN_PAYMENT_REFERENCE)->unique(); // RefId in api, paymentReference in csv file
            $table->enum(Payment::COLUMN_STATE, [Payment::STATE_ASSIGNED, Payment::STATE_PARTIALLY_ASSIGNED, Payment::STATE_REJECTED]);
            $table->integer(Payment::COLUMN_CODE); // 0 for success, everything else is an error
            $table->enum(Payment::COLUMN_SOURCE, [Payment::SOURCE_API, Payment::SOURCE_CSV]);
            $table->dateTimeTz(Payment::COLUMN_PAYMENT_DATE);
        });

        Schema::table(Payment::PAYMENTS_TABLE_TESTING, function (Blueprint $table) {
            $table->foreign(Payment::COLUMN_LOAN_REFERENCE)->references(Loan::COLUMN_REFERENCE)->on(Loan::LOANS_TABLE_TESTING)->onDelete('cascade');
            $table->index([Payment::COLUMN_LOAN_REFERENCE, Payment::COLUMN_PAYMENT_REFERENCE]);
        });

        // Refunds
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
        // Refunds
        Schema::table(Refund::REFUNDS_TABLE_TESTING, function (Blueprint $table) {
            $table->dropForeign([Refund::COLUMN_PAYMENT_REFERENCE]);
            $table->dropIndex([Refund::COLUMN_PAYMENT_REFERENCE, Refund::COLUMN_STATUS]);
        });
        Schema::dropIfExists(Refund::REFUNDS_TABLE_TESTING);

        // Payments
        Schema::table(Payment::PAYMENTS_TABLE_TESTING, function (Blueprint $table) {
            $table->dropForeign([Payment::COLUMN_LOAN_REFERENCE]);
            $table->dropIndex([Payment::COLUMN_LOAN_REFERENCE, Payment::COLUMN_PAYMENT_REFERENCE]);
        });
        Schema::dropIfExists(Payment::PAYMENTS_TABLE_TESTING);

        // Loans
        Schema::table(Loan::LOANS_TABLE_TESTING, function (Blueprint $table) {
            $table->dropForeign([Loan::COLUMN_CUSTOMER_ID]);
            $table->dropIndex([Loan::COLUMN_CUSTOMER_ID, Loan::COLUMN_REFERENCE, Loan::COLUMN_STATE]);
        });
        Schema::dropIfExists(Loan::LOANS_TABLE_TESTING);

        // Customers
        Schema::dropIfExists(Customer::CUSTOMERS_TABLE_TESTING);
    }
};
