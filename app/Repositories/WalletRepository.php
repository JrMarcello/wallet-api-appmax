<?php

namespace App\Repositories;

use App\Infrastructure\Serializer\EventSerializer;
use App\Models\StoredEvent;
use App\Models\Wallet;

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
                // Garante que a data usada seja a do banco (Source of Truth)
                $payload['occurredAt'] = $row->occurred_at->format(\DateTimeImmutable::ATOM);

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
}
