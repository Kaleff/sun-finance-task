<?php

use App\Models\Customer;
use App\Models\Loan;
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
        Schema::create(Loan::LOANS_TABLE, function (Blueprint $table) {
            $table->uuid(Loan::COLUMN_ID)->primary()->unique();
            $table->foreignUuid(Loan::COLUMN_CUSTOMER_ID);
            $table->string(Loan::COLUMN_REFERENCE)->unique();
            $table->enum(Loan::COLUMN_STATE, [Loan::STATE_ACTIVE, Loan::STATE_PAID])->default(Loan::STATE_ACTIVE);
            $table->decimal(Loan::COLUMN_AMOUNT_ISSUED, 10, 2);
            $table->decimal(Loan::COLUMN_AMOUNT_TO_PAY, 10, 2);
            $table->decimal(Loan::COLUMN_AMOUNT_PAID, 10, 2)->default(0.00);
            $table->timestamps();
        });

        Schema::table(Loan::LOANS_TABLE, function (Blueprint $table) {
            $table->foreign(Loan::COLUMN_CUSTOMER_ID)->references(Customer::COLUMN_ID)->on(Customer::CUSTOMERS_TABLE)->onDelete('cascade');
            $table->index([Loan::COLUMN_CUSTOMER_ID, Loan::COLUMN_REFERENCE, Loan::COLUMN_STATE]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(Loan::LOANS_TABLE, function (Blueprint $table) {
            $table->dropForeign([Loan::COLUMN_CUSTOMER_ID]);
            $table->dropIndex([Loan::COLUMN_CUSTOMER_ID, Loan::COLUMN_REFERENCE, Loan::COLUMN_STATE]);
        });
        Schema::dropIfExists(Loan::LOANS_TABLE);
    }
};
