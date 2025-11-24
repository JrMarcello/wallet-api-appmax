<?php

namespace App\Repositories;

use App\Infrastructure\Serializer\EventSerializer;
use App\Models\StoredEvent;
use App\Models\Wallet;
use DateTimeImmutable;

class WalletRepository
{
    public function __construct(
        protected EventSerializer $serializer
    ) {}

    public function getHistory(string $walletId): array
    {
        return StoredEvent::where('aggregate_id', $walletId)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get()
            ->map(function ($row) {
                $payload = $row->payload;
                // Fallback para data do banco
                $payload['occurredAt'] = $row->occurred_at->format(DateTimeImmutable::ATOM);

                return $this->serializer->deserialize($row->event_class, $payload);
            })
            ->toArray();
    }

    public function append(object $event): void
    {
        $payload = $this->serializer->serialize($event);

        StoredEvent::create([
            'aggregate_id' => $event->walletId,
            'event_class' => get_class($event),
            'payload' => $payload,
            'occurred_at' => $event->occurredAt,
        ]);
    }

    public function updateProjection(string $walletId, int $newBalance): void
    {
        Wallet::where('id', $walletId)->update(['balance' => $newBalance]);
    }

    /**
     * Calcula volume de SAÍDA (Withdraw) hoje.
     */
    public function getDailyOutgoingVolume(string $walletId): int
    {
        $outgoingEvents = [
            \App\Domain\Wallet\Events\FundsWithdrawn::class,
            // \App\Domain\Wallet\Events\TransferSent::class,
        ];

        return $this->sumDailyVolume($walletId, $outgoingEvents);
    }

    /**
     * Calcula volume de ENTRADA (Deposit) hoje.
     */
    public function getDailyIncomingVolume(string $walletId): int
    {
        $incomingEvents = [
            \App\Domain\Wallet\Events\FundsDeposited::class,
            // \App\Domain\Wallet\Events\TransferSent::class,
        ];

        return $this->sumDailyVolume($walletId, $incomingEvents);
    }

    /**
     * Helper privado para query de soma
     */
    private function sumDailyVolume(string $walletId, array $eventClasses): int
    {
        return (int) StoredEvent::where('aggregate_id', $walletId)
            ->whereIn('event_class', $eventClasses)
            ->whereDate('occurred_at', now())
            ->sum('payload->amount');
    }
}
