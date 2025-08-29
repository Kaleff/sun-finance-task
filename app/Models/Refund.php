<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    public static const STATUS_PENDING = 'PENDING';
    public static const STATUS_COMPLETED = 'COMPLETED';
    public static const STATUS_FAILED = 'FAILED';

    protected $fillable = [
        'payment_reference',
        'amount',
        'status'
    ];
}
