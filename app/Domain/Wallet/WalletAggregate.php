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
     * Aplica um evento ao estado atual da carteira.
     *
     * Usa convenção de nomenclatura: apply{NomeDoEvento}
     */
    private function apply(object $event): object
    {
        $eventClass = (new \ReflectionClass($event))->getShortName();
        $method = 'apply'.$eventClass;

        if (method_exists($this, $method)) {
            $this->$method($event);
        }

        return $event;
    }

    /**
     * Handler: Aplica depósito de fundos.
     */
    private function applyFundsDeposited(FundsDeposited $event): void
    {
        $this->balance += $event->amount;
    }

    /**
     * Handler: Aplica recebimento de transferência.
     */
    private function applyTransferReceived(TransferReceived $event): void
    {
        $this->balance += $event->amount;
    }

    /**
     * Handler: Aplica saque de fundos.
     */
    private function applyFundsWithdrawn(FundsWithdrawn $event): void
    {
        $this->balance -= $event->amount;
    }

    /**
     * Handler: Aplica envio de transferência.
     */
    private function applyTransferSent(TransferSent $event): void
    {
        $this->balance -= $event->amount;
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
     * Executa um depósito na carteira.
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

    /**
     * Executa um saque na carteira.
     */
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

    /**
     * Executa uma transferência para outra carteira.
     */
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

    /**
     * Executa o recebimento de uma transferência de outra carteira.
     */
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

    /**
     * Obtém o saldo atual da carteira.
     */
    public function getBalance(): int
    {
        return $this->balance;
    }
}
