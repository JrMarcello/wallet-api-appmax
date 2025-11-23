<?php

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\Cache;
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
        ], ['Authorization' => "Bearer $token"])->assertStatus(422);
    });

    test('transfer triggers webhook notification to target user url', function () {
        // 1. Mock do HTTP (Impede requisição real e permite verificação)
        Illuminate\Support\Facades\Http::fake();

        // 2. Setup A (Sender)
        $userA = User::factory()->create();
        Wallet::create(['user_id' => $userA->id, 'balance' => 0, 'version' => 1]);
        $tokenA = JWTAuth::fromUser($userA);

        // 3. Setup B (Receiver) com Webhook Configurado
        $targetUrl = 'https://loja-do-b.com/notify';
        $userB = User::factory()->create(['email' => 'receiver-web@test.com', 'webhook_url' => $targetUrl]);
        Wallet::create(['user_id' => $userB->id, 'balance' => 0, 'version' => 1]);

        // 4. Depósito inicial em A
        postJson('/api/wallet/deposit', ['amount' => 1000], [
            'Authorization' => "Bearer $tokenA",
            'Idempotency-Key' => 'setup-'.uniqid(),
        ]);

        // 5. Transferir A -> B
        postJson('/api/wallet/transfer', [
            'target_email' => $userB->email,
            'amount' => 500,
        ], [
            'Authorization' => "Bearer $tokenA",
            'Idempotency-Key' => 'trans-webhook-'.uniqid(),
        ])->assertStatus(200);

        // 6. Asserção: Verifique se o sistema tentou chamar a URL do User B
        // Nota: Em testes, o Queue driver padrão é 'sync', então o job roda na hora.
        Http::assertSent(function (\Illuminate\Http\Client\Request $request) use ($targetUrl) {
            return $request->url() == $targetUrl &&
                   $request['event'] == 'transfer_received' &&
                   $request['amount'] == 500;
        });
    });
});
