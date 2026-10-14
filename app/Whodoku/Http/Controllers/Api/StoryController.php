<?php

declare(strict_types=1);

namespace Whodoku\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Whodoku\Application\Player\RankLadder;
use Whodoku\Application\Story\StoryService;
use Whodoku\Http\Controllers\Concerns\ResolvesPlayer;
use Whodoku\Http\Controllers\Controller;

/** The path of the story «Путь детектива»: every chapter with its state for the viewer. */
class StoryController extends Controller
{
    use ResolvesPlayer;

    public function __invoke(Request $request, StoryService $story): JsonResponse
    {
        $player = $this->player($request);

        $solved = $player === null ? 0 : $story->solvedChapters($player);

        return response()->json([
            'signed_in' => $player !== null,
            'solved' => $solved,
            'total' => count($story->numbers()),
            // The rank follows the solved chapters (the campaign is the only way to a rank).
            'rank' => $player === null ? null : RankLadder::forChapters($solved),
            'acts' => config('story.acts', []),
            'chapters' => $story->path($player),
        ]);
    }
}
