<?php

namespace App\Domain\Wallet\Events;

use DateTimeImmutable;

readonly class TransferReceived
{
    public function __construct(
        public string $walletId,
        public string $sourceWalletId,
        public int $amount,
        public DateTimeImmutable $occurredAt
    ) {}
}
