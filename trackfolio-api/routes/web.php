<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'message' => 'This is an API-only application. Please use the /api routes.',
        'version' => app()->version(),
    ]);
});
