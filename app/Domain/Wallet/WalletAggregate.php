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
     * Reconstrói o estado atual ("Replay") baseando-se no histórico.
     *
     * @param string $id
     * @param array $historyEvents Lista de objetos de evento (DTOs)
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
     * APLICA o evento no estado interno (Mutação matemática)
     */
    private function apply(object $event): void
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
    }

    /**
     * --- ACTIONS (Comportamentos de Escrita) ---
     */

    public function deposit(int $amount): FundsDeposited
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException("O valor do depósito deve ser positivo.");
        }

        // Regra: Depósito sempre aceito (sem limite no case básico)
        $event = new FundsDeposited(
            $this->id,
            $amount,
            new DateTimeImmutable()
        );

        // Opcional: Já aplica no state atual caso a gente fosse continuar usando a instância
        $this->apply($event);

        return $event;
    }

    public function withdraw(int $amount): FundsWithdrawn
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException("O valor do saque deve ser positivo.");
        }

        if ($this->balance < $amount) {
            throw new InsufficientFundsException();
        }

        $event = new FundsWithdrawn(
            $this->id,
            $amount,
            new DateTimeImmutable()
        );

        $this->apply($event);
        return $event;
    }

    public function sendTransfer(string $targetWalletId, int $amount): TransferSent
    {
        if ($amount <= 0) throw new InvalidArgumentException("Valor deve ser positivo.");
        if ($this->balance < $amount) throw new InsufficientFundsException();

        $event = new TransferSent(
            $this->id,
            $targetWalletId,
            $amount,
            new DateTimeImmutable()
        );

        $this->apply($event);
        return $event;
    }

    public function receiveTransfer(string $sourceWalletId, int $amount): TransferReceived
    {
        // Recebimento passivo. Não valida saldo, apenas aceita.
        $event = new TransferReceived(
            $this->id,
            $sourceWalletId,
            $amount,
            new DateTimeImmutable()
        );
        $this->apply($event);
        return $event;
    }

    public function getBalance(): int
    {
        return $this->balance;
    }
}
