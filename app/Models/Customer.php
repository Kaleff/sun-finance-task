<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    public static const CUSTOMERS_TABLE = 'customers';

    public static const COLUMN_ID = 'id';
    public static const COLUMN_FIRST_NAME = 'first_name';
    public static const COLUMN_LAST_NAME = 'last_name';
    public static const COLUMN_SSN = 'ssn';
    public static const COLUMN_EMAIL = 'email';
    public static const COLUMN_PHONE = 'phone';

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
}
