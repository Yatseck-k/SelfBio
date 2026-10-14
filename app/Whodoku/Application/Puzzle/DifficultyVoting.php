<?php

declare(strict_types=1);

namespace Whodoku\Application\Puzzle;

use Illuminate\Support\Collection;
use Whodoku\Domain\Puzzle\Generation\DifficultyMetrics;
use Whodoku\Domain\Puzzle\Generation\DifficultyRating;
use Whodoku\Models\DifficultyVote;
use Whodoku\Models\Puzzle;
use Whodoku\Models\User;

/**
 * A blind check of the difficulty rating: the administrator plays a few cases of every level, says how hard each
 * felt (1–5 stars) without seeing the system's rating, and the thresholds of the levels are suggested from the
 * comparison.
 */
class DifficultyVoting
{
    /** How many cases of each level are offered. */
    public const PER_LEVEL = 4;

    public function __construct(private readonly DifficultyThresholds $thresholds = new DifficultyThresholds) {}

    /**
     * A steady sample: the same cases every time (until the set of cases changes), spread over the levels. Story
     * chapters are left out (their difficulty is set by the story).
     *
     * @return Collection<int, Puzzle>
     */
    public function sample(): Collection
    {
        $sample = collect();
        foreach (range(1, 5) as $level) {
            $sample = $sample->merge(
                Puzzle::query()->where('status', 'published')->whereNotNull('metrics')->where('difficulty', $level)
                    ->where('collection', '!=', 'story')->orderBy('id')->get()
                    ->sortBy(fn (Puzzle $puzzle) => md5("difficulty-vote-{$puzzle->id}"))->take(self::PER_LEVEL)->values(),
            );
        }

        // Shuffled by a fixed key, so the order does not give the rating away.
        return $sample->sortBy(fn (Puzzle $puzzle) => md5("order-{$puzzle->id}"))->values();
    }

    public function vote(User $user, Puzzle $puzzle, int $stars): void
    {
        DifficultyVote::query()->updateOrCreate(['user_id' => $user->id, 'puzzle_id' => $puzzle->id], ['stars' => max(1, min(5, $stars))]);
    }

    /** @return array<int, int> puzzle id => stars of this administrator */
    public function votesOf(User $user): array
    {
        return DifficultyVote::query()->where('user_id', $user->id)->pluck('stars', 'puzzle_id')->all();
    }

    public function score(Puzzle $puzzle): float
    {
        return is_array($puzzle->metrics) ? DifficultyRating::score(DifficultyMetrics::fromArray($puzzle->metrics)) : 0.0;
    }

    /**
     * The thresholds that would make the rating match the votes: between the mean scores of the cases the
     * administrator called k stars and k + 1 stars. A level without votes keeps its threshold.
     *
     * @return array{thresholds: list<float>, votes: int, counts: array<int, int>, means: array<int, float>}|null null when nothing was rated yet
     */
    public function suggestion(): ?array
    {
        $votes = DifficultyVote::query()->with('puzzle')->get()->filter(fn (DifficultyVote $vote) => $vote->puzzle !== null && is_array($vote->puzzle->metrics));
        if ($votes->isEmpty()) {
            return null;
        }

        $byStars = $votes->groupBy('stars');
        $means = [];
        foreach ($byStars as $stars => $group) {
            $means[(int) $stars] = round($group->avg(fn (DifficultyVote $vote) => $this->score($vote->puzzle)), 3);
        }

        $current = $this->thresholds->current();
        $suggested = $current;
        foreach (range(1, 4) as $k) {
            if (isset($means[$k], $means[$k + 1])) {
                $suggested[$k - 1] = round(($means[$k] + $means[$k + 1]) / 2, 3);
            }
        }
        // The thresholds must still grow: a gap of at least 0.01 and a value inside (0, 1).
        for ($i = 0; $i < 4; $i++) {
            $floor = $i === 0 ? 0.01 : $suggested[$i - 1] + 0.01;
            $suggested[$i] = round(min(0.97, max($floor, $suggested[$i])), 3);
        }

        return [
            'thresholds' => $suggested,
            'votes' => $votes->count(),
            'counts' => $byStars->map->count()->sortKeys()->all(),
            'means' => $means,
        ];
    }

    /** How many of the votes the current rating already agrees with, as `[agree, total]`. */
    public function agreement(?User $user = null): array
    {
        $votes = DifficultyVote::query()->with('puzzle')->when($user !== null, fn ($query) => $query->where('user_id', $user->id))->get()
            ->filter(fn (DifficultyVote $vote) => $vote->puzzle !== null);

        return [$votes->filter(fn (DifficultyVote $vote) => $vote->puzzle->difficulty === $vote->stars)->count(), $votes->count()];
    }

    /** Re-rates every case by the current thresholds. @return int how many cases changed their difficulty */
    public function applyToAll(): int
    {
        $service = new PuzzleRatingService(thresholds: $this->thresholds);
        $changed = 0;
        foreach (Puzzle::query()->orderBy('id')->get() as $puzzle) {
            [$before, $after] = $service->rate($puzzle);
            $changed += $before !== $after ? 1 : 0;
        }

        return $changed;
    }
}
