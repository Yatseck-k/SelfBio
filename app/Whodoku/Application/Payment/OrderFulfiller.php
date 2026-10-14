<?php

declare(strict_types=1);

namespace Whodoku\Application\Payment;

use Illuminate\Support\Facades\DB;
use Throwable;
use Whodoku\Models\Order;
use Whodoku\Models\OrderEvent;
use Whodoku\Models\Player;

/**
 * Turns what the provider reports into hints. The request that brings the player back and the webhook only say
 * "look at this order": the state is always asked from the provider and compared with the order, so a forged
 * request cannot give anything. The grant happens once, under a lock on the order.
 */
class OrderFulfiller
{
    public function __construct(private readonly PaymentDriver $driver) {}

    /** Asks the provider and applies the answer. Returns the order as it is now. */
    public function settle(Order $order, string $source): Order
    {
        if ($order->provider_payment_id === null || !in_array($order->status, [Order::PENDING, Order::PAID], true)) {
            return $order;
        }

        try {
            $payment = $this->driver->make()->fetchPayment($order->provider_payment_id);
        } catch (Throwable $e) {
            $this->log($order, $source, null, 'provider_error', ['error' => $e->getMessage()]);

            return $order;
        }

        return DB::transaction(function () use ($order, $payment, $source): Order {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($payment->orderId !== $locked->public_id || $payment->amountKopecks !== $locked->amount_kopecks || $payment->currency !== $locked->currency) {
                if ($locked->status === Order::PENDING && $payment->isSucceeded()) {
                    $locked->update(['status' => Order::FAILED]);
                }
                $this->log($locked, $source, $payment->status, 'mismatch', [
                    'order' => $payment->orderId, 'amount' => $payment->amountKopecks, 'currency' => $payment->currency,
                ]);

                return $locked;
            }

            if ($locked->status === Order::PENDING) {
                if ($payment->isSucceeded()) {
                    $this->grant($locked);
                    $this->log($locked, $source, $payment->status, 'fulfilled');
                } elseif ($payment->status === 'canceled') {
                    $locked->update(['status' => Order::CANCELED]);
                    $this->log($locked, $source, $payment->status, 'canceled');
                }
            }

            if ($locked->status === Order::PAID && $payment->refundedKopecks >= $locked->amount_kopecks) {
                $this->takeBack($locked);
                $this->log($locked, $source, $payment->status, 'refunded');
            }

            return $locked->fresh() ?? $locked;
        });
    }

    /** The administrator gives the hints for a payment that is confirmed outside (a stuck order). Once only. */
    public function grantManually(Order $order, string $reason): Order
    {
        return DB::transaction(function () use ($order, $reason): Order {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === Order::PENDING || $locked->status === Order::FAILED) {
                $this->grant($locked);
                $this->log($locked, 'admin', null, 'fulfilled_manually', ['reason' => $reason]);
            }

            return $locked->fresh() ?? $locked;
        });
    }

    /** The administrator returns the money (the provider refunds the whole sum) and takes the hints back. */
    public function refund(Order $order): Order
    {
        if ($order->status !== Order::PAID) {
            return $order;
        }
        $this->driver->make()->refund($order);

        return DB::transaction(function () use ($order): Order {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === Order::PAID) {
                $this->takeBack($locked);
                $this->log($locked, 'admin', null, 'refunded');
            }

            return $locked->fresh() ?? $locked;
        });
    }

    private function grant(Order $order): void
    {
        if ($order->fulfilled_at !== null) {
            return;
        }
        Player::query()->whereKey($order->player_id)->increment('purchased_hints', $order->quantity);
        $order->update(['status' => Order::PAID, 'paid_at' => now(), 'fulfilled_at' => now()]);
    }

    private function takeBack(Order $order): void
    {
        $player = Player::query()->whereKey($order->player_id)->lockForUpdate()->first();
        $taken = $player === null ? 0 : min($order->quantity, $player->purchased_hints);
        if ($player !== null && $taken > 0) {
            $player->decrement('purchased_hints', $taken);
        }
        $order->update(['status' => Order::REFUNDED, 'meta' => [...($order->meta ?? []), 'hints_taken_back' => $taken]]);
    }

    /** @param array<string, mixed> $details */
    private function log(Order $order, string $source, ?string $providerStatus, string $result, array $details = []): void
    {
        OrderEvent::query()->create([
            'order_id' => $order->id, 'source' => $source, 'provider_status' => $providerStatus,
            'result' => $result, 'details' => $details === [] ? null : $details,
        ]);
    }
}
