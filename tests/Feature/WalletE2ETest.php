<?php

use App\Models\User;
use App\Models\Wallet; // Importar Wallet
use Illuminate\Support\Facades\Cache;
use function Pest\Laravel\postJson;
use function Pest\Laravel\getJson;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

describe('Wallet API E2E Flow', function () {

    beforeEach(function () {
        // Garante limpeza do cache (seja Array ou Redis)
        Cache::flush();
    });

    test('full transaction cycle: register -> deposit -> transfer', function () {
        // 1. Criar Usuário A
        $registerA = postJson('/api/auth/register', [
            'name' => 'User A', 'email' => 'a@test.com', 'password' => '12345678'
        ]);
        $registerA->assertStatus(201);
        $tokenA = $registerA->json('data.token');

        // 2. Criar Usuário B
        $registerB = postJson('/api/auth/register', [
            'name' => 'User B', 'email' => 'b@test.com', 'password' => '12345678'
        ]);
        $registerB->assertStatus(201);

        // Limpar estado de Auth para não vazar o User B para o próximo request
        auth('api')->logout();
        
        // 3. Depósito (Usando chave aleatória para evitar colisão de teste anterior)
        $depKey = 'dep-' . uniqid();
        $deposit = postJson('/api/wallet/deposit', [
            'amount' => 10000
        ], [
            'Authorization' => "Bearer $tokenA",
            'Idempotency-Key' => $depKey
        ]);
        
        $deposit->assertStatus(200)
                ->assertJsonPath('data.new_balance', 10000);

        // 4. Transferência A -> B
        $transKey = 'trans-' . uniqid();
        $transfer = postJson('/api/wallet/transfer', [
            'target_email' => 'b@test.com',
            'amount' => 4000
        ], [
            'Authorization' => "Bearer $tokenA",
            'Idempotency-Key' => $transKey
        ]);

        // Debug: Se falhar, mostra o erro no terminal
        if ($transfer->status() !== 200) {
            dump($transfer->json());
        }

        $transfer->assertStatus(200);

        // 5. Verificar Saldo
        $balanceA = getJson('/api/wallet/balance', [
            'Authorization' => "Bearer $tokenA"
        ]);

        $balanceA->assertStatus(200)
                 ->assertJsonPath('data.balance', 6000);
    });

    test('idempotency prevents double charge', function () {
        $user = User::factory()->create();
        
        // CORREÇÃO: Criar a Wallet que o Factory não cria
        Wallet::create([
            'user_id' => $user->id,
            'balance' => 0,
            'version' => 1
        ]);

        $token = auth('api')->login($user);
        
        // Depositar saldo inicial
        postJson('/api/wallet/deposit', ['amount' => 1000], [
            'Authorization' => "Bearer $token",
            'Idempotency-Key' => 'setup-' . uniqid()
        ])->assertStatus(200);
        
        $payload = ['amount' => 500];
        $headers = [
            'Authorization' => "Bearer $token",
            'Idempotency-Key' => 'withdraw-' . uniqid() // Chave única
        ];

        // Request 1
        $res1 = postJson('/api/wallet/withdraw', $payload, $headers);
        $res1->assertStatus(200);
        
        // Request 2 (Replay)
        $res2 = postJson('/api/wallet/withdraw', $payload, $headers);
        $res2->assertStatus(200);
        $res2->assertHeader('X-Idempotency-Hit', 'true');

        // Saldo deve ter descido apenas 500
        $this->assertDatabaseHas('wallets', [
            'user_id' => $user->id,
            'balance' => 500 
        ]);
    });

});
