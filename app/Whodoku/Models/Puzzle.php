<?php

declare(strict_types=1);

namespace Whodoku\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Orchid\Screen\AsSource;

#[Fillable([
    'slug', 'title', 'description', 'story', 'type', 'difficulty', 'metrics',
    'rows', 'cols', 'status', 'collection', 'story_chapter', 'guest_access', 'released_on', 'revision', 'blocked_cells', 'windows', 'victim',
    'ending', 'published_at',
])]
#[Hidden(['ending'])]
class Puzzle extends Model
{
    use AsSource;

    /** @return HasMany<PuzzleRoom, $this> */
    public function rooms(): HasMany
    {
        return $this->hasMany(PuzzleRoom::class);
    }

    /** @return HasMany<PuzzleWall, $this> */
    public function walls(): HasMany
    {
        return $this->hasMany(PuzzleWall::class);
    }

    /** @return HasMany<PuzzleObject, $this> */
    public function objects(): HasMany
    {
        return $this->hasMany(PuzzleObject::class);
    }

    /** @return HasMany<PuzzleToken, $this> */
    public function tokens(): HasMany
    {
        return $this->hasMany(PuzzleToken::class);
    }

    /** @return HasMany<PuzzleClue, $this> */
    public function clues(): HasMany
    {
        return $this->hasMany(PuzzleClue::class);
    }

    /** @return HasOne<PuzzleSolution, $this> */
    public function solution(): HasOne
    {
        return $this->hasOne(PuzzleSolution::class);
    }

    /** @return HasMany<PuzzleHint, $this> */
    public function hints(): HasMany
    {
        return $this->hasMany(PuzzleHint::class);
    }

    /** @return HasMany<AdminAuditLog, $this> */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AdminAuditLog::class);
    }

    protected function casts(): array
    {
        return [
            'difficulty' => 'integer',
            'metrics' => 'array',
            'rows' => 'integer',
            'story_chapter' => 'integer',
            'cols' => 'integer',
            'revision' => 'integer',
            'blocked_cells' => 'array',
            'windows' => 'array',
            'victim' => 'array',
            'ending' => 'array',
            'published_at' => 'datetime',
            'guest_access' => 'boolean',
            'released_on' => 'date:Y-m-d',
        ];
    }
}
