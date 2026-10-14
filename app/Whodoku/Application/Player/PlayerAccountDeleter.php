<?php

declare(strict_types=1);

namespace Whodoku\Application\Player;

use Illuminate\Support\Facades\DB;
use Whodoku\Models\AdminAuditLog;
use Whodoku\Models\Player;

/** Removes a player at their own request together with their results and hints; the audit keeps only the id. */
class PlayerAccountDeleter
{
    public function delete(Player $player): void
    {
        DB::transaction(function () use ($player): void {
            AdminAuditLog::query()->create([
                'action' => 'player_delete',
                'validation_result' => ['player_id' => $player->id, 'points' => $player->points, 'by' => 'player'],
            ]);
            $player->delete();
        });
    }
}
