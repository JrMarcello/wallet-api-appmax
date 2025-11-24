<?php

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\Config;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

use function Pest\Laravel\postJson;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

describe('Wallet Deposit', function () {

    test('can deposit positive amount', function () {
        $user = User::factory()->create();
        Wallet::create(['user_id' => $user->id, 'balance' => 0, 'version' => 1]);
        $token = JWTAuth::fromUser($user);

        postJson('/api/wallet/deposit', ['amount' => 1000], [
            'Authorization' => "Bearer $token",
            'Idempotency-Key' => 'dep-'.uniqid(),
        ])->assertStatus(200)
            ->assertJsonPath('data.new_balance', 1000);
    });

    test('cannot deposit negative amount', function () {
        $user = User::factory()->create();
        Wallet::create(['user_id' => $user->id, 'balance' => 0, 'version' => 1]);
        $token = JWTAuth::fromUser($user);

        postJson('/api/wallet/deposit', ['amount' => -100], [
            'Authorization' => "Bearer $token",
            'Idempotency-Key' => 'error-test-key',
        ])
            ->assertStatus(422);
    });

    test('it requires idempotency key for write operations', function () {
        $user = User::factory()->create();
        Wallet::create(['user_id' => $user->id]);
        $token = JWTAuth::fromUser($user);

        // Tenta depositar SEM o header
        postJson('/api/wallet/deposit', ['amount' => 100], [
            'Authorization' => "Bearer $token",
            // 'Idempotency-Key' => omitido propositalmente
        ])->assertStatus(400) // Bad Request
            ->assertJsonFragment(['message' => 'Header "Idempotency-Key" is required for state-changing operations.']);
    });

    test('cannot exceed daily deposit limit', function () {
        // Limite baixo para teste (R$ 100,00)
        Config::set('wallet.limits.daily_deposit', 10000);

        $user = User::factory()->create();
        Wallet::create(['user_id' => $user->id, 'balance' => 0, 'version' => 1]);
        $token = JWTAuth::fromUser($user);

        // Depósito parcial (R$ 60,00) dentro do limite
        postJson('/api/wallet/deposit', ['amount' => 6000], [
            'Authorization' => "Bearer $token",
            'Idempotency-Key' => 'dep-1',
        ])->assertStatus(200);

        // Depósito que estoura o acumulado (R$ 60,00 + R$ 50,00 = R$ 110,00 > Limit)
        postJson('/api/wallet/deposit', ['amount' => 5000], [
            'Authorization' => "Bearer $token",
            'Idempotency-Key' => 'dep-2',
        ])->assertStatus(400)
            ->assertJsonFragment(['status' => 'error']);
    });
});
