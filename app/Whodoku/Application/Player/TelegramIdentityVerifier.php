<?php

declare(strict_types=1);

namespace Whodoku\Application\Player;

/** Turns what the browser got from Telegram into an identity, or refuses it. The widget is one way; OpenID Connect or a Mini App can be others. */
interface TelegramIdentityVerifier
{
    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws InvalidTelegramLogin
     */
    public function verify(array $payload): TelegramIdentity;
}
