<?php

declare(strict_types=1);

namespace Whodoku\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'puzzle_id', 'code', 'name', 'kind', 'description', 'avatar',
    'counts_for_row_col', 'sort_order', 'metadata',
])]
class PuzzleToken extends Model
{
    /** @return BelongsTo<Puzzle, $this> */
    public function puzzle(): BelongsTo
    {
        return $this->belongsTo(Puzzle::class);
    }

    protected function casts(): array
    {
        return [
            'counts_for_row_col' => 'boolean',
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }
}
