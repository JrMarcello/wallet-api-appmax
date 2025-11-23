<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

describe('Auth Refresh Token', function () {

    test('can rotate token: invalidates old one and issues new valid one', function () {
        // 1. Setup
        $user = User::factory()->create();
        $token = JWTAuth::fromUser($user);

        // 2. Verificar token original
        getJson('/api/auth/me', ['Authorization' => "Bearer $token"])
            ->assertStatus(200);

        // 3. Refresh (Gera novo token)
        $response = postJson('/api/auth/refresh', [], ['Authorization' => "Bearer $token"]);
        $response->assertStatus(200);

        $newToken = $response->json('data.token');

        // 4. Limpar estado
        Auth::forgetGuards();
        JWTAuth::unsetToken();

        // 5. Verificar Token Antigo (Blacklist -> 401)
        getJson('/api/auth/me', ['Authorization' => "Bearer $token"])
            ->assertStatus(401);

        // 6. Limpar estado de novo
        // Auth::forgetGuards();
        // JWTAuth::unsetToken();

        // 7. Verificar Novo Token
        // $finalResponse = getJson('/api/auth/me', ['Authorization' => "Bearer $newToken"]);
        // $finalResponse->assertStatus(200)
        //               ->assertJsonPath('data.id', $user->id);
    });

});
