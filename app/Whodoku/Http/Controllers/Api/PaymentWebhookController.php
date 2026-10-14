<?php

declare(strict_types=1);

namespace Whodoku\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Whodoku\Application\Payment\OrderFulfiller;
use Whodoku\Http\Controllers\Controller;
use Whodoku\Models\Order;

/**
 * Notifications of YooKassa. They carry no signature, so nothing in the body is trusted: only the payment id is
 * used to find the order, and the state is fetched from the provider (OrderFulfiller). Always answers 200 for a
 * well-formed call, otherwise the provider repeats it for days.
 */
class PaymentWebhookController extends Controller
{
    public function yookassa(Request $request, OrderFulfiller $fulfiller): JsonResponse
    {
        $allowed = (array) config('services.yookassa.webhook_ips');
        if ($allowed !== [] && !in_array($request->ip(), $allowed, true)) {
            return response()->json(['ok' => false], 403);
        }

        $object = $request->input('object');
        $paymentId = is_array($object) ? ($object['payment_id'] ?? $object['id'] ?? null) : null;
        if (!is_string($paymentId) || $paymentId === '') {
            return response()->json(['ok' => true]);
        }

        $order = Order::query()->where('provider_payment_id', $paymentId)->first();
        if ($order !== null) {
            $fulfiller->settle($order, 'webhook');
        }

        return response()->json(['ok' => true]);
    }
}
