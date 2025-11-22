<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;


// Rotas Públicas (Não exigem Token)
Route::group(['prefix' => 'auth'], function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
});

// Rotas Protegidas (Exigem Token JWT)
Route::middleware(['auth:api'])->group(function () {
    
    // Auth Management
    Route::prefix('auth')->group(function () {
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });

    // Wallet Operations
    Route::prefix('wallet')->group(function () {
        Route::get('balance', [App\Http\Controllers\WalletController::class, 'balance']);
        Route::get('transactions', [App\Http\Controllers\WalletController::class, 'transactions']);
        
        Route::post('deposit', [App\Http\Controllers\WalletController::class, 'deposit']);
        Route::post('withdraw', [App\Http\Controllers\WalletController::class, 'withdraw']);
        Route::post('transfer', [App\Http\Controllers\WalletController::class, 'transfer']);
    });
});
