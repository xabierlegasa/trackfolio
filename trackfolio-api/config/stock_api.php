<?php

return [
    /*
    |--------------------------------------------------------------------------
    | API Keys
    |--------------------------------------------------------------------------
    |
    | API keys for each provider. These should be set in your .env file.
    | The active provider is chosen per request (defaults to finnhub in code).
    |
    */

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

