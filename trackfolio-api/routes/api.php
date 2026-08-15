<?php

use App\Auth\Controllers\AuthController;
use App\DegiroTransaction\Infrastructure\Controllers\DeleteAllDegiroTransactionsController;
use App\DegiroTransaction\Infrastructure\Controllers\ListDegiroTransactionsController;
use App\DegiroTransaction\Infrastructure\Controllers\PortfolioStatsController;
use App\DegiroTransaction\Infrastructure\Controllers\TradesController;
use App\DegiroTransaction\Infrastructure\Controllers\TradesSummaryController;
use App\DegiroTransaction\Infrastructure\Controllers\UploadDegiroTransactionController;
use App\Dummy\Controllers\DummyController;
use App\Isin\Infrastructure\Controllers\StockCandleController;
use App\Portfolio\Infrastructure\Controllers\PortfolioEvolutionController;
use App\TaxReturn\Infrastructure\Controllers\TaxReturnYearAuditController;
use App\TaxReturn\Infrastructure\Controllers\TaxReturnYearDetailController;
use App\TaxReturn\Infrastructure\Controllers\TaxReturnYearsController;
use App\User\Controllers\UserController;
use App\User\Controllers\UserLeverageController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public routes (no authentication required)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/dummy', [DummyController::class, 'index']);
Route::post('/dummy/ping-queue', [DummyController::class, 'pingQueue']);
Route::post('/dummy/reset-password', [DummyController::class, 'resetPassword']);
Route::get('/dummy/test-api', [DummyController::class, 'testApi']);
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
    Route::get('/leverage', [UserLeverageController::class, 'show']);
    Route::post('/leverage', [UserLeverageController::class, 'store']);

    // Degiro Transaction routes
    Route::post('/upload-degiro-transactions', [UploadDegiroTransactionController::class, 'upload']);
    Route::get('/degiro-transactions', [ListDegiroTransactionsController::class, 'index']);
    Route::delete('/degiro-transactions', [DeleteAllDegiroTransactionsController::class, 'destroy']);

    // Portfolio Statistics routes
    Route::get('/portfolio-stats', [PortfolioStatsController::class, 'index']);
    Route::get('/portfolio-evolution', [PortfolioEvolutionController::class, 'index']);

    // Trades routes
    Route::get('/trades', [TradesController::class, 'index']);
    Route::get('/trades-summary', [TradesSummaryController::class, 'index']);

    Route::get('/tax-return/years', [TaxReturnYearsController::class, 'index']);
    Route::get('/tax-return/{year}/audit/{isin}', [TaxReturnYearAuditController::class, 'show']);
    Route::get('/tax-return/{year}', [TaxReturnYearDetailController::class, 'show']);
});
