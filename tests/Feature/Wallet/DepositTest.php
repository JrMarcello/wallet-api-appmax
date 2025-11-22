<?php

use App\Models\User;
use App\Models\Wallet;
use function Pest\Laravel\postJson;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

describe('Wallet Deposit', function () {

    test('can deposit positive amount', function () {
        $user = User::factory()->create();
        Wallet::create(['user_id' => $user->id, 'balance' => 0, 'version' => 1]);
        $token = auth('api')->login($user);

        postJson('/api/wallet/deposit', ['amount' => 1000], [
            'Authorization' => "Bearer $token",
            'Idempotency-Key' => 'dep-' . uniqid()
        ])->assertStatus(200)
          ->assertJsonPath('data.new_balance', 1000);
    });

    test('cannot deposit negative amount', function () {
        $user = User::factory()->create();
        Wallet::create(['user_id' => $user->id, 'balance' => 0, 'version' => 1]);
        $token = auth('api')->login($user);

        postJson('/api/wallet/deposit', ['amount' => -100], ['Authorization' => "Bearer $token"])
            ->assertStatus(422);
    });
});
