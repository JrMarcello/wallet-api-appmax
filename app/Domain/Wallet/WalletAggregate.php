<?php

namespace App\Domain\Wallet;

use App\Domain\Wallet\Events\FundsDeposited;
use App\Domain\Wallet\Events\FundsWithdrawn;
use App\Domain\Wallet\Events\TransferReceived;
use App\Domain\Wallet\Events\TransferSent;
use App\Domain\Wallet\Exceptions\InsufficientFundsException;
use DateTimeImmutable;
use InvalidArgumentException;

class WalletAggregate
{
    private int $balance = 0;

    private string $id;

    private function __construct(string $id)
    {
        $this->id = $id;
    }

    /**
     * APLICA o evento no estado interno (Mutação matemática)
     */
    private function apply(object $event): object
    {
        if ($event instanceof FundsDeposited) {
            $this->balance += $event->amount;
        }

        if ($event instanceof TransferReceived) {
            $this->balance += $event->amount;
        }

        if ($event instanceof FundsWithdrawn) {
            $this->balance -= $event->amount;
        }

        if ($event instanceof TransferSent) {
            $this->balance -= $event->amount;
        }

        return $event;
    }

    /**
     * Reconstrói o estado atual ("Replay") baseando-se no histórico.
     *
     * @param  array  $historyEvents  Lista de objetos de evento (DTOs)
     */
    public static function retrieve(string $id, array $historyEvents): self
    {
        $aggregate = new self($id);

        foreach ($historyEvents as $event) {
            $aggregate->apply($event);
        }

        return $aggregate;
    }

    /**
     * --- ACTIONS (Comportamentos de Escrita) ---
     */
    public function deposit(int $amount): FundsDeposited
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('O valor do depósito deve ser positivo.');
        }

        return $this->apply(
            new FundsDeposited(
                $this->id,
                $amount,
                new DateTimeImmutable
            )
        );

    }

    public function withdraw(int $amount): FundsWithdrawn
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('O valor do saque deve ser positivo.');
        }

        if ($this->balance < $amount) {
            throw new InsufficientFundsException;
        }

        return $this->apply(
            new FundsWithdrawn(
                $this->id,
                $amount,
                new DateTimeImmutable
            )
        );
    }

    public function sendTransfer(string $targetWalletId, int $amount): TransferSent
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Valor deve ser positivo.');
        }
        if ($this->balance < $amount) {
            throw new InsufficientFundsException;
        }

        return $this->apply(
            new TransferSent(
                $this->id,
                $targetWalletId,
                $amount,
                new DateTimeImmutable
            )
        );
    }

    public function receiveTransfer(string $sourceWalletId, int $amount): TransferReceived
    {
        return $this->apply(
            new TransferReceived(
                $this->id,
                $sourceWalletId,
                $amount,
                new DateTimeImmutable
            )
        );
    }

    public function getBalance(): int
    {
        return $this->balance;
    }
}
