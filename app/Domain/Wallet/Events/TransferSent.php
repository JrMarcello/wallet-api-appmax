<?php

namespace App\Domain\Wallet\Events;

use DateTimeImmutable;

readonly class TransferSent
{
    public function __construct(
        public string $walletId,
        public string $targetWalletId,
        public int $amount,
        public DateTimeImmutable $occurredAt
    ) {}
}
