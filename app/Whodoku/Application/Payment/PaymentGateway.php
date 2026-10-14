<?php

declare(strict_types=1);

namespace Whodoku\Application\Payment;

use Whodoku\Models\Order;

interface PaymentGateway
{
    public function name(): string;

    /** Creates the payment of an order; the same idempotence key never creates a second one. */
    public function createPayment(Order $order, string $description, string $returnUrl): PaymentLink;

    public function fetchPayment(string $paymentId): ProviderPayment;

    /** Returns the whole sum of a paid order. */
    public function refund(Order $order): void;
}
