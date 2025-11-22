<?php

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\Cache;
use function Pest\Laravel\postJson;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

describe('Wallet Transfer', function () {

    beforeEach(fn() => Cache::flush());

    test('can transfer funds between users', function () {
        // Setup User A
        $userA = User::factory()->create(['email' => 'sender@test.com']);
        Wallet::create(['user_id' => $userA->id, 'balance' => 0, 'version' => 1]);
        $tokenA = auth('api')->login($userA);

        // Setup User B
        $userB = User::factory()->create(['email' => 'receiver@test.com']);
        Wallet::create(['user_id' => $userB->id, 'balance' => 0, 'version' => 1]);

        // Depósito em A
        postJson('/api/wallet/deposit', ['amount' => 1000], [
            'Authorization' => "Bearer $tokenA",
            'Idempotency-Key' => 'setup-' . uniqid()
        ]);

        // Transferência
        postJson('/api/wallet/transfer', [
            'target_email' => 'receiver@test.com',
            'amount' => 400
        ], [
            'Authorization' => "Bearer $tokenA",
            'Idempotency-Key' => 'trans-' . uniqid()
        ])->assertStatus(200);

        $this->assertDatabaseHas('wallets', ['user_id' => $userA->id, 'balance' => 600]);
        $this->assertDatabaseHas('wallets', ['user_id' => $userB->id, 'balance' => 400]);
    });

    test('cannot transfer to self', function () {
        $user = User::factory()->create();
        Wallet::create(['user_id' => $user->id, 'balance' => 0, 'version' => 1]);
        $token = auth('api')->login($user);

        postJson('/api/wallet/transfer', [
            'target_email' => $user->email,
            'amount' => 100
        ], ['Authorization' => "Bearer $token"])->assertStatus(400);
    });

    test('cannot transfer to invalid email', function () {
        $user = User::factory()->create();
        Wallet::create(['user_id' => $user->id, 'balance' => 0, 'version' => 1]);
        $token = auth('api')->login($user);

        postJson('/api/wallet/transfer', [
            'target_email' => 'ghost@test.com',
            'amount' => 100
        ], ['Authorization' => "Bearer $token"])->assertStatus(422);
    });
});
