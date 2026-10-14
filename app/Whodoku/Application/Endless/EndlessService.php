<?php

declare(strict_types=1);

namespace Whodoku\Application\Endless;

use Whodoku\Application\Player\PlayerProgress;
use Whodoku\Application\Player\PlayerSkills;
use Whodoku\Application\Puzzle\PublishOptions;
use Whodoku\Application\Puzzle\PuzzleAccess;
use Whodoku\Models\Player;
use Whodoku\Models\PlayerCaseResult;
use Whodoku\Models\Puzzle;

/**
 * The free play (task 55): «следующее дело» without choosing from the catalog. The difficulty follows the player:
 * a streak of clean solves moves it up, hints move it down, and it never goes above what the intellect opens.
 * The case is taken from what is released — the starting set of a hundred and the ten new cases of every day —
 * nothing is generated on request.
 */
class EndlessService
{
    /** How many of the latest solved cases decide the next difficulty. */
    public const WINDOW = 3;

    public function __construct(
        private readonly PlayerProgress $progress = new PlayerProgress,
        private readonly PlayerSkills $skills = new PlayerSkills,
        private readonly PuzzleAccess $access = new PuzzleAccess,
    ) {}

    /**
     * The difficulty of the next case: the last solved one, one up after a clean run without hints, one down when
     * hints were needed in most of the latest cases; the first case is the easiest.
     */
    public function targetDifficulty(Player $player): int
    {
        $allowed = $this->skills->abilities($player)['difficulty'];
        $recent = PlayerCaseResult::query()->where('player_id', $player->id)->whereNotNull('solved_at')
            ->latest('solved_at')->orderByDesc('id')->limit(self::WINDOW)->get();
        if ($recent->isEmpty()) {
            return 1;
        }

        $target = (int) $recent->first()->difficulty;
        if ($recent->count() === self::WINDOW && $recent->every(fn (PlayerCaseResult $result) => $result->hints_used === 0)) {
            $target++;
        } elseif ($recent->filter(fn (PlayerCaseResult $result) => $result->hints_used > 0)->count() * 2 > $recent->count()) {
            $target--;
        }

        return max(1, min($allowed, $target));
    }

    /**
     * The next case: an unsolved one of the target difficulty from what is released (the starting set and the daily
     * cases); when there is none, the nearest difficulty the player may open, lower ones first.
     *
     * @return array{puzzle: Puzzle, difficulty: int, exact: bool}
     *
     * @throws NoCaseLeft when every case open to the player is solved
     */
    public function next(Player $player): array
    {
        $target = $this->targetDifficulty($player);
        $solved = $this->progress->solvedSlugs($player);

        $order = [$target];
        for ($step = 1; $step < 5; $step++) {
            array_push($order, $target - $step, $target + $step);
        }
        foreach ($order as $difficulty) {
            if ($difficulty < 1) {
                continue;
            }
            $found = Puzzle::query()->where('status', 'published')->where('difficulty', $difficulty)
                ->where('collection', '!=', PublishOptions::COLLECTION_STORY)
                ->when($solved !== [], fn ($query) => $query->whereNotIn('slug', $solved))
                ->inRandomOrder()->get()
                ->first(fn (Puzzle $puzzle) => $this->access->canOpen($player, $puzzle));
            if ($found !== null) {
                return ['puzzle' => $found, 'difficulty' => $difficulty, 'exact' => $difficulty === $target];
            }
        }

        throw new NoCaseLeft('Every open case is solved.');
    }
}
