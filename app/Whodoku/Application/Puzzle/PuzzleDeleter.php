<?php

declare(strict_types=1);

namespace Whodoku\Application\Puzzle;

use Illuminate\Support\Facades\DB;
use Whodoku\Models\AdminAuditLog;
use Whodoku\Models\Puzzle;
use Whodoku\Models\User;

class PuzzleDeleter
{
    /**
     * Permanently removes a puzzle with its map, tokens, clues, hints and solution.
     * The audit trail survives: its rows keep the slug while puzzle_id is nulled by the foreign key.
     */
    public function delete(Puzzle $puzzle, ?User $actor): void
    {
        $startedAt = hrtime(true);

        DB::transaction(function () use ($puzzle, $actor, $startedAt): void {
            AdminAuditLog::query()->create([
                'user_id' => $actor?->id,
                'actor_email' => $actor?->email,
                'puzzle_id' => $puzzle->id,
                'puzzle_slug' => $puzzle->slug,
                'action' => 'delete',
                'validation_result' => [
                    'title' => $puzzle->title,
                    'status' => $puzzle->status,
                    'revision' => $puzzle->revision,
                ],
                'duration_ms' => (int) ((hrtime(true) - $startedAt) / 1_000_000),
            ]);

            $puzzle->delete();
        });
    }
}
