<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    public const STATE_ASSIGNED = 'ASSIGNED';
    public const STATE_PARTIALLY_ASSIGNED = 'PARTIALLY_ASSIGNED';
    public const STATE_REJECTED = 'REJECTED';

    public const SOURCE_API = 'api';
    public const SOURCE_CSV = 'csv';

    public const PAYMENTS_TABLE = 'payments';

    public const COLUMN_ID = 'id';
    public const COLUMN_PAYER_NAME = 'payer_name';
    public const COLUMN_PAYER_SURNAME = 'payer_surname';
    public const COLUMN_AMOUNT = 'amount';
    public const COLUMN_SSN = 'ssn';
    public const COLUMN_LOAN_REFERENCE = 'loan_reference';
    public const COLUMN_PAYMENT_REFERENCE = 'payment_reference';
    public const COLUMN_STATE = 'state';
    public const COLUMN_CODE = 'code';
    public const COLUMN_SOURCE = 'source';
    public const COLUMN_PAYMENT_DATE = 'payment_date';

    public const CODE_SUCCESS = 0;
    public const CODE_ERROR_DUPLICATE = 1;
    public const CODE_ERROR_AMOUNT = 2;
    public const CODE_ERROR_PAYMENT_DATE = 3;
    public const CODE_ERROR_LOAN_REFERENCE = 4;
    public const CODE_ERROR_ALREADY_PAID = 5;
    public const CODE_ERROR_UNKNOWN = 99;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            self::COLUMN_PAYMENT_DATE => 'datetime',
        ];
    }

    protected $fillable = [
        self::COLUMN_PAYMENT_DATE,
        self::COLUMN_PAYER_NAME,
        self::COLUMN_PAYER_SURNAME,
        self::COLUMN_AMOUNT,
        self::COLUMN_SSN,
        self::COLUMN_LOAN_REFERENCE,
        self::COLUMN_PAYMENT_REFERENCE,
        self::COLUMN_STATE,
        self::COLUMN_CODE,
    ];
}
