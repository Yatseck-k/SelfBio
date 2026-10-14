<?php

declare(strict_types=1);

namespace Whodoku\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Whodoku\Application\Player\PlayerProgress;
use Whodoku\Http\Controllers\Concerns\ResolvesPlayer;
use Whodoku\Http\Controllers\Controller;

class LeaderboardController extends Controller
{
    use ResolvesPlayer;

    public function __invoke(Request $request, PlayerProgress $progress): JsonResponse
    {
        $period = $request->query('period') === 'week' ? 'week' : 'all';

        return response()->json(['period' => $period, ...$progress->leaderboard($period, $this->player($request))]);
    }
}
