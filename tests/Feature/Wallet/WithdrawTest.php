<?php

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\Cache;
use function Pest\Laravel\postJson;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

describe('Wallet Withdraw', function () {
    
    beforeEach(fn() => Cache::flush()); // Importante para idempotência

    test('can withdraw if balance is sufficient', function () {
        $user = User::factory()->create();
        Wallet::create(['user_id' => $user->id, 'balance' => 0, 'version' => 1]);
        $token = auth('api')->login($user);

        // Setup Saldo (via API para gerar evento)
        postJson('/api/wallet/deposit', ['amount' => 1000], [
            'Authorization' => "Bearer $token",
            'Idempotency-Key' => 'setup-' . uniqid()
        ]);

        postJson('/api/wallet/withdraw', ['amount' => 500], [
            'Authorization' => "Bearer $token",
            'Idempotency-Key' => 'with-' . uniqid()
        ])->assertStatus(200)
          ->assertJsonPath('data.new_balance', 500);
    });

    test('cannot withdraw insufficient funds', function () {
        $user = User::factory()->create();
        Wallet::create(['user_id' => $user->id, 'balance' => 0, 'version' => 1]);
        $token = auth('api')->login($user);

        // Tenta sacar sem ter depositado
        postJson('/api/wallet/withdraw', ['amount' => 500], ['Authorization' => "Bearer $token"])
            ->assertStatus(400)
            ->assertJsonFragment(['status' => 'error']);
    });

    test('idempotency prevents double withdrawal', function () {
        $user = User::factory()->create();
        Wallet::create(['user_id' => $user->id, 'balance' => 0, 'version' => 1]);
        $token = auth('api')->login($user);

        // Deposito 1000
        postJson('/api/wallet/deposit', ['amount' => 1000], [
            'Authorization' => "Bearer $token", 
            'Idempotency-Key' => 'setup-' . uniqid()
        ]);

        $payload = ['amount' => 1000];
        $headers = ['Authorization' => "Bearer $token", 'Idempotency-Key' => 'same-key'];

        // Request 1: Saca tudo (1000)
        postJson('/api/wallet/withdraw', $payload, $headers)->assertStatus(200);

        // Request 2: Replay. Se falhar idempotência, tentaria sacar +1000 e daria Erro 400 (Saldo insuficiente).
        // Se funcionar, retorna 200 (Cache) e saldo fica 0.
        $res2 = postJson('/api/wallet/withdraw', $payload, $headers);
        
        $res2->assertStatus(200)
             ->assertHeader('X-Idempotency-Hit', 'true');
             
        $this->assertDatabaseHas('wallets', ['user_id' => $user->id, 'balance' => 0]);
    });
});
