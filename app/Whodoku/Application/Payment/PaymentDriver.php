<?php

declare(strict_types=1);

namespace Whodoku\Application\Payment;

use RuntimeException;

/** Picks the gateway named in the config; production runs the stand-in only during the alpha test. */
class PaymentDriver
{
    public function make(): PaymentGateway
    {
        $driver = (string) config('killdoku.shop.driver');
        if ($driver === 'fake' && app()->isProduction() && !config('killdoku.shop.alpha')) {
            throw new RuntimeException('PAYMENTS_DRIVER=fake is not allowed in production outside the alpha test (PAYMENTS_ALPHA=true): set yookassa and its keys.');
        }

        return match ($driver) {
            'yookassa' => resolve(YooKassaGateway::class),
            'fake' => resolve(FakeGateway::class),
            default => throw new RuntimeException("Unknown payments driver «{$driver}»."),
        };
    }
}
