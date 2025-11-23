<?php

use App\Http\Controllers\AuthController;
use App\Http\Middleware\CheckIdempotency;
use Illuminate\Support\Facades\Route;

// Rotas Públicas
Route::group(['prefix' => 'auth'], function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    Route::post('refresh', [AuthController::class, 'refresh']);
});

// Rotas Protegidas
Route::middleware(['auth:api'])->group(function () {

    // Auth Management
    Route::prefix('auth')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('webhook', [AuthController::class, 'updateWebhook']);
        Route::post('logout', [AuthController::class, 'logout']);
    });

    // Wallet Operations
    Route::prefix('wallet')
        ->middleware([CheckIdempotency::class])
        ->group(function () {
            Route::get('balance', [App\Http\Controllers\WalletController::class, 'balance']);
            Route::get('transactions', [App\Http\Controllers\WalletController::class, 'transactions']);
            Route::post('deposit', [App\Http\Controllers\WalletController::class, 'deposit']);
            Route::post('withdraw', [App\Http\Controllers\WalletController::class, 'withdraw']);
            Route::post('transfer', [App\Http\Controllers\WalletController::class, 'transfer']);
        });
});
