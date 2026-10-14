<?php

declare(strict_types=1);

namespace Whodoku\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Whodoku\Application\Player\PlayerProgress;
use Whodoku\Application\Puzzle\PublicPuzzleService;
use Whodoku\Application\Puzzle\PuzzleAccess;
use Whodoku\Application\Story\StoryService;
use Whodoku\Http\Controllers\Concerns\GuardsPuzzleAccess;
use Whodoku\Http\Controllers\Concerns\ResolvesPlayer;
use Whodoku\Http\Controllers\Controller;
use Whodoku\Models\Puzzle;

class PuzzleController extends Controller
{
    use GuardsPuzzleAccess;
    use ResolvesPlayer;

    /** `solved` lists the cases the signed-in player has solved (kept on the server); guests keep theirs in the browser. */
    public function index(Request $request, PublicPuzzleService $puzzles, PlayerProgress $progress): JsonResponse
    {
        $player = $this->player($request);

        return response()->json([
            'data' => $puzzles->catalog($player),
            'solved' => $player === null ? [] : $progress->solvedSlugs($player),
            // Guests are told how many more cases wait behind the sign-in.
            'locked' => $player === null ? resolve(PuzzleAccess::class)->hiddenFromGuests() : 0,
        ]);
    }

    public function show(Request $request, PublicPuzzleService $puzzles, PlayerProgress $progress, Puzzle $publishedPuzzle): JsonResponse
    {
        $player = $this->player($request);
        if ($locked = $this->lockedFor($player, $publishedPuzzle)) {
            return $locked;
        }
        $detail = $puzzles->detail($publishedPuzzle->slug);
        $chapter = resolve(StoryService::class)->chapterOf($publishedPuzzle);
        if ($chapter !== null) {
            // The briefing and the number; the outro is told only after the solution.
            $detail['chapter'] = ['number' => $chapter['number'], 'title' => $chapter['title'], 'briefing' => $chapter['briefing'], 'speaker' => $chapter['speaker']];
        }
        if ($player !== null) {
            $progress->opened($player, $publishedPuzzle);
        }

        return response()->json($detail);
    }
}
