<?php

declare(strict_types=1);

namespace Whodoku\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

#[Description('Check the Telegram bot used for sign-in: token, username and the domain reminder (the token is never printed)')]
#[Signature('telegram:check')]
class TelegramCheck extends Command
{
    public function handle(): int
    {
        $token = (string) config('services.telegram.bot_token');
        $username = (string) config('services.telegram.bot_username');

        if ($token === '' || $username === '') {
            $this->error('Set TELEGRAM_BOT_USERNAME and TELEGRAM_BOT_TOKEN in .env, then restart the application.');

            return self::FAILURE;
        }

        try {
            $response = Http::timeout(10)->get("https://api.telegram.org/bot{$token}/getMe");
        } catch (Throwable $e) {
            $this->error('Telegram is unreachable: '.$e->getMessage());

            return self::FAILURE;
        }

        if (!$response->ok() || !$response->json('ok')) {
            $this->error('Telegram refused the token (revoked or mistyped). Get a new one from BotFather: /mybots → the bot → API Token.');

            return self::FAILURE;
        }

        $real = (string) $response->json('result.username');
        if (strcasecmp(ltrim($username, '@'), $real) !== 0) {
            $this->error("TELEGRAM_BOT_USERNAME is «{$username}», but the token belongs to «{$real}».");

            return self::FAILURE;
        }

        $this->info("Bot @{$real} is reachable.");
        $url = (string) config('app.url');
        if (!str_starts_with($url, 'https://')) {
            $this->warn("APP_URL is «{$url}»: the sign-in widget works only on the https domain given to BotFather (/setdomain).");
        } else {
            $this->line('Domain for BotFather /setdomain: '.parse_url($url, PHP_URL_HOST));
        }
        if (config('services.telegram.dev_login') && app()->isProduction()) {
            $this->warn('TELEGRAM_DEV_LOGIN is on; it is ignored in production, but switch it off.');
        }

        return self::SUCCESS;
    }
}
