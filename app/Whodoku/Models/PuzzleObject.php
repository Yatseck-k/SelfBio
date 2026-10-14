<?php

declare(strict_types=1);

namespace Whodoku\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'puzzle_id', 'code', 'type', 'name', 'row', 'col',
    'blocking', 'occupiable', 'metadata',
])]
class PuzzleObject extends Model
{
    /** @return BelongsTo<Puzzle, $this> */
    public function puzzle(): BelongsTo
    {
        return $this->belongsTo(Puzzle::class);
    }

    protected function casts(): array
    {
        return [
            'row' => 'integer',
            'col' => 'integer',
            'blocking' => 'boolean',
            'occupiable' => 'boolean',
            'metadata' => 'array',
        ];
    }
}
