<?php

declare(strict_types=1);

namespace Whodoku\Application\Payment;

/** A payment the provider has created: its id and the page where the player pays. */
final readonly class PaymentLink
{
    public function __construct(public string $paymentId, public string $url) {}
}
