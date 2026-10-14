<?php

declare(strict_types=1);

namespace Whodoku\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;
use Whodoku\Application\Player\PlayerSkills;
use Whodoku\Application\Puzzle\PuzzleAccess;
use Whodoku\Models\Player;
use Whodoku\Models\Puzzle;
use Whodoku\Support\Plural;

trait GuardsPuzzleAccess
{
    /** The answer for a guest who asks for a case that is for signed-in players only, or null when the case is open. */
    protected function lockedFor(?Player $player, Puzzle $puzzle): ?JsonResponse
    {
        $access = resolve(PuzzleAccess::class);
        if ($access->canOpen($player, $puzzle)) {
            return null;
        }
        if ($access->closedByChapter($player, $puzzle)) {
            return response()->json([
                'code' => 'chapter_locked',
                'message' => 'Эта глава откроется, когда вы раскроете предыдущую. Путь детектива — на странице «Сюжет».',
            ], 403);
        }
        if ($access->closedByLevel($player, $puzzle)) {
            $unlock = resolve(PlayerSkills::class)->unlockFor((int) $puzzle->difficulty);

            return response()->json([
                'code' => 'level_locked',
                'message' => $unlock === null
                    ? 'Это дело пока закрыто.'
                    : "Дела сложности {$puzzle->difficulty} открываются при {$unlock['at']} ".Plural::ru((int) $unlock['at'], 'очке', 'очках', 'очках')." навыка «{$unlock['label']}». Вложите очки развития в профиле.",
                'unlock' => $unlock,
            ], 403);
        }

        return response()->json([
            'code' => 'login_required',
            'message' => 'Это дело открывается после входа через Telegram. Для гостей доступны гостевые дела.',
        ], 403);
    }
}
