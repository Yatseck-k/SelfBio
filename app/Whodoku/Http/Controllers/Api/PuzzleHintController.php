<?php

declare(strict_types=1);

namespace Whodoku\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Whodoku\Application\Player\PlayerProgress;
use Whodoku\Application\Puzzle\PublicPuzzleService;
use Whodoku\Http\Controllers\Concerns\GuardsPuzzleAccess;
use Whodoku\Http\Controllers\Concerns\ResolvesPlayer;
use Whodoku\Http\Controllers\Controller;
use Whodoku\Http\Requests\PuzzleHintRequest;
use Whodoku\Models\Puzzle;
use Whodoku\Support\Plural;

class PuzzleHintController extends Controller
{
    use GuardsPuzzleAccess;
    use ResolvesPlayer;

    /** Hints belong to signed-in players: one as a gift and one for every three solved cases. */
    public function __invoke(PuzzleHintRequest $request, PublicPuzzleService $puzzles, PlayerProgress $progress, Puzzle $publishedPuzzle): JsonResponse
    {
        $player = $this->player($request);
        if ($locked = $this->lockedFor($player, $publishedPuzzle)) {
            return $locked;
        }
        if ($player === null) {
            return response()->json(['code' => 'login_required', 'message' => 'Подсказки доступны после входа и изучения навыка «Внимательность».'], 401);
        }

        if ($progress->hints($player)['locked']) {
            return response()->json([
                'code' => 'hints_locked',
                'message' => 'Подсказки открывает навык «Внимательность»: вложите очко развития в профиле.',
                'hints' => $progress->hints($player),
            ], 403);
        }

        $level = $request->integer('level');
        $hint = $puzzles->hint($publishedPuzzle->slug, $level, $request->validated('placements') ?? []);

        if (!$progress->spendHint($player, $publishedPuzzle, $level)) {
            $left = $progress->hints($player)['next_in'];

            return response()->json([
                'code' => 'no_hints',
                'message' => "Подсказок не осталось. Новая появится после ещё $left ".Plural::ru($left, 'решённого дела', 'решённых дел', 'решённых дел').'.',
                'hints' => $progress->hints($player),
            ], 403);
        }

        return response()->json([...$hint, 'hints' => $progress->hints($player)]);
    }
}
