<?php

declare(strict_types=1);

namespace Whodoku\Application\Player;

/**
 * Checks the data of the Telegram Login Widget: the fields sorted by name and joined as `key=value` lines must
 * hash (HMAC-SHA-256) to the received `hash` with SHA-256 of the bot token as the key, and `auth_date` must be
 * recent. See https://core.telegram.org/widgets/login#checking-authorization
 */
class TelegramWidgetVerifier implements TelegramIdentityVerifier
{
    public function __construct(
        private readonly string $botToken,
        private readonly int $maxAgeSeconds = 86400,
        private readonly ?int $now = null,
    ) {}

    public function verify(array $payload): TelegramIdentity
    {
        if ($this->botToken === '') {
            throw new InvalidTelegramLogin('Telegram login is not configured.');
        }

        $hash = $payload['hash'] ?? null;
        if (!is_string($hash) || !isset($payload['id'], $payload['auth_date'])) {
            throw new InvalidTelegramLogin('Incomplete Telegram data.');
        }
        unset($payload['hash']);

        $lines = [];
        foreach ($payload as $key => $value) {
            if (!is_scalar($value)) {
                throw new InvalidTelegramLogin('Unexpected Telegram field.');
            }
            $lines[$key] = "$key=$value";
        }
        ksort($lines);
        $expected = hash_hmac('sha256', implode("\n", $lines), hash('sha256', $this->botToken, true));
        if (!hash_equals($expected, strtolower($hash))) {
            throw new InvalidTelegramLogin('Bad Telegram signature.');
        }

        $age = ($this->now ?? time()) - (int) $payload['auth_date'];
        if ($age > $this->maxAgeSeconds || $age < -300) {
            throw new InvalidTelegramLogin('The Telegram login is too old.');
        }

        return new TelegramIdentity(
            (int) $payload['id'],
            isset($payload['first_name']) ? (string) $payload['first_name'] : null,
            isset($payload['username']) ? (string) $payload['username'] : null,
            isset($payload['photo_url']) ? (string) $payload['photo_url'] : null,
        );
    }
}
