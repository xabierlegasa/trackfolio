<?php

use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Controllers\CsrfCookieController;

Route::get('/', function () {
    return response()->json([
        'message' => 'This is an API-only application. Please use the /api routes.',
        'version' => app()->version(),
    ]);
});

// Sanctum CSRF cookie route for SPA authentication
Route::get('/sanctum/csrf-cookie', [CsrfCookieController::class, 'show']);
