<?php

declare(strict_types=1);

namespace Whodoku\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'puzzle_id', 'row_from', 'col_from', 'row_to', 'col_to',
])]
class PuzzleWall extends Model
{
    /** @return BelongsTo<Puzzle, $this> */
    public function puzzle(): BelongsTo
    {
        return $this->belongsTo(Puzzle::class);
    }

    protected function casts(): array
    {
        return [
            'row_from' => 'integer',
            'col_from' => 'integer',
            'row_to' => 'integer',
            'col_to' => 'integer',
        ];
    }
}
