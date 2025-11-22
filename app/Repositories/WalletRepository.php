<?php

namespace App\Repositories;

use App\Domain\Wallet\Events\FundsDeposited;
use App\Domain\Wallet\Events\FundsWithdrawn;
use App\Domain\Wallet\Events\TransferReceived;
use App\Domain\Wallet\Events\TransferSent;
use App\Models\StoredEvent;
use App\Models\Wallet;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Collection;

class WalletRepository
{
    /**
     * Carrega o histórico de eventos convertendo JSON -> Objetos de Domínio
     */
    public function getHistory(string $walletId): array
    {
        return StoredEvent::where('aggregate_id', $walletId)
            ->orderBy('occurred_at') // Importante: Ordem cronológica
            ->orderBy('id')          // Desempate por inserção (ULID)
            ->get()
            ->map(function ($row) {
                return $this->mapToDomainEvent($row);
            })
            ->toArray();
    }

    /**
     * Persiste um novo evento na tabela de Write Model
     */
    public function append(object $event): void
    {
        StoredEvent::create([
            'aggregate_id' => $event->walletId,
            'event_class'  => get_class($event),
            'payload'      => $this->extractPayload($event),
            'occurred_at'  => $event->occurredAt,
        ]);
    }

    /**
     * Atualiza a Projeção (Read Model)
     * Isso mantém a tabela 'wallets' sincronizada na hora.
     */
    public function updateProjection(string $walletId, int $newBalance): void
    {
        Wallet::where('id', $walletId)->update([
            'balance' => $newBalance,
            // 'version' => DB::raw('version + 1') // Optimistic Lock se necessário no futuro
        ]);
    }

    // --- Helpers de Mapeamento ---

    private function extractPayload(object $event): array
    {
        return get_object_vars($event);
    }

    private function mapToDomainEvent(StoredEvent $row): object
    {
        $class = $row->event_class;
        $payload = $row->payload;
        
        // Reconstrói o objeto baseado na classe salva
        // Nota: Em prod, usaríamos um Serializer mais robusto, mas aqui resolve.
        
        return match ($class) {
            FundsDeposited::class => new FundsDeposited(
                $payload['walletId'],
                $payload['amount'],
                new DateTimeImmutable($row->occurred_at)
            ),
            FundsWithdrawn::class => new FundsWithdrawn(
                $payload['walletId'],
                $payload['amount'],
                new DateTimeImmutable($row->occurred_at)
            ),
            TransferSent::class => new TransferSent(
                $payload['walletId'],
                $payload['targetWalletId'],
                $payload['amount'],
                new DateTimeImmutable($row->occurred_at)
            ),
            TransferReceived::class => new TransferReceived(
                $payload['walletId'],
                $payload['sourceWalletId'],
                $payload['amount'],
                new DateTimeImmutable($row->occurred_at)
            ),
            default => throw new \Exception("Evento desconhecido: $class")
        };
    }
}
