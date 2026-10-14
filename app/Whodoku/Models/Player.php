<?php

declare(strict_types=1);

namespace Whodoku\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Orchid\Screen\AsSource;

/**
 * A player: signed in with Telegram, plays as a detective. Separate from the admin users of Orchid.
 *
 * @property int|null $solved_count set by withCount in the admin list
 */
#[Fillable([
    'telegram_id', 'telegram_username', 'first_name', 'photo_url', 'detective_name', 'detective_key', 'avatar',
    'points', 'skills', 'skills_reset_at', 'skills_reset_level', 'banned_at', 'last_seen_at',
])]
#[Hidden(['remember_token'])]
class Player extends Authenticatable
{
    use AsSource;

    #[\Override]
    protected $attributes = ['points' => 0, 'purchased_hints' => 0];

    /** @return HasMany<PlayerCaseResult, $this> */
    public function results(): HasMany
    {
        return $this->hasMany(PlayerCaseResult::class);
    }

    /** @return HasMany<PlayerHintUse, $this> */
    public function hintUses(): HasMany
    {
        return $this->hasMany(PlayerHintUse::class);
    }

    public function isBanned(): bool
    {
        return $this->banned_at !== null;
    }

    protected function casts(): array
    {
        return [
            'telegram_id' => 'integer',
            'points' => 'integer',
            'purchased_hints' => 'integer',
            'skills' => 'array',
            'skills_reset_at' => 'datetime',
            'skills_reset_level' => 'integer',
            'banned_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }
}
