<?php

use App\Auth\Controllers\AuthController;
use App\User\Controllers\UserController;
use App\DegiroTransaction\Infrastructure\Controllers\UploadDegiroTransactionController;
use App\DegiroTransaction\Infrastructure\Controllers\ListDegiroTransactionsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Authentication routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

// Account routes (authenticated)
Route::get('/account', [UserController::class, 'account'])->middleware('auth:sanctum');
Route::get('/degiro-transactions/count', [UserController::class, 'degiroTransactionsCount'])->middleware('auth:sanctum');

// Degiro Transaction routes (authenticated)
Route::post('/upload-degiro-transactions', [UploadDegiroTransactionController::class, 'upload'])->middleware('auth:sanctum');
Route::get('/degiro-transactions', [ListDegiroTransactionsController::class, 'index'])->middleware('auth:sanctum');

