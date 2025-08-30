<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_FAILED = 'FAILED';

    public const REFUNDS_TABLE = 'refunds';

    public const COLUMN_PAYMENT_REFERENCE = 'payment_reference';
    public const COLUMN_AMOUNT = 'amount';
    public const COLUMN_STATUS = 'status';
    public const COLUMN_CREATED_AT = 'created_at';
    public const COLUMN_UPDATED_AT = 'updated_at';

    protected $fillable = [
        self::COLUMN_PAYMENT_REFERENCE,
        self::COLUMN_AMOUNT,
        self::COLUMN_STATUS,
        self::COLUMN_CREATED_AT,
        self::COLUMN_UPDATED_AT
    ];

    protected $casts = [
        self::COLUMN_AMOUNT => 'decimal:2',
    ];
}
