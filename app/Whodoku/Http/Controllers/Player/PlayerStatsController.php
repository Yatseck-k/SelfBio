<?php

declare(strict_types=1);

namespace Whodoku\Http\Controllers\Player;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Whodoku\Application\Player\PlayerPresenter;
use Whodoku\Application\Player\PlayerProgress;
use Whodoku\Http\Controllers\Concerns\ResolvesPlayer;
use Whodoku\Http\Controllers\Controller;

class PlayerStatsController extends Controller
{
    use ResolvesPlayer;

    public function __invoke(Request $request, PlayerPresenter $presenter, PlayerProgress $progress): JsonResponse
    {
        $player = $this->player($request);
        abort_if($player === null, 401);

        return response()->json(['player' => $presenter->present($player), 'stats' => $progress->stats($player)]);
    }
}
