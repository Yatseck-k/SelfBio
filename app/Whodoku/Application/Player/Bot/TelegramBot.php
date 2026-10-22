<?php

declare(strict_types=1);

namespace Whodoku\Application\Player\Bot;

use Illuminate\Support\Facades\Http;
use Whodoku\Application\Player\TelegramIdentity;
use Whodoku\Models\Player;

/** The few calls to the Telegram Bot API that the sign-in needs, and the reaction to what players write to the bot. */
class TelegramBot
{
    private const string CONFIRM_PREFIX = 'login:';

    public function __construct(
        private readonly BotLoginRequests $requests,
        private readonly string $token,
    ) {}

    /** The address of the Bot API holds the token: never let it into a log line. */
    public static function redact(string $text): string
    {
        return (string) preg_replace('#bot\d+:[A-Za-z0-9_-]+#', 'bot<token>', $text);
    }

    public function configured(): bool
    {
        return $this->token !== '';
    }

    /** @return list<array<string, mixed>> */
    public function updates(int $offset, int $timeout = 25): array
    {
        $response = Http::timeout($timeout + 10)->get($this->url('getUpdates'), [
            'offset' => $offset,
            'timeout' => $timeout,
            'allowed_updates' => json_encode(['message', 'callback_query']),
        ]);
        if (!$response->ok() || !$response->json('ok')) {
            throw new \RuntimeException('getUpdates failed: '.$response->status());
        }

        return $response->json('result') ?? [];
    }

    /** Long polling does not work while a webhook is set. */
    public function dropWebhook(): void
    {
        Http::timeout(10)->post($this->url('deleteWebhook'));
    }

    /** @param array<string, mixed>|null $markup */
    public function say(int $chatId, string $text, ?array $markup = null): void
    {
        Http::timeout(10)->post($this->url('sendMessage'), array_filter([
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => $markup === null ? null : json_encode($markup),
        ], fn ($value): bool => $value !== null));
    }

    /** @param array<string, mixed> $update */
    public function handle(array $update): void
    {
        if (is_array($update['callback_query'] ?? null)) {
            $this->handleConfirmation($update['callback_query']);

            return;
        }

        $message = $update['message'] ?? null;
        $from = $message['from'] ?? null;
        // Only a private chat with a person counts: a sign-in must not be confirmed from a group or by another bot.
        if (!is_array($message) || !is_array($from) || ($from['is_bot'] ?? false) || ($message['chat']['type'] ?? '') !== 'private') {
            return;
        }
        $chatId = (int) $message['chat']['id'];
        $text = trim((string) ($message['text'] ?? ''));

        if (preg_match('/^\/start(?:@\w+)?\s+([A-Za-z0-9_-]{16,64})$/', $text, $match) === 1) {
            $this->askConfirmation($chatId, $match[1]);

            return;
        }

        $this->say($chatId, 'Я нужен только для входа на сайт Whodoku. Нажмите «Войти через Telegram» на сайте, и я вас узнаю.');
    }

    /** Step 1: Start with the code. Nothing is signed in yet: the player is shown who asks and has to confirm. */
    private function askConfirmation(int $chatId, string $code): void
    {
        $pending = $this->requests->pending($code);
        if ($pending === null) {
            $this->say($chatId, 'Эта ссылка для входа устарела. Вернитесь на сайт и нажмите «Войти через Telegram» ещё раз.');

            return;
        }

        $where = $pending['ip'] === '' ? $pending['device'] : "{$pending['device']}, IP {$pending['ip']}";
        $this->say(
            $chatId,
            "Вход на сайт Whodoku запрошен с устройства: {$where}.\n\nЕсли это вы, нажмите «Подтвердить». Если ссылку вам прислал кто-то другой, не подтверждайте: иначе этот человек войдёт под вашим именем.",
            ['inline_keyboard' => [[['text' => 'Подтвердить вход', 'callback_data' => self::CONFIRM_PREFIX.$code]]]],
        );
    }

    /**
     * Step 2: the button. Only a person in a private chat can confirm, and only for himself.
     *
     * @param  array<string, mixed>  $query
     */
    private function handleConfirmation(array $query): void
    {
        $from = $query['from'] ?? null;
        $chat = $query['message']['chat'] ?? null;
        $data = (string) ($query['data'] ?? '');
        if (!is_array($from) || !is_array($chat) || ($from['is_bot'] ?? false) || ($chat['type'] ?? '') !== 'private'
            || preg_match('/^'.self::CONFIRM_PREFIX.'([A-Za-z0-9_-]{16,64})$/', $data, $match) !== 1) {
            return;
        }

        $identity = new TelegramIdentity((int) $from['id'], $from['first_name'] ?? null, $from['username'] ?? null);
        $done = $this->requests->confirm($match[1], $identity);
        if (isset($query['id'])) {
            Http::timeout(10)->post($this->url('answerCallbackQuery'), ['callback_query_id' => $query['id']]);
        }
        // The request is still confirmed: the site then answers the page with «заблокирован», and the bot must say the same.
        // The connection is named: the bot also runs from tests and commands that keep the SelfBio database as the default.
        $banned = $done && Player::on('whodoku')->where('telegram_id', $identity->id)->whereNotNull('banned_at')->exists();
        $this->say((int) $chat['id'], match (true) {
            $banned => 'Этот аккаунт заблокирован, войти на сайт не получится.',
            $done => 'Готово, вы вошли. Вернитесь на сайт: страница обновится сама.',
            default => 'Эта ссылка для входа устарела или уже использована. Вернитесь на сайт и нажмите «Войти через Telegram» ещё раз.',
        });
    }

    private function url(string $method): string
    {
        return "https://api.telegram.org/bot{$this->token}/{$method}";
    }
}
