<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\postJson;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

describe('Auth Login', function () {

    test('existing user can login with correct credentials', function () {
        // 1. Criar usuário com senha conhecida
        $user = User::factory()->create([
            'email' => 'login@test.com',
            'password' => Hash::make('password123'),
        ]);

        // 2. Tentar login
        $response = postJson('/api/auth/login', [
            'email' => 'login@test.com',
            'password' => 'password123',
        ]);

        // 3. Validar
        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => ['token', 'type', 'expires_in'],
            ]);
    });

    test('user cannot login with incorrect password', function () {
        User::factory()->create([
            'email' => 'wrong@test.com',
            'password' => Hash::make('password123'),
        ]);

        postJson('/api/auth/login', [
            'email' => 'wrong@test.com',
            'password' => 'wrong-password',
        ])->assertStatus(401)
            ->assertJsonFragment(['message' => 'Unauthorized']);
    });

    test('user cannot login with non-existent email', function () {
        postJson('/api/auth/login', [
            'email' => 'ghost@test.com',
            'password' => 'password123',
        ])->assertStatus(401);
    });

    test('login requires email and password', function () {
        postJson('/api/auth/login', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    });

});
