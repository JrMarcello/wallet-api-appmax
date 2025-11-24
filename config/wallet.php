<?php

return [
    'limits' => [
        // Padrão: R$ 10.000,00
        'daily_deposit' => env('WALLET_LIMIT_DAILY_DEPOSIT', 1000000),

        // Padrão: R$ 2.000,00
        'daily_withdrawal' => env('WALLET_LIMIT_DAILY_WITHDRAWAL', 200000),
    ],
];
