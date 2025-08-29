<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    public static const STATE_ACTIVE = 'ACTIVE';
    public static const STATE_PAID = 'PAID';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'string',
        ];
    }

    protected $fillable = [
        'amount_paid',
        'state',
        'updated_at',
    ];
}
