<?php

declare(strict_types=1);

namespace Whodoku\Application\Player;

/** Who Telegram says the visitor is: only the id is trusted to stay the same. */
readonly class TelegramIdentity
{
    public function __construct(
        public int $id,
        public ?string $firstName = null,
        public ?string $username = null,
        public ?string $photoUrl = null,
    ) {}
}
