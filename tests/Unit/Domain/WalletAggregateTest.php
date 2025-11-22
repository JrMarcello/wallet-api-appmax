<?php

use App\Domain\Wallet\Events\FundsDeposited;
use App\Domain\Wallet\Events\FundsWithdrawn;
use App\Domain\Wallet\Events\TransferReceived;
use App\Domain\Wallet\Events\TransferSent;
use App\Domain\Wallet\Exceptions\InsufficientFundsException;
use App\Domain\Wallet\WalletAggregate;

// UUID fake
$uuid = '01HQ...FAKE...UUID';

describe('Wallet Aggregate Core Logic', function () use ($uuid) {

    test('it starts with zero balance', function () use ($uuid) {
        // Cenário: Carteira nova, sem histórico
        $wallet = WalletAggregate::retrieve($uuid, []);
        
        expect($wallet->getBalance())->toBe(0);
    });

    test('it can deposit funds', function () use ($uuid) {
        $wallet = WalletAggregate::retrieve($uuid, []);
        
        $event = $wallet->deposit(1000);

        expect($event)
            ->toBeInstanceOf(FundsDeposited::class)
            ->and($event->amount)->toBe(1000)
            ->and($event->walletId)->toBe($uuid);
        
        expect($wallet->getBalance())->toBe(1000); 
    });

    test('it can withdraw funds if balance is sufficient', function () use ($uuid) {
        // Cenário: Já existe um depósito de 1000 no passado
        $pastEvents = [
            new FundsDeposited($uuid, 1000, new DateTimeImmutable())
        ];
        
        $wallet = WalletAggregate::retrieve($uuid, $pastEvents);
        
        // Ação: Sacar 500
        $event = $wallet->withdraw(500);

        expect($event)->toBeInstanceOf(FundsWithdrawn::class)
            ->and($event->amount)->toBe(500);
    });

    test('it prevents withdrawal with insufficient funds', function () use ($uuid) {
        // Cenário: Saldo 0
        $wallet = WalletAggregate::retrieve($uuid, []);

        $wallet->withdraw(100);
    })->throws(InsufficientFundsException::class);

    test('it reconstructs balance correctly from history stream', function () use ($uuid) {
        // Cenário Complexo: Depósito -> Saque -> Transferência Recebida -> Transferência Enviada
        $history = [
            new FundsDeposited($uuid, 5000, new DateTimeImmutable()), // +5000
            new FundsWithdrawn($uuid, 2000, new DateTimeImmutable()), // -2000 (Saldo 3000)
            new TransferReceived($uuid, 'other-id', 1000, new DateTimeImmutable()), // +1000 (Saldo 4000)
            new TransferSent($uuid, 'target-id', 500, new DateTimeImmutable()), // -500 (Saldo 3500)
        ];

        $wallet = WalletAggregate::retrieve($uuid, $history);

        expect($wallet->getBalance())->toBe(3500);
    });

    test('it prevents negative amounts', function () use ($uuid) {
        $wallet = WalletAggregate::retrieve($uuid, []);

        expect(fn() => $wallet->deposit(-100))
            ->toThrow(InvalidArgumentException::class);

        expect(fn() => $wallet->withdraw(-100))
            ->toThrow(InvalidArgumentException::class);
    });

});
