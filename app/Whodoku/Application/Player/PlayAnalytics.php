<?php

declare(strict_types=1);

namespace Whodoku\Application\Player;

use Whodoku\Models\Player;
use Whodoku\Models\PlayerCaseResult;

/** Numbers for the administrator: how the cases and the players are doing. */
class PlayAnalytics
{
    /** @return array{opened: int, solved: int, rate: ?int, average_seconds: ?int, average_hints: ?float, average_checks: ?float} */
    public function forCase(string $slug, int $revision): array
    {
        $results = PlayerCaseResult::query()->where('puzzle_slug', $slug)->where('revision', $revision);
        $opened = (clone $results)->count();
        $solved = (clone $results)->whereNotNull('solved_at');
        $count = (clone $solved)->count();

        return [
            'opened' => $opened,
            'solved' => $count,
            'rate' => $opened === 0 ? null : (int) round($count / $opened * 100),
            'average_seconds' => $count === 0 ? null : (int) round((float) (clone $solved)->avg('duration_seconds')),
            'average_hints' => $count === 0 ? null : round((float) (clone $solved)->avg('hints_used'), 1),
            'average_checks' => $opened === 0 ? null : round((float) (clone $results)->avg('checks_count'), 1),
        ];
    }

    /** @return array{players: int, active_week: int, solved: int, solved_week: int} */
    public function overall(): array
    {
        return [
            'players' => Player::query()->count(),
            'active_week' => Player::query()->where('last_seen_at', '>=', now()->subDays(7))->count(),
            'solved' => PlayerCaseResult::query()->whereNotNull('solved_at')->count(),
            'solved_week' => PlayerCaseResult::query()->where('solved_at', '>=', now()->subDays(7))->count(),
        ];
    }
}
