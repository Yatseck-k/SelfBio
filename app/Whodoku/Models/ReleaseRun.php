<?php

declare(strict_types=1);

namespace Whodoku\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['kind', 'status', 'requested', 'created', 'failed', 'message', 'actor_email', 'started_at', 'finished_at', 'updated_at'])]
#[WithoutTimestamps]
class ReleaseRun extends Model
{
    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'finished_at' => 'datetime', 'updated_at' => 'datetime'];
    }
}
