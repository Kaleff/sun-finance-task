<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Loan extends Model
{
    use HasFactory;

    public const STATE_ACTIVE = 'ACTIVE';
    public const STATE_PAID = 'PAID';

    public const LOANS_TABLE = 'loans';
    public const LOANS_TABLE_TESTING = 'testing.loans';

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
            self::COLUMN_AMOUNT_ISSUED => 'decimal:2',
            self::COLUMN_AMOUNT_TO_PAY => 'decimal:2',
            self::COLUMN_AMOUNT_PAID => 'decimal:2',
        ];
    }

    protected $fillable = [
        self::COLUMN_AMOUNT_PAID,
        self::COLUMN_STATE,
        self::COLUMN_UPDATED_AT,
    ];

    protected $primaryKey = self::COLUMN_ID;
    public $incrementing = false;
    protected $keyType = 'string';

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, foreignKey: self::COLUMN_CUSTOMER_ID, ownerKey: Customer::COLUMN_ID);
    }
}
