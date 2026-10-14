<?php

declare(strict_types=1);

namespace Whodoku\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Orchid\Screen\AsSource;

#[Fillable([
    'user_id', 'puzzle_id', 'actor_email', 'puzzle_slug', 'action',
    'validation_result', 'solver_result', 'duration_ms',
])]
#[WithoutTimestamps]
class AdminAuditLog extends Model
{
    use AsSource;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Puzzle, $this> */
    public function puzzle(): BelongsTo
    {
        return $this->belongsTo(Puzzle::class);
    }

    protected function casts(): array
    {
        return [
            'validation_result' => 'array',
            'solver_result' => 'array',
            'duration_ms' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
