<?php

declare(strict_types=1);

namespace Whodoku\Application\Payment;

use Illuminate\Support\Str;
use Whodoku\Application\Player\PlayerProgress;
use Whodoku\Models\Order;
use Whodoku\Models\Player;

/** Starts purchases of hint packs. A pack needs the attention skill: without it hints do not exist for the player. */
class ShopService
{
    public function __construct(
        private readonly PaymentDriver $driver,
        private readonly PlayerProgress $progress,
    ) {}

    /** @return list<array{code: string, title: string, hints: int, price_kopecks: int, price: string}> */
    public function products(): array
    {
        $list = [];
        foreach ((array) config('killdoku.shop.products') as $code => $product) {
            $list[] = [
                'code' => (string) $code, 'title' => $product['title'], 'hints' => $product['hints'],
                'price_kopecks' => $product['price_kopecks'], 'price' => self::rubles($product['price_kopecks']),
            ];
        }

        return $list;
    }

    /** @return array{can_buy: bool, reason: string|null, test_mode: bool, products: list<array<string, mixed>>} */
    public function offer(?Player $player): array
    {
        $reason = null;
        if ($player === null) {
            $reason = 'login_required';
        } elseif ($this->progress->hints($player)['locked']) {
            $reason = 'hints_locked';
        }

        return ['can_buy' => $reason === null, 'reason' => $reason, 'test_mode' => config('killdoku.shop.driver') === 'fake', 'products' => $this->products()];
    }

    /** @throws PurchaseRefused */
    public function start(Player $player, string $code): Order
    {
        $product = config("killdoku.shop.products.$code");
        if (!is_array($product)) {
            throw new PurchaseRefused('unknown_product', 'Такого товара нет.');
        }
        if ($this->progress->hints($player)['locked']) {
            throw new PurchaseRefused('hints_locked', 'Подсказки открывает навык «Внимательность»: вложите очко развития в профиле, и пакет можно будет купить.');
        }

        $gateway = $this->driver->make();
        $order = Order::query()->create([
            'public_id' => (string) Str::uuid(), 'player_id' => $player->id, 'product' => $code,
            'quantity' => $product['hints'], 'amount_kopecks' => $product['price_kopecks'],
            'currency' => (string) config('killdoku.shop.currency'), 'status' => Order::PENDING,
            'idempotence_key' => (string) Str::uuid(), 'provider' => $gateway->name(),
        ]);

        $link = $gateway->createPayment($order, $product['title'].' · Whodoku', url('/shop/return/'.$order->public_id));
        $order->update(['provider_payment_id' => $link->paymentId, 'meta' => ['confirmation_url' => $link->url]]);

        return $order;
    }

    public static function rubles(int $kopecks): string
    {
        return rtrim(rtrim(number_format($kopecks / 100, 2, '.', ''), '0'), '.');
    }
}
