<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    public static const STATE_ACTIVE = 'ACTIVE';
    public static const STATE_PAID = 'PAID';

    public static const LOANS_TABLE = 'loans';

    public static const COLUMN_ID = 'id';
    public static const COLUMN_CUSTOMER_ID = 'customer_id';
    public static const COLUMN_REFERENCE = 'reference';
    public static const COLUMN_STATE = 'state';
    public static const COLUMN_AMOUNT_ISSUED = 'amount_issued';
    public static const COLUMN_AMOUNT_TO_PAY = 'amount_to_pay';
    public static const COLUMN_AMOUNT_PAID = 'amount_paid';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'string',
        ];
    }

    protected $fillable = [
        'amount_paid',
        'state',
        'updated_at',
    ];
}
