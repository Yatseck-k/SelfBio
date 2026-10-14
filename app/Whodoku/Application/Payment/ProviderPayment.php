<?php

declare(strict_types=1);

namespace Whodoku\Application\Payment;

/** What the provider says about a payment (always fetched from the provider, never taken from a request). */
final readonly class ProviderPayment
{
    public function __construct(
        public string $id,
        /** pending|waiting_for_capture|succeeded|canceled */
        public string $status,
        public int $amountKopecks,
        public string $currency,
        public ?string $orderId,
        public int $refundedKopecks = 0,
    ) {}

    public function isSucceeded(): bool
    {
        return $this->status === 'succeeded';
    }
}
