<?php

declare(strict_types=1);

namespace Whodoku\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'puzzle_id', 'text', 'constraint_type', 'constraint', 'sort_order',
])]
class PuzzleClue extends Model
{
    /** @return BelongsTo<Puzzle, $this> */
    public function puzzle(): BelongsTo
    {
        return $this->belongsTo(Puzzle::class);
    }

    protected function casts(): array
    {
        return [
            'constraint' => 'array',
            'sort_order' => 'integer',
        ];
    }
}
