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

    public static const PAYMENTS_TABLE = 'payments';

    public static const COLUMN_ID = 'id';
    public static const COLUMN_PAYER_NAME = 'payer_name';
    public static const COLUMN_PAYER_SURNAME = 'payer_surname';
    public static const COLUMN_AMOUNT = 'amount';
    public static const COLUMN_SSN = 'ssn';
    public static const COLUMN_LOAN_REFERENCE = 'loan_reference';
    public static const COLUMN_PAYMENT_REFERENCE = 'payment_reference';
    public static const COLUMN_STATE = 'state';
    public static const COLUMN_CODE = 'code';
    public static const COLUMN_SOURCE = 'source';
    public static const COLUMN_PAYMENT_DATE = 'payment_date';


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
