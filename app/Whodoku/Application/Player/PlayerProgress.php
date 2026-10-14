<?php

declare(strict_types=1);

namespace Whodoku\Application\Player;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Whodoku\Application\Puzzle\PublishOptions;
use Whodoku\Application\Story\StoryService;
use Whodoku\Models\Player;
use Whodoku\Models\PlayerCaseResult;
use Whodoku\Models\PlayerHintUse;
use Whodoku\Models\Puzzle;

/**
 * What a signed-in player has done: opened and solved cases (statistics), points and rank, and the hints they
 * have earned — one as a welcome gift, and, once the attention skill opens them, one for every three solved cases
 * plus a stock for higher attention. Everything is decided on the server.
 */
class PlayerProgress
{
    public function __construct(private readonly PlayerSkills $skills = new PlayerSkills) {}

    public const SOLVES_PER_HINT = 3;

    public const STARTER_HINTS = 1;

    public const TIMEZONE = 'Europe/Moscow';

    /** The first time a player opens a case starts its record (and its stopwatch). */
    public function opened(Player $player, Puzzle $puzzle): PlayerCaseResult
    {
        return PlayerCaseResult::query()->firstOrCreate(
            ['player_id' => $player->id, 'puzzle_slug' => $puzzle->slug, 'revision' => $puzzle->revision],
            [
                'puzzle_id' => $puzzle->id, 'puzzle_title' => $puzzle->title, 'puzzle_type' => $puzzle->type,
                'difficulty' => $puzzle->difficulty, 'first_opened_at' => now(),
            ],
        );
    }

    /**
     * Counts one answer. A correct one closes the record and pays points once per case and revision.
     *
     * @return array{points: int, bonus: bool, first: bool}|null the reward when this check solved the case for the first time
     */
    public function checked(Player $player, Puzzle $puzzle, bool $correct): ?array
    {
        return DB::transaction(function () use ($player, $puzzle, $correct): ?array {
            $result = $this->opened($player, $puzzle);
            $result = PlayerCaseResult::query()->whereKey($result->id)->lockForUpdate()->firstOrFail();
            $result->increment('checks_count');

            if (!$correct || $result->solved_at !== null) {
                return null;
            }

            $hints = PlayerHintUse::query()->where('player_id', $player->id)->where('puzzle_slug', $puzzle->slug)->where('revision', $puzzle->revision)->count();
            $abilities = $this->skills->abilities($player);
            // The attention skill makes the first hints free for the bonus; the expertise skill adds a share to every case.
            $points = RankLadder::pointsForSolve((int) $puzzle->difficulty, max(0, $hints - $abilities['free_hints']));
            $bonus = $abilities['points_bonus'] + ((int) $puzzle->difficulty >= 4 ? $abilities['hard_bonus'] : 0);
            if ($bonus > 0) {
                $points = (int) round($points * (100 + $bonus) / 100);
            }
            // A chapter of the story pays more: the story is the quick way up through the ranks.
            if ($puzzle->collection === PublishOptions::COLLECTION_STORY) {
                $points = (int) round($points * (float) config('story.bonus', 1));
            }
            $result->update([
                'solved_at' => now(),
                'duration_seconds' => max(0, (int) $result->first_opened_at->diffInSeconds(now())),
                'hints_used' => $hints,
                'points_awarded' => $points,
            ]);
            $player->increment('points', $points);

            return ['points' => $points, 'bonus' => $hints <= $abilities['free_hints'], 'first' => true];
        });
    }

    public function solvedCount(Player $player): int
    {
        return PlayerCaseResult::query()->where('player_id', $player->id)->whereNotNull('solved_at')->count();
    }

    /** @return list<string> slugs of the cases the player has solved */
    public function solvedSlugs(Player $player): array
    {
        return PlayerCaseResult::query()->where('player_id', $player->id)->whereNotNull('solved_at')->pluck('puzzle_slug')->unique()->values()->all();
    }

    /** @return array{available: int, earned: int, spent: int, purchased: int, next_in: int, locked: bool} */
    public function hints(Player $player): array
    {
        $solved = $this->solvedCount($player);
        $abilities = $this->skills->abilities($player);
        $perHint = max(1, $abilities['hint_pace']);
        $purchased = max(0, (int) $player->purchased_hints);
        // Bought hints, like the earned ones, work only while the attention skill is learned.
        $earned = self::STARTER_HINTS + ($abilities['hints'] ? intdiv($solved, $perHint) + $abilities['hint_stock'] + $purchased : 0);
        $spent = PlayerHintUse::query()->where('player_id', $player->id)->count();

        return [
            // Until attention opens the hints even the gift one stays shut.
            'available' => $abilities['hints'] ? max(0, $earned - $spent) : 0,
            'earned' => $earned,
            'spent' => $spent,
            'purchased' => $purchased,
            'next_in' => $perHint - ($solved % $perHint),
            'locked' => !$abilities['hints'],
        ];
    }

