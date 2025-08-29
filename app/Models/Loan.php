<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    public const STATE_ACTIVE = 'ACTIVE';
    public const STATE_PAID = 'PAID';

    public const LOANS_TABLE = 'loans';

    public const COLUMN_ID = 'id';
    public const COLUMN_CUSTOMER_ID = 'customer_id';
    public const COLUMN_REFERENCE = 'reference';
    public const COLUMN_STATE = 'state';
    public const COLUMN_AMOUNT_ISSUED = 'amount_issued';
    public const COLUMN_AMOUNT_TO_PAY = 'amount_to_pay';
    public const COLUMN_AMOUNT_PAID = 'amount_paid';
    // Timestamps
    public const COLUMN_CREATED_AT = 'created_at';
    public const COLUMN_UPDATED_AT = 'updated_at';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            self::COLUMN_ID => 'string',
        ];
    }

    protected $fillable = [
        self::COLUMN_AMOUNT_PAID,
        self::COLUMN_STATE,
        self::COLUMN_UPDATED_AT,
    ];
}
