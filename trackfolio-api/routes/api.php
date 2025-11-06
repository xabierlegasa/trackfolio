<?php

use App\Auth\Controllers\AuthController;
use App\User\Controllers\UserController;
use App\DegiroTransaction\Infrastructure\Controllers\UploadDegiroTransactionController;
use App\DegiroTransaction\Infrastructure\Controllers\ListDegiroTransactionsController;
use App\DegiroTransaction\Infrastructure\Controllers\PortfolioStatsController;
use App\DegiroTransaction\Infrastructure\Controllers\TradesController;
use App\DegiroTransaction\Infrastructure\Controllers\TradesSummaryController;
use App\Dummy\Controllers\DummyController;
use App\Isin\Infrastructure\Controllers\StockCandleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public routes (no authentication required)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/dummy', [DummyController::class, 'index']);
Route::get('/stock-candle', [StockCandleController::class, 'index']);

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {
    // User routes
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    
    // Authentication routes
    Route::post('/logout', [AuthController::class, 'logout']);
    
    // Account routes
    Route::get('/account', [UserController::class, 'account']);
    Route::get('/degiro-transactions/count', [UserController::class, 'degiroTransactionsCount']);
    
    // Degiro Transaction routes
    Route::post('/upload-degiro-transactions', [UploadDegiroTransactionController::class, 'upload']);
    Route::get('/degiro-transactions', [ListDegiroTransactionsController::class, 'index']);
    
    // Portfolio Statistics routes
    Route::get('/portfolio-stats', [PortfolioStatsController::class, 'index']);
    
    // Trades routes
    Route::get('/trades', [TradesController::class, 'index']);
    Route::get('/trades-summary', [TradesSummaryController::class, 'index']);
});

