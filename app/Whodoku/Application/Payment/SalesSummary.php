<?php

declare(strict_types=1);

namespace Whodoku\Application\Payment;

use Whodoku\Models\Order;

/** Revenue figures for the admin: paid orders only, refunds counted apart. */
class SalesSummary
{
    /** @return array<string, string> */
    public function overall(): array
    {
        // The stand-in payments of the alpha test bring no money and stay out of the figures.
        $paid = Order::query()->where('provider', '!=', 'fake')->whereIn('status', [Order::PAID, Order::REFUNDED]);
        $week = (clone $paid)->where('paid_at', '>=', now()->subDays(7));
        $month = (clone $paid)->where('paid_at', '>=', now()->subDays(30));
        $net = fn ($query) => (int) (clone $query)->where('status', Order::PAID)->sum('amount_kopecks');
        $count = (clone $paid)->count();
        $refunded = (clone $paid)->where('status', Order::REFUNDED)->count();
        $payers = (clone $paid)->where('status', Order::PAID)->distinct()->count('player_id');
        $revenue = $net($paid);
        $paidCount = (clone $paid)->where('status', Order::PAID)->count();
        $attention = Order::query()->where('provider', '!=', 'fake')->where('status', Order::PENDING)
            ->where('created_at', '<', now()->subMinutes((int) config('killdoku.shop.pending_minutes', 60)))->count();

        return [
            'week' => ShopService::rubles($net($week)).' ₽',
            'month' => ShopService::rubles($net($month)).' ₽',
            'total' => ShopService::rubles($revenue).' ₽',
            'payers' => (string) $payers,
            'average' => $paidCount === 0 ? '—' : ShopService::rubles((int) round($revenue / $paidCount)).' ₽',
            'refund_share' => $count === 0 ? '—' : round($refunded / $count * 100).' %',
            'attention' => (string) $attention,
        ];
    }
}
