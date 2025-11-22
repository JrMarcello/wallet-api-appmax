<?php

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\postJson;
use function Pest\Laravel\getJson;

// Garante que o banco limpa a cada teste
uses(RefreshDatabase::class);

describe('Wallet API Sad Paths & Validations', function () {

    // Cenário 1: Validação de Entrada (FormRequest)
    test('it rejects negative amounts via validation layer', function () {
        $user = User::factory()->create();
        // Criamos a wallet manualmente pois nosso factory de user não cria (decisão de design da fase 2)
        Wallet::create(['user_id' => $user->id, 'balance' => 0, 'version' => 1]);
        $token = auth('api')->login($user);

        // Tenta depositar valor negativo
        postJson('/api/wallet/deposit', ['amount' => -100], ['Authorization' => "Bearer $token"])
            ->assertStatus(422) // Unprocessable Entity (Padrão Laravel)
            ->assertJsonValidationErrors(['amount']);
            
        // Tenta sacar valor negativo
        postJson('/api/wallet/withdraw', ['amount' => -50], ['Authorization' => "Bearer $token"])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    });

    // Cenário 2: Validação de Integridade (Exists)
    test('it rejects transfers to non-existent users', function () {
        $user = User::factory()->create();
        Wallet::create(['user_id' => $user->id, 'balance' => 1000, 'version' => 1]);
        $token = auth('api')->login($user);

        postJson('/api/wallet/transfer', [
            'target_email' => 'fantasma@naoexiste.com',
            'amount' => 100
        ], ['Authorization' => "Bearer $token"])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['target_email']);
    });

    // Cenário 3: Regra de Domínio (Domain Exception -> HTTP 400)
    test('it rejects withdrawals exceeding current balance', function () {
        $user = User::factory()->create();
        // Começa com R$ 5,00
        Wallet::create(['user_id' => $user->id, 'balance' => 500, 'version' => 1]);
        $token = auth('api')->login($user);

        // Tenta sacar R$ 10,00
        // Como não estamos usando API de Depósito, o EventStore está vazio.
        // O Agregado vai calcular saldo 0 (ou ler do snapshot se implementamos otimização, mas a regra é clara)
        // Nesse caso, mesmo lendo do snapshot, 500 < 1000.
        
        // IMPORTANTE: Para esse teste funcionar com Event Sourcing puro, 
        // precisamos "hidratar" o saldo via evento primeiro, ou o saldo será 0 de qualquer jeito.
        // Vamos fazer um depósito legítimo primeiro.
        
        postJson('/api/wallet/deposit', ['amount' => 500], ['Authorization' => "Bearer $token"]);
        
        // Agora temos 500 de saldo real no histórico. Tentamos sacar 1000.
        $response = postJson('/api/wallet/withdraw', ['amount' => 1000], ['Authorization' => "Bearer $token"]);

        $response->assertStatus(400) // Bad Request (Definido no Controller catch block)
                 ->assertJsonFragment(['status' => 'error']);
    });

    // Cenário 4: Segurança (Middleware)
    test('it denies access to unauthenticated users', function () {
        postJson('/api/wallet/deposit', ['amount' => 100])
            ->assertStatus(401); // Unauthorized
            
        getJson('/api/wallet/balance')
            ->assertStatus(401);
    });
    
    // Cenário 5: Regra de Negócio (Transferência para si mesmo)
    test('it prevents transfer to self', function () {
        $user = User::factory()->create();
        Wallet::create(['user_id' => $user->id, 'balance' => 0, 'version' => 1]);
        $token = auth('api')->login($user);
        
        postJson('/api/wallet/transfer', [
            'target_email' => $user->email, // O próprio email
            'amount' => 100
        ], ['Authorization' => "Bearer $token"])
            ->assertStatus(400) // Bad Request (Lançado pelo Service)
            ->assertJsonFragment(['message' => 'Cannot transfer to self']);
    });

});
