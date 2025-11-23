<?php

use function Pest\Laravel\postJson;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

describe('Auth Registration', function () {

    test('can register a new user', function () {
        $response = postJson('/api/auth/register', [
            'name' => 'New User',
            'email' => 'new@test.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['token', 'user']]);
    });

    test('cannot register with existing email', function () {
        // Cria o primeiro
        postJson('/api/auth/register', [
            'name' => 'User A', 'email' => 'duplicate@test.com', 'password' => '12345678',
        ]);

        // Tenta o segundo igual
        postJson('/api/auth/register', [
            'name' => 'User B', 'email' => 'duplicate@test.com', 'password' => '12345678',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    });
});
