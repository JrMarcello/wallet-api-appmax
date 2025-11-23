<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        // web: __DIR__.'/../routes/web.php',
        // commands: __DIR__.'/../routes/console.php',
        api: __DIR__.'/../routes/api.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Registra para todas as rotas da API
        $middleware->api(prepend: [
            \App\Http\Middleware\CheckIdempotency::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Captura qualquer erro 500 não tratado
        $exceptions->reportable(function (Throwable $e) {
            Log::error('Global Exception Handler', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'url' => request()->fullUrl(),
                'user_id' => auth('api')->id() ?? null,
                // 'trace' => $e->getTraceAsString() // Opcional se quiser verbosidade
            ]);
        });
    })->create();
