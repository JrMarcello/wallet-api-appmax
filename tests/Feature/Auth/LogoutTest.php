<?php

use App\Models\User;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Auth;
use function Pest\Laravel\postJson;
use function Pest\Laravel\getJson;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

describe('Auth Logout', function () {

    test('authenticated user can logout', function () {
        $user = User::factory()->create();
        $token = JWTAuth::fromUser($user);

        // 1. Logout
        postJson('/api/auth/logout', [], ['Authorization' => "Bearer $token"])
            ->assertStatus(200)
            ->assertJsonFragment(['message' => 'Successfully logged out']);

        // 2. Limpar estado local do teste
        Auth::forgetGuards();
        JWTAuth::unsetToken();

        // 3. Tentar acessar rota protegida com o mesmo token (Deve falhar)
        getJson('/api/auth/me', ['Authorization' => "Bearer $token"])
            ->assertStatus(401); // Token está na blacklist
    });

    test('unauthenticated user cannot logout', function () {
        // Tenta logout sem token
        postJson('/api/auth/logout')
            ->assertStatus(401);
    });

});
