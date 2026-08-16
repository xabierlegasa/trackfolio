<?php

return [
    /*
    |--------------------------------------------------------------------------
    | API Keys
    |--------------------------------------------------------------------------
    |
    | API keys for each provider. These should be set in your .env file.
    | Active closing-price provider order is EODHD-only (see ResolveIsinClosingPriceService).
    | Other providers remain configured for manual/debug use.
    |
    */

    'eodhd' => [
        'api_token' => env('EODHD_API_TOKEN'),
        'base_url' => env('EODHD_BASE_URL', 'https://eodhd.com/api'),
    ],

    'finnhub' => [
        'api_key' => env('FINNHUB_API_KEY'),
    ],

    'fmp' => [
        'api_key' => env('FMP_API_KEY'),
    ],

    'alphavantage' => [
        'api_key' => env('ALPHAVANTAGE_API_KEY'),
    ],
];

