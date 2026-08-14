<?php

namespace App\ExchangeRate\Domain\Entity;

use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    protected $table = 'exchange_rates';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'rate_date',
        'base_currency',
        'quote_currency',
        'rate',
        'provider',
        'response',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'rate_date' => 'date',
        'rate' => 'float',
        'response' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
