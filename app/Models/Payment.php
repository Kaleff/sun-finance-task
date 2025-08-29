<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    public static const STATE_ASSIGNED = 'ASSIGNED';
    public static const STATE_PARTIALLY_ASSIGNED = 'PARTIALLY_ASSIGNED';
    public static const STATE_REJECTED = 'REJECTED';

    public static const SOURCE_API = 'api';
    public static const SOURCE_CSV = 'csv';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payment_date' => 'datetime',
        ];
    }

    protected $fillable = [
        'payment_date',
        'payer_name',
        'payer_surname',
        'amount',
        'ssn',
        'loan_reference',
        'payment_reference',
        'state'
    ];
}
