<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    public static const STATUS_PENDING = 'PENDING';
    public static const STATUS_COMPLETED = 'COMPLETED';
    public static const STATUS_FAILED = 'FAILED';

    public static const REFUNDS_TABLE = 'refunds';

    public static const COLUMN_PAYMENT_REFERENCE = 'payment_reference';
    public static const COLUMN_AMOUNT = 'amount';
    public static const COLUMN_STATUS = 'status';

    protected $fillable = [
        Refund::COLUMN_PAYMENT_REFERENCE,
        Refund::COLUMN_AMOUNT,
        Refund::COLUMN_STATUS
    ];
}
