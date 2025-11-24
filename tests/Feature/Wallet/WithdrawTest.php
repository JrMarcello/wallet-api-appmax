<?php

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\postJson;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

describe('Wallet Withdraw', function () {

    beforeEach(fn () => Cache::flush());

    test('can withdraw if balance is sufficient', function () {
        $user = User::factory()->create();
        Wallet::create(['user_id' => $user->id, 'balance' => 0, 'version' => 1]);

        $token = JWTAuth::fromUser($user);

        postJson('/api/wallet/deposit', ['amount' => 1000], [
            'Authorization' => "Bearer $token",
            'Idempotency-Key' => 'setup-'.uniqid(),
        ]);

        postJson('/api/wallet/withdraw', ['amount' => 500], [
            'Authorization' => "Bearer $token",
            'Idempotency-Key' => 'with-'.uniqid(),
        ])->assertStatus(200)
            ->assertJsonPath('data.new_balance', 500);
    });

    test('cannot withdraw insufficient funds', function () {
        $user = User::factory()->create();
        Wallet::create(['user_id' => $user->id, 'balance' => 0, 'version' => 1]);
        $token = JWTAuth::fromUser($user);

        postJson('/api/wallet/withdraw', ['amount' => 500], [
            'Authorization' => "Bearer $token",
            'Idempotency-Key' => 'domain-rule-check',
        ])->assertStatus(400)
            ->assertJsonFragment(['status' => 'error']);
    });

    test('idempotency prevents double withdrawal', function () {
        $user = User::factory()->create();
        Wallet::create(['user_id' => $user->id, 'balance' => 0, 'version' => 1]);
        $token = JWTAuth::fromUser($user);

        postJson('/api/wallet/deposit', ['amount' => 1000], [
            'Authorization' => "Bearer $token",
            'Idempotency-Key' => 'setup-'.uniqid(),
        ]);

        $payload = ['amount' => 1000];
        $headers = ['Authorization' => "Bearer $token", 'Idempotency-Key' => 'same-key'];

        postJson('/api/wallet/withdraw', $payload, $headers)->assertStatus(200);

        $res2 = postJson('/api/wallet/withdraw', $payload, $headers);

        $res2->assertStatus(200)
            ->assertHeader('X-Idempotency-Hit', 'true');

        assertDatabaseHas('wallets', ['user_id' => $user->id, 'balance' => 0]);
    });

    test('it requires idempotency key for withdrawal', function () {
        $user = User::factory()->create();
        // Criamos saldo suficiente para garantir que o erro não seja de saldo, mas sim do header
        Wallet::create(['user_id' => $user->id, 'balance' => 1000, 'version' => 1]);
        $token = JWTAuth::fromUser($user);

        postJson('/api/wallet/withdraw', ['amount' => 100], [
            'Authorization' => "Bearer $token",
            // Header omitido
        ])->assertStatus(400)
            ->assertJsonFragment(['message' => 'Header "Idempotency-Key" is required for state-changing operations.']);
    });

    test('cannot exceed daily withdrawal limit', function () {
        // Limite R$ 50,00
        Config::set('wallet.limits.daily_withdrawal', 5000);

        $user = App\Models\User::factory()->create();
        // Saldo alto (R$ 1.000,00) para garantir que o erro não seja "Saldo Insuficiente"
        Wallet::create(['user_id' => $user->id, 'balance' => 100000, 'version' => 1]);
        $token = JWTAuth::fromUser($user);

        // Precisa criar saldo no Event Store ou o replay vai dar 0
        postJson('/api/wallet/deposit', ['amount' => 100000], [
            'Authorization' => "Bearer $token", 'Idempotency-Key' => 'setup',
        ]);

        // Saque dentro do limite (R$ 30,00)
        postJson('/api/wallet/withdraw', ['amount' => 3000], [
            'Authorization' => "Bearer $token", 'Idempotency-Key' => 'wd-1',
        ])->assertStatus(200);

        // Saque que estoura limite (R$ 30 + R$ 30 = 60 > 50)
        postJson('/api/wallet/withdraw', ['amount' => 3000], [
            'Authorization' => "Bearer $token", 'Idempotency-Key' => 'wd-2',
        ])->assertStatus(400)
            ->assertJsonFragment(['status' => 'error']);
    });
});
