<?php

declare(strict_types=1);

namespace Whodoku\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Whodoku\Models\Player;

trait ResolvesPlayer
{
    /** The signed-in player; a banned one is signed out on the spot. */
    protected function player(Request $request): ?Player
    {
        $player = $request->user('player');
        if ($player instanceof Player && $player->isBanned()) {
            Auth::guard('player')->logout();

            return null;
        }

        return $player instanceof Player ? $player : null;
    }
}