    /**
     * Pays for a hint level of a case. Asking again for a level that was already paid for is free.
     *
     * @return bool false when the player has no hint left
     */
    public function spendHint(Player $player, Puzzle $puzzle, int $level): bool
    {
        return DB::transaction(function () use ($player, $puzzle, $level): bool {
            // Serialise the player's hints, so two quick clicks cannot spend one hint twice.
            Player::query()->whereKey($player->id)->lockForUpdate()->first();

            $paid = PlayerHintUse::query()
                ->where(['player_id' => $player->id, 'puzzle_slug' => $puzzle->slug, 'revision' => $puzzle->revision, 'level' => $level])
                ->exists();
            if ($paid) {
                return true;
            }
            if ($this->hints($player)['available'] < 1) {
                return false;
            }
            PlayerHintUse::query()->create([
                'player_id' => $player->id, 'puzzle_id' => $puzzle->id, 'puzzle_slug' => $puzzle->slug,
                'revision' => $puzzle->revision, 'level' => $level,
            ]);

            return true;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function stats(Player $player): array
    {
        $solved = PlayerCaseResult::query()->where('player_id', $player->id)->whereNotNull('solved_at')->latest('solved_at')->get();
        $durations = $solved->pluck('duration_seconds')->filter(fn ($seconds) => $seconds !== null);
        $days = $solved->map(fn (PlayerCaseResult $result) => CarbonImmutable::parse($result->solved_at)->setTimezone(self::TIMEZONE)->toDateString())->unique()->sort()->values()->all();

        return [
            'solved' => $solved->count(),
            'opened' => PlayerCaseResult::query()->where('player_id', $player->id)->count(),
            'by_type' => $solved->countBy('puzzle_type')->sortKeys()->all(),
            'by_difficulty' => $solved->countBy('difficulty')->sortKeys()->all(),
            'average_seconds' => $durations->isEmpty() ? null : (int) round($durations->avg()),
            'best_seconds' => $durations->isEmpty() ? null : (int) $durations->min(),
            'streak' => $this->streak($days),
            'hints' => $this->hints($player),
            'recent' => $solved->take(10)->map(fn (PlayerCaseResult $result) => [
                'slug' => $result->puzzle_slug, 'title' => $result->puzzle_title, 'type' => $result->puzzle_type,
                'difficulty' => $result->difficulty, 'solved_at' => $result->solved_at->toIso8601String(),
                'points' => $result->points_awarded, 'hints_used' => $result->hints_used, 'duration_seconds' => $result->duration_seconds,
            ])->values()->all(),
        ];
    }

    /**
     * Days in a row with a solved case: the current streak counts when the last one was today or yesterday.
     *
     * @param  list<string>  $days  ascending dates (Y-m-d) in the game timezone
     * @return array{current: int, best: int}
     */
    public function streak(array $days, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::now(self::TIMEZONE);
        $best = 0;
        $run = 0;
        $previous = null;
        foreach ($days as $day) {
            $run = $previous !== null && CarbonImmutable::parse($previous)->addDay()->toDateString() === $day ? $run + 1 : 1;
            $best = max($best, $run);
            $previous = $day;
        }
        $current = 0;
        if ($previous !== null && in_array($previous, [$today->toDateString(), $today->subDay()->toDateString()], true)) {
            $current = $run;
        }

        return ['current' => $current, 'best' => $best];
    }

    /**
     * @return array{players: list<array<string, mixed>>, me: array<string, mixed>|null}
     */
    public function leaderboard(string $period, ?Player $viewer = null, int $limit = 50): array
    {
        $solved = PlayerCaseResult::query()->whereNotNull('solved_at')->select('player_id', DB::raw('count(*) as solved'))->groupBy('player_id')->pluck('solved', 'player_id');
        $weekly = $period === 'week'
            ? PlayerCaseResult::query()->whereNotNull('solved_at')->where('solved_at', '>=', now()->subDays(7))
                ->select('player_id', DB::raw('sum(points_awarded) as points'))->groupBy('player_id')->pluck('points', 'player_id')
            : null;

        $chapters = new StoryService($this)->chapterCounts();
        $rows = Player::query()->whereNotNull('detective_name')->whereNull('banned_at')->get()
            ->map(fn (Player $player) => [
                'id' => $player->id,
                'name' => $player->detective_name,
                'avatar' => $player->avatar,
                'title' => RankLadder::forChapters($chapters[$player->id] ?? 0)['title'],
                'points' => $weekly === null ? $player->points : (int) ($weekly[$player->id] ?? 0),
                'total_points' => $player->points,
                'solved' => (int) ($solved[$player->id] ?? 0),
            ])
            ->filter(fn (array $row) => $weekly === null || $row['points'] > 0)
            ->sort(fn (array $a, array $b) => [$b['points'], $b['solved'], $a['id']] <=> [$a['points'], $a['solved'], $b['id']])
            ->values();

        $positioned = $rows->map(fn (array $row, int $index) => ['position' => $index + 1, ...$row]);
        $me = $viewer === null ? null : $positioned->firstWhere('id', $viewer->id);

        return [
            'players' => $positioned->take($limit)->map(fn (array $row) => collect($row)->except(['id', 'total_points'])->all())->all(),
            'me' => $me === null ? null : collect($me)->except(['id', 'total_points'])->all(),
        ];
    }
}
