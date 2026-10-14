<?php

declare(strict_types=1);

namespace Whodoku\Application\Player;

use Whodoku\Models\Player;

class PlayerRegistry
{
    /** The player for a Telegram identity, created on the first sign-in. Names and photos follow Telegram; the id never changes. */
    public function fromIdentity(TelegramIdentity $identity): Player
    {
        $player = Player::query()->firstOrNew(['telegram_id' => $identity->id]);
        $player->fill([
            'first_name' => $identity->firstName,
            'telegram_username' => $identity->username,
            'photo_url' => $identity->photoUrl,
            'last_seen_at' => now(),
        ])->save();

        return $player;
    }
}
