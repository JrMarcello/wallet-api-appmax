<?php

namespace App\Domain\Wallet\Events;

use DateTimeImmutable;

readonly class FundsDeposited
{
    public function __construct(
        public string $walletId,
        public int $amount,
        public DateTimeImmutable $occurredAt
    ) {}
}
