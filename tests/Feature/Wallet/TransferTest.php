<?php

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\postJson;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

describe('Wallet Transfer', function () {

    beforeEach(fn () => Cache::flush());

    test('can transfer funds between users', function () {
        // Setup User A
        $userA = User::factory()->create(['email' => 'sender@test.com']);
        Wallet::create(['user_id' => $userA->id, 'balance' => 0, 'version' => 1]);
        $tokenA = JWTAuth::fromUser($userA);

        // Setup User B
        $userB = User::factory()->create(['email' => 'receiver@test.com']);
        Wallet::create(['user_id' => $userB->id, 'balance' => 0, 'version' => 1]);

        // Depósito em A
        postJson('/api/wallet/deposit', ['amount' => 1000], [
            'Authorization' => "Bearer $tokenA",
            'Idempotency-Key' => 'setup-'.uniqid(),
        ]);

        // Transferência
        postJson('/api/wallet/transfer', [
            'target_email' => 'receiver@test.com',
            'amount' => 400,
        ], [
            'Authorization' => "Bearer $tokenA",
            'Idempotency-Key' => 'trans-'.uniqid(),
        ])->assertStatus(200);

        assertDatabaseHas('wallets', ['user_id' => $userA->id, 'balance' => 600]);
        assertDatabaseHas('wallets', ['user_id' => $userB->id, 'balance' => 400]);
    });

    test('transfer does not consume withdrawal daily limit', function () {
        // Setup: Limite de Saque baixo (R$ 100,00)
        Config::set('wallet.limits.daily_withdrawal', 10000);

        $userA = User::factory()->create();
        Wallet::create(['user_id' => $userA->id, 'balance' => 0, 'version' => 1]);
        $tokenA = JWTAuth::fromUser($userA);

        $userB = User::factory()->create(); // Destino
        Wallet::create(['user_id' => $userB->id]);
        // Deposito inicial em A (R$ 500,00)
        postJson('/api/wallet/deposit', ['amount' => 50000], [
            'Authorization' => "Bearer $tokenA", 'Idempotency-Key' => 'setup',
        ]);

        // Transferir R$ 200,00 (Acima do limite de saque de 100)
        postJson('/api/wallet/transfer', [
            'target_email' => $userB->email,
            'amount' => 20000,
        ], [
            'Authorization' => "Bearer $tokenA", 'Idempotency-Key' => 'trans-1',
        ])->assertStatus(200);

        // Agora tentar sacar o limite total (R$ 100,00)
        postJson('/api/wallet/withdraw', ['amount' => 10000], [
            'Authorization' => "Bearer $tokenA", 'Idempotency-Key' => 'wd-check',
        ])->assertStatus(200);
    });

    test('cannot transfer to self', function () {
        $user = User::factory()->create();
        Wallet::create(['user_id' => $user->id, 'balance' => 0, 'version' => 1]);
        $token = JWTAuth::fromUser($user);

        postJson('/api/wallet/transfer', [
            'target_email' => $user->email,
            'amount' => 100,
        ], ['Authorization' => "Bearer $token"])->assertStatus(400);
    });

    test('cannot transfer to invalid email', function () {
        $user = User::factory()->create();
        Wallet::create(['user_id' => $user->id, 'balance' => 0, 'version' => 1]);
        $token = JWTAuth::fromUser($user);

        postJson('/api/wallet/transfer', [
            'target_email' => 'ghost@test.com',
            'amount' => 100,
        ], [
            'Authorization' => "Bearer $token",
            'Idempotency-Key' => 'error-test-key',
        ])->assertStatus(422);
    });

    test('transfer triggers webhook notification to target user url', function () {
        // Mock do HTTP
        Http::fake();

        // Setup A (Sender)
        $userA = User::factory()->create();
        Wallet::create(['user_id' => $userA->id, 'balance' => 0, 'version' => 1]);
        $tokenA = JWTAuth::fromUser($userA);

        // Setup B (Receiver) com Webhook Configurado
        $targetUrl = 'https://loja-do-b.com/notify';
        $userB = User::factory()->create(['email' => 'receiver-web@test.com', 'webhook_url' => $targetUrl]);
        Wallet::create(['user_id' => $userB->id, 'balance' => 0, 'version' => 1]);

        // Depósito inicial em A
        postJson('/api/wallet/deposit', ['amount' => 1000], [
            'Authorization' => "Bearer $tokenA",
            'Idempotency-Key' => 'setup-'.uniqid(),
        ]);

        // Transferir A -> B
        postJson('/api/wallet/transfer', [
            'target_email' => $userB->email,
            'amount' => 500,
        ], [
            'Authorization' => "Bearer $tokenA",
            'Idempotency-Key' => 'trans-webhook-'.uniqid(),
        ])->assertStatus(200);

        // Verifica se o sistema tentou chamar a URL do User B
        // Nota: Em testes, o Queue driver padrão é 'sync', então o job roda na hora.
        Http::assertSent(function (Request $request) use ($targetUrl) {
            return $request->url() == $targetUrl &&
                   $request['event'] == 'transfer_received' &&
                   $request['amount'] == 500;
        });
    });

    test('it requires idempotency key for transfer', function () {
        // Sender
        $user = User::factory()->create();
        Wallet::create(['user_id' => $user->id, 'balance' => 1000, 'version' => 1]);
        $token = JWTAuth::fromUser($user);

        // Receiver
        $target = User::factory()->create(['email' => 'receiver-check@test.com']);

        postJson('/api/wallet/transfer', [
            'amount' => 100,
            'target_email' => $target->email,
        ], [
            'Authorization' => "Bearer $token",
            // Header omitido
        ])->assertStatus(400)
            ->assertJsonFragment(['message' => 'Header "Idempotency-Key" is required for state-changing operations.']);
    });
});
