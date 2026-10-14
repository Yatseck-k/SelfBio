<?php

declare(strict_types=1);

namespace Whodoku\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Whodoku\Application\Player\LevelLadder;
use Whodoku\Application\Player\PlayerPresenter;
use Whodoku\Application\Player\PlayerProgress;
use Whodoku\Application\Player\RankLadder;
use Whodoku\Application\Puzzle\PublicPuzzleService;
use Whodoku\Application\Story\StoryService;
use Whodoku\Http\Controllers\Concerns\GuardsPuzzleAccess;
use Whodoku\Http\Controllers\Concerns\ResolvesPlayer;
use Whodoku\Http\Controllers\Controller;
use Whodoku\Http\Requests\CheckPuzzleRequest;
use Whodoku\Models\Puzzle;

class PuzzleCheckController extends Controller
{
    use GuardsPuzzleAccess;
    use ResolvesPlayer;

    public function __invoke(CheckPuzzleRequest $request, PublicPuzzleService $puzzles, PlayerProgress $progress, PlayerPresenter $presenter, Puzzle $publishedPuzzle): JsonResponse
    {
        $player = $this->player($request);
        if ($locked = $this->lockedFor($player, $publishedPuzzle)) {
            return $locked;
        }

        $result = $puzzles->check($publishedPuzzle->slug, $request->validated('placements'));

        // A signed-in player is credited on the server: the first correct answer to a case pays its points.
        if ($player !== null) {
            $before = $player->points;
            $chaptersBefore = resolve(StoryService::class)->solvedChapters($player);
            $reward = $progress->checked($player, $publishedPuzzle, ($result['correct'] ?? false) === true);
            if ($reward !== null) {
                $player->refresh();
                $levelFrom = LevelLadder::forPoints($before)['level'];
                $levelTo = LevelLadder::forPoints($player->points)['level'];
                $rankTo = RankLadder::forChapters(resolve(StoryService::class)->solvedChapters($player));
                $rankUp = RankLadder::forChapters($chaptersBefore)['level'] < $rankTo['level'];
                $result['reward'] = [
                    ...$reward,
                    'player' => $presenter->present($player),
                    'rank_up' => $rankUp,
                    // A new level gives a development point per level; a new rank (only a solved chapter gives one) comes with a scene of the story.
                    'level_up' => $levelTo > $levelFrom ? ['from' => $levelFrom, 'to' => $levelTo, 'skill_points' => $levelTo - $levelFrom] : null,
                    'promotion' => $rankUp && config("story.promotions.{$rankTo['level']}") !== null
                        ? [
                            'rank' => $rankTo['title'],
                            'text' => config("story.promotions.{$rankTo['level']}.text"),
                            'speaker' => resolve(StoryService::class)->hero(config("story.promotions.{$rankTo['level']}.speaker")),
                        ]
                        : null,
                ];
            }
        }

        // The end of a story chapter: its closing lines go with the correct answer only.
        if (($result['correct'] ?? false) === true && ($chapter = resolve(StoryService::class)->chapterOf($publishedPuzzle)) !== null) {
            $result['chapter'] = ['number' => $chapter['number'], 'title' => $chapter['title'], 'outro' => $chapter['outro'], 'speaker' => $chapter['speaker'], 'last' => $chapter['number'] === max(resolve(StoryService::class)->numbers())];
            // The first solve of a chapter also gives its development points.
            if (isset($result['reward'])) {
                $result['reward']['story_points'] = (int) ($chapter['grant'] ?? 0);
            }
        }

        return response()->json($result);
    }
}
