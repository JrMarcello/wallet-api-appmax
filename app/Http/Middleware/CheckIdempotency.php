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
        if (!$request->isMethod('POST') && !$request->isMethod('PUT') && !$request->isMethod('PATCH')) {
            return $next($request);
        }

        // Check Header Idempotency-Key
        // Idempotencia é opcional, então só age se o header estiver presente
        $key = $request->header('Idempotency-Key');
        if (!$key) {
            return $next($request);
        }

        $user = auth('api')->user();
        $userId = $user ? $user->id : 'guest';
        $cacheKey = "idempotency_{$userId}_{$key}";
        
        $ctx = ['key' => $key, 'user_id' => $userId, 'method' => $request->method()];
        
        if (Cache::has($cacheKey)) {
            Log::info("Idempotency: HIT (Redis)", $ctx);
            $cachedResponse = Cache::get($cacheKey);
            
            return response()->json(
                $cachedResponse['content'], 
                $cachedResponse['status']
            )->header('X-Idempotency-Hit', 'true');
        }

        Log::debug("Idempotency: MISS (Processing)", $ctx);

        $response = $next($request);
        if ($response->isSuccessful()) {
            Log::debug("Idempotency: STORE", $ctx);
            
            // Pegamos o conteúdo cru para salvar string no banco e array no Redis
            $rawContent = $response->getContent();
            $contentArray = json_decode($rawContent, true);
            
            // Salvar no Redis (Rápido, TTL curto de 24h)
            Cache::put($cacheKey, [
                'status' => $response->getStatusCode(),
                'content' => $contentArray
            ], now()->addDay());

            // Salvar no MySQL (Auditoria, Persistente)
            // try/catch para não bloquear a resposta ao cliente
            try {
                DB::table('idempotency_keys')->insertOrIgnore([
                    'key' => $key,
                    'user_id' => $user ? $user->id : null, // Nullable no banco
                    'response_json' => $rawContent, // Salva o JSON exato retornado
                    'status_code' => $response->getStatusCode(),
                    'created_at' => now(),
                    'expires_at' => now()->addDay(), // Mantemos a semântica de expiração
                ]);
            } catch (\Exception $e) {
                Log::error("Idempotency: DB Persist Failed - " . $e->getMessage(), $ctx);
            }
        }

        return $response;
    }
}
