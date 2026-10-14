<?php

declare(strict_types=1);

namespace Whodoku\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;
use Whodoku\Application\Payment\OrderFulfiller;
use Whodoku\Application\Payment\PurchaseRefused;
use Whodoku\Application\Payment\ShopService;
use Whodoku\Application\Player\PlayerProgress;
use Whodoku\Http\Controllers\Concerns\ResolvesPlayer;
use Whodoku\Http\Controllers\Controller;
use Whodoku\Models\Order;

class ShopController extends Controller
{
    use ResolvesPlayer;

    public function show(Request $request, ShopService $shop): JsonResponse
    {
        return response()->json($shop->offer($this->player($request)));
    }

    public function store(Request $request, ShopService $shop): JsonResponse
    {
        $player = $this->player($request);
        if ($player === null) {
            return response()->json(['code' => 'login_required', 'message' => 'Покупать подсказки могут вошедшие игроки.'], 401);
        }
        $data = $request->validate(['product' => ['required', 'string', 'max:40'], 'consent' => ['accepted']]);

        try {
            $order = $shop->start($player, $data['product']);
        } catch (PurchaseRefused $e) {
            return response()->json(['code' => $e->reason, 'message' => $e->getMessage()], $e->reason === 'unknown_product' ? 404 : 403);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['code' => 'payment_unavailable', 'message' => 'Оплата временно недоступна. Попробуйте позже.'], 503);
        }

        return response()->json(['order' => $order->public_id, 'confirmation_url' => $order->meta['confirmation_url'] ?? null], 201);
    }

    /** The return page asks this: the server checks the provider itself, not the address the player came back by. */
    public function status(Request $request, Order $order, OrderFulfiller $fulfiller, PlayerProgress $progress): JsonResponse
    {
        $player = $this->player($request);
        abort_unless($player !== null && $player->id === $order->player_id, 404);

        $order = $fulfiller->settle($order, 'return');

        return response()->json([
            'status' => $order->status, 'quantity' => $order->quantity,
            'hints' => $progress->hints($player->fresh() ?? $player),
        ]);
    }
}
