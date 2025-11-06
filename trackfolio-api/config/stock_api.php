<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Stock API Provider
    |--------------------------------------------------------------------------
    |
    | This option controls which stock API provider to use.
    | Available options: 'finnhub', 'fmp'
    |
    */

    'provider' => env('STOCK_API_PROVIDER', 'fmp'),

    /*
    |--------------------------------------------------------------------------
    | API Keys
    |--------------------------------------------------------------------------
    |
    | API keys for each provider. These should be set in your .env file.
    |
    */

    'finnhub' => [
        'api_key' => env('FINNHUB_API_KEY'),
    ],

    'fmp' => [
        'api_key' => env('FMP_API_KEY'),
    ],
];

