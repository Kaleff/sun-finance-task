<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    public const CUSTOMERS_TABLE = 'customers';

    public const COLUMN_ID = 'id';
    public const COLUMN_FIRST_NAME = 'first_name';
    public const COLUMN_LAST_NAME = 'last_name';
    public const COLUMN_SSN = 'ssn';
    public const COLUMN_EMAIL = 'email';
    public const COLUMN_PHONE = 'phone';

    // Uncomment if you need mass-insertion
    /*
    protected $fillable = [
        self::COLUMN_ID,
        self::COLUMN_FIRST_NAME,
        self::COLUMN_LAST_NAME,
        self::COLUMN_SSN,
        self::COLUMN_EMAIL,
        self::COLUMN_PHONE
    ];
    */

    protected $primaryKey = self::COLUMN_ID;
    public $incrementing = false;
    protected $keyType = 'string';
}
