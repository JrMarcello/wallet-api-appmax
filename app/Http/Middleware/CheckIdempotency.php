<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class CheckIdempotency
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Ignora métodos de leitura (GET, HEAD, OPTIONS)
        if ($request->isMethodSafe()) {
            return $next($request);
        }

        $key = $request->header('Idempotency-Key');
        if (empty($key)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Header "Idempotency-Key" is required for state-changing operations.',
            ], 400);
        }

        $user = auth('api')->user();
        $userId = $user ? $user->id : 'guest';
        $cacheKey = "idempotency_{$userId}_{$key}";
        $ctx = ['key' => $key, 'user_id' => $userId, 'method' => $request->method()];

        if (Cache::has($cacheKey)) {
            Log::info('Idempotency: HIT (Redis)', $ctx);
            $cachedResponse = Cache::get($cacheKey);

            return response()->json($cachedResponse['content'], $cachedResponse['status'])
                ->header('X-Idempotency-Hit', 'true');
        }

        Log::debug('Idempotency: MISS (Processing)', $ctx);

        $response = $next($request);
        if ($response->isSuccessful()) {
            Log::debug('Idempotency: STORE', $ctx);
            $rawContent = $response->getContent();
            $contentArray = json_decode($rawContent, true);

            Cache::put($cacheKey, [
                'status' => $response->getStatusCode(),
                'content' => $contentArray,
            ], now()->addDay());

            try {
                DB::table('idempotency_keys')->insertOrIgnore([
                    'key' => $key,
                    'user_id' => $user ? $user->id : null,
                    'response_json' => $rawContent,
                    'status_code' => $response->getStatusCode(),
                    'created_at' => now(),
                    'expires_at' => now()->addDay(),
                ]);
            } catch (\Exception $e) {
                Log::error('Idempotency: DB Persist Failed - '.$e->getMessage(), $ctx);
            }
        }

        return $response;
    }
}
