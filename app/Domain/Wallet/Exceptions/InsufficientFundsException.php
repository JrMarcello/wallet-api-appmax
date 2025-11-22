<?php

namespace App\Domain\Wallet\Exceptions;

use Exception;

class InsufficientFundsException extends Exception
{
    protected $message = "Saldo insuficiente para realizar a operação.";
}
