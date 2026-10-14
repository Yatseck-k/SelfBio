<?php

declare(strict_types=1);

namespace Whodoku\Application\Payment;

use RuntimeException;

class PurchaseRefused extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}
