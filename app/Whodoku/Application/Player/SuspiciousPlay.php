<?php

declare(strict_types=1);

namespace Whodoku\Application\Player;

use Whodoku\Models\Player;
use Whodoku\Models\PlayerCaseResult;

/**
 * Flags the signs of a rating that was not played by hand, for an administrator to look at. It never blocks anyone:
 * a flag is a reason to check, not a verdict.
 */
class SuspiciousPlay
{
    /** A solve faster than this is not what a person reading statements and placing guests does. */
    public static function fastLimitSeconds(int $difficulty): int
    {
        return 10 + 8 * max(1, $difficulty);
    }

    /** @return list<string> human-readable reasons, empty when nothing stands out */
    public function reasons(Player $player): array
    {
        $reasons = [];

        $fast = PlayerCaseResult::query()
            ->where('player_id', $player->id)->whereNotNull('solved_at')->where('solved_at', '>=', now()->subDay())
            ->whereRaw('duration_seconds < 10 + 8 * difficulty')->count();
        if ($fast >= 3) {
            $reasons[] = "{$fast} слишком быстрых решений за сутки";
        }

        $lastHour = PlayerCaseResult::query()->where('player_id', $player->id)->where('solved_at', '>=', now()->subHour())->count();
        if ($lastHour >= 10) {
            $reasons[] = "{$lastHour} решённых дел за час";
        }

        return $reasons;
    }
}
