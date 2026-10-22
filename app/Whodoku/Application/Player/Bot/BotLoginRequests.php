<?php

declare(strict_types=1);

namespace Whodoku\Application\Player\Bot;

use Illuminate\Contracts\Cache\Repository;
use Whodoku\Application\Player\TelegramIdentity;

/**
 * Sign-in through the bot, no domain needed. The site makes a one-time request and hands out a link to the bot with
 * its code; the player opens the bot, sees which browser asks for the sign-in and confirms it, the bot attaches the
 * player's Telegram account to the request, and the browser that made the request collects it. The code travels
 * through Telegram, so the browser also holds a secret of its own: whoever only sees the link cannot collect the
 * sign-in. The confirmation step with the device on it is what stops a link sent to a stranger from signing the
 * sender in as the stranger.
 */
class BotLoginRequests
{
    public const TTL_SECONDS = 300;

    public function __construct(private readonly Repository $cache) {}

    /** @return array{code: string, secret: string} */
    public function open(string $ip = '', string $userAgent = ''): array
    {
        $code = bin2hex(random_bytes(16));
        $secret = bin2hex(random_bytes(16));
        $this->cache->put($this->key($code), [
            'secret_hash' => hash('sha256', $secret),
            'identity' => null,
            'ip' => $ip,
            'device' => self::describeDevice($userAgent),
        ], self::TTL_SECONDS);

        return ['code' => $code, 'secret' => $secret];
    }

    /** What the bot shows before the player confirms: where the sign-in is asked from. Null when the code is unknown, expired or taken. */
    public function pending(string $code): ?array
    {
        $request = $this->cache->get($this->key($code));

        return is_array($request) && $request['identity'] === null ? ['ip' => (string) ($request['ip'] ?? ''), 'device' => (string) ($request['device'] ?? '')] : null;
    }

    /** Called by the bot once the player has confirmed. False when the code is unknown, expired or already taken by someone. */
    public function confirm(string $code, TelegramIdentity $identity): bool
    {
        // Two confirmations of one code at once must not both pass: the check and the write go under a lock.
        return (bool) $this->cache->lock($this->key($code).':lock', 5)->get(function () use ($code, $identity): bool {
            $request = $this->cache->get($this->key($code));
            if (!is_array($request) || $request['identity'] !== null) {
                return false;
            }
            $request['identity'] = ['id' => $identity->id, 'first_name' => $identity->firstName, 'username' => $identity->username];
            $this->cache->put($this->key($code), $request, self::TTL_SECONDS);

            return true;
        });
    }

    /** @return 'expired'|'pending'|TelegramIdentity  an identity is returned once: the request is spent */
    public function claim(string $code, string $secret): string|TelegramIdentity
    {
        $request = $this->cache->get($this->key($code));
        if (!is_array($request) || !hash_equals($request['secret_hash'], hash('sha256', $secret))) {
            return 'expired';
        }
        if ($request['identity'] === null) {
            return 'pending';
        }
        // «pull» reads and deletes in one step: of two polls at once only one gets the identity.
        $taken = $this->cache->pull($this->key($code));
        if (!is_array($taken) || $taken['identity'] === null) {
            return 'expired';
        }
        $who = $taken['identity'];

        return new TelegramIdentity((int) $who['id'], $who['first_name'], $who['username']);
    }

    /** «iPhone · Safari» from a User-Agent: enough for a person to tell whether it is their own device. */
    public static function describeDevice(string $userAgent): string
    {
        if ($userAgent === '') {
            return 'неизвестное устройство';
        }
        $system = match (true) {
            str_contains($userAgent, 'iPhone') => 'iPhone',
            str_contains($userAgent, 'iPad') => 'iPad',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Mac OS X'), str_contains($userAgent, 'Macintosh') => 'Mac',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => 'устройство',
        };
        $browser = match (true) {
            str_contains($userAgent, 'Edg/') => 'Edge',
            str_contains($userAgent, 'YaBrowser') => 'Яндекс Браузер',
            str_contains($userAgent, 'OPR/') => 'Opera',
            str_contains($userAgent, 'Firefox') => 'Firefox',
            str_contains($userAgent, 'Chrome'), str_contains($userAgent, 'CriOS') => 'Chrome',
            str_contains($userAgent, 'Safari') => 'Safari',
            default => 'браузер',
        };

        return "{$system} · {$browser}";
    }

    private function key(string $code): string
    {
        return 'telegram-bot-login:'.$code;
    }
}
