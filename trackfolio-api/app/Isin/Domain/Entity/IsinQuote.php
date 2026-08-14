<?php

namespace App\Isin\Domain\Entity;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IsinQuote extends Model
{
    protected $table = 'isin_quotes';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'isin',
        'ticker_symbol',
        'closing_date',
        'close_price_min_unit',
        'open_price_min_unit',
        'high_price_min_unit',
        'low_price_min_unit',
        'volume',
        'currency',
        'stock_exchange',
        'provider',
        'ticker_request_id',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'closing_date' => 'date',
        'close_price_min_unit' => 'integer',
        'open_price_min_unit' => 'integer',
        'high_price_min_unit' => 'integer',
        'low_price_min_unit' => 'integer',
        'volume' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function tickerRequest(): BelongsTo
    {
        return $this->belongsTo(TickerRequest::class);
    }
}
