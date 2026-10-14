<?php

declare(strict_types=1);

namespace Whodoku\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'puzzle_id', 'level', 'text', 'sort_order', 'metadata',
])]
class PuzzleHint extends Model
{
    /** @return BelongsTo<Puzzle, $this> */
    public function puzzle(): BelongsTo
    {
        return $this->belongsTo(Puzzle::class);
    }

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }
}
