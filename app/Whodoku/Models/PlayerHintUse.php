<?php

declare(strict_types=1);

namespace Whodoku\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['player_id', 'puzzle_id', 'puzzle_slug', 'revision', 'level'])]
#[WithoutTimestamps]
class PlayerHintUse extends Model
{
    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
