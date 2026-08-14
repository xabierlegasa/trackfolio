<?php

namespace App\Isin\Domain\Entity;

use Illuminate\Database\Eloquent\Model;

class TickerRequest extends Model
{
    protected $table = 'ticker_requests';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'isin',
        'ticker_symbol',
        'closing_date',
        'provider',
        'stock_exchange',
        'response',
        'error_message',
        'provider_response_http_status',
        'success',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'closing_date' => 'date',
        'response' => 'array',
        'success' => 'boolean',
        'provider_response_http_status' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
