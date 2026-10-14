<?php

declare(strict_types=1);

namespace Whodoku\Application\Payment;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Whodoku\Models\Order;

class YooKassaGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'yookassa';
    }

    public function createPayment(Order $order, string $description, string $returnUrl): PaymentLink
    {
        $body = [
            'amount' => ['value' => $order->rubles(), 'currency' => $order->currency],
            'capture' => true,
            'confirmation' => ['type' => 'redirect', 'return_url' => $returnUrl],
            'description' => $description,
            'metadata' => ['order' => $order->public_id],
        ];
        if (config('killdoku.shop.receipt')) {
            $body['receipt'] = [
                'customer' => ['full_name' => (string) ($order->player->detective_name ?? 'Игрок')],
                'items' => [[
                    'description' => $description, 'quantity' => '1.00', 'vat_code' => 1, 'payment_subject' => 'service',
                    'payment_mode' => 'full_payment', 'amount' => ['value' => $order->rubles(), 'currency' => $order->currency],
                ]],
            ];
        }

        $response = $this->http()->withHeaders(['Idempotence-Key' => $order->idempotence_key])->post('/payments', $body);
        $url = $response->json('confirmation.confirmation_url');
        if (!$response->successful() || !is_string($url) || !is_string($response->json('id'))) {
            throw new RuntimeException('YooKassa did not create the payment (HTTP '.$response->status().').');
        }

        return new PaymentLink($response->json('id'), $url);
    }

    public function fetchPayment(string $paymentId): ProviderPayment
    {
        $response = $this->http()->get('/payments/'.rawurlencode($paymentId));
        if (!$response->successful()) {
            throw new RuntimeException('YooKassa did not return the payment (HTTP '.$response->status().').');
        }

        return new ProviderPayment(
            id: (string) $response->json('id'),
            status: (string) $response->json('status'),
            amountKopecks: self::kopecks($response->json('amount.value')),
            currency: (string) $response->json('amount.currency'),
            orderId: is_string($response->json('metadata.order')) ? $response->json('metadata.order') : null,
            refundedKopecks: self::kopecks($response->json('refunded_amount.value') ?? 0),
        );
    }

    public function refund(Order $order): void
    {
        $response = $this->http()->withHeaders(['Idempotence-Key' => 'refund-'.$order->idempotence_key])->post('/refunds', [
            'payment_id' => $order->provider_payment_id,
            'amount' => ['value' => $order->rubles(), 'currency' => $order->currency],
        ]);
        if (!$response->successful()) {
            throw new RuntimeException('YooKassa did not accept the refund (HTTP '.$response->status().').');
        }
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl((string) config('services.yookassa.api_url'))
            ->withBasicAuth((string) config('services.yookassa.shop_id'), (string) config('services.yookassa.secret_key'))
            ->acceptJson()->asJson()->timeout(15);
    }

    /** "99.00" → 9900 without float errors. */
    private static function kopecks(mixed $value): int
    {
        return (int) round(((float) $value) * 100);
    }
}
