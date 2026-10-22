<?php

declare(strict_types=1);

namespace Whodoku\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Sleep;
use Throwable;
use Whodoku\Application\Player\Bot\TelegramBot;

#[Description('Listen to the sign-in bot (long polling, no domain or HTTPS needed); run it as a permanent process')]
#[Signature('telegram:poll {--once : Handle one batch of updates and stop}')]
class TelegramPoll extends Command
{
    public function handle(TelegramBot $bot): int
    {
        if (!$bot->configured()) {
            $this->error('Set TELEGRAM_BOT_TOKEN in .env, then restart.');
            // As a permanent container this must not exit: the container would restart in a loop. It waits for the restart that follows the .env change.
            if (!$this->option('once')) {
                Sleep::sleep(86400);
            }

            return self::FAILURE;
        }
        $bot->dropWebhook();
        $offset = 0;

        do {
            try {
                foreach ($bot->updates($offset, $this->option('once') ? 0 : 25) as $update) {
                    $offset = max($offset, (int) $update['update_id'] + 1);
                    try {
                        $bot->handle($update);
                    } catch (Throwable $e) {
                        // The message of a failed request carries the address with the token: report a cleaned copy.
                        report(new \RuntimeException(TelegramBot::redact($e->getMessage())));
                    }
                }
            } catch (Throwable $e) {
                $this->warn(TelegramBot::redact($e->getMessage()));
                if ($this->option('once')) {
                    return self::FAILURE;
                }
                Sleep::sleep(5);
            }
        } while (!$this->option('once'));

        return self::SUCCESS;
    }
}
