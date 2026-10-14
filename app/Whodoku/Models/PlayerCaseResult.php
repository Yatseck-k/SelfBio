<?php

declare(strict_types=1);

namespace Whodoku\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'player_id', 'puzzle_id', 'puzzle_slug', 'puzzle_title', 'puzzle_type', 'difficulty', 'revision',
    'first_opened_at', 'solved_at', 'checks_count', 'hints_used', 'points_awarded', 'duration_seconds',
])]
class PlayerCaseResult extends Model
{
    /** @return BelongsTo<Player, $this> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    protected function casts(): array
    {
        return ['first_opened_at' => 'datetime', 'solved_at' => 'datetime'];
    }
}
