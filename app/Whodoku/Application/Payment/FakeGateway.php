<?php

declare(strict_types=1);

namespace Whodoku\Application\Payment;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Whodoku\Models\Order;

/** A stand-in for development and tests: the "provider" is a cache entry and a page where the payment is confirmed by hand. */
class FakeGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'fake';
    }

    public function createPayment(Order $order, string $description, string $returnUrl): PaymentLink
    {
        $id = 'fake-'.Str::lower(Str::random(20));
        Cache::put($this->key($id), [
            'status' => 'pending', 'amount' => $order->amount_kopecks, 'currency' => $order->currency,
            'order' => $order->public_id, 'refunded' => 0, 'return' => $returnUrl,
        ], now()->addDay());

        return new PaymentLink($id, url('/shop/fake/'.$id));
    }

    public function fetchPayment(string $paymentId): ProviderPayment
    {
        $payment = Cache::get($this->key($paymentId));
        abort_unless(is_array($payment), 404);

        return new ProviderPayment($paymentId, $payment['status'], $payment['amount'], $payment['currency'], $payment['order'], $payment['refunded']);
    }

    public function refund(Order $order): void
    {
        $this->update((string) $order->provider_payment_id, ['refunded' => $order->amount_kopecks]);
    }

    /** @return array<string, mixed>|null */
    public function payment(string $paymentId): ?array
    {
        $payment = Cache::get($this->key($paymentId));

        return is_array($payment) ? $payment : null;
    }

    /** @param array<string, mixed> $changes */
    public function update(string $paymentId, array $changes): void
    {
        $payment = $this->payment($paymentId);
        if ($payment !== null) {
            Cache::put($this->key($paymentId), [...$payment, ...$changes], now()->addDay());
        }
    }

    private function key(string $id): string
    {
        return 'fake-payment:'.$id;
    }
}
