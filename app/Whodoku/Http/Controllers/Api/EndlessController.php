<?php

declare(strict_types=1);

namespace Whodoku\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Whodoku\Application\Endless\EndlessService;
use Whodoku\Application\Endless\NoCaseLeft;
use Whodoku\Http\Controllers\Concerns\ResolvesPlayer;
use Whodoku\Http\Controllers\Controller;

/** The free play: the next case for the signed-in player, chosen by the difficulty they have reached. */
class EndlessController extends Controller
{
    use ResolvesPlayer;

    public function next(Request $request, EndlessService $endless): JsonResponse
    {
        $player = $this->player($request);
        if ($player === null) {
            return response()->json(['code' => 'login_required', 'message' => 'Свободная игра подбирает дела под ваш уровень — для неё нужен вход через Telegram.'], 401);
        }

        try {
            $next = $endless->next($player);
        } catch (NoCaseLeft) {
            return response()->json(['code' => 'no_case', 'message' => 'Все дела, которые вам сейчас открыты, решены. Новые дела выходят каждый день — загляните завтра, а сложные откроются вместе с навыком «Интеллект».'], 404);
        }

        return response()->json([
            'slug' => $next['puzzle']->slug,
            'title' => $next['puzzle']->title,
            'difficulty' => $next['difficulty'],
            'exact' => $next['exact'],
        ]);
    }
}
