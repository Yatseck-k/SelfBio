<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;
use Whodoku\Application\Player\Bot\TelegramBot;
use Whodoku\Models\Player;

class TelegramBotLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.telegram.bot_token' => '123:token',
            'services.telegram.bot_username' => 'WhodokuBot',
        ]);
    }

    protected function tearDown(): void
    {
        // The game keeps its own connection, which RefreshDatabase does not roll back.
        Player::on('whodoku')->where('telegram_id', 777)->delete();
        parent::tearDown();
    }

    /** @param array<string, mixed> $from */
    private function start(string $code, array $from = ['id' => 777, 'first_name' => 'Анна', 'username' => 'anna'], string $chat = 'private'): void
    {
        resolve(TelegramBot::class)->handle(['update_id' => 1, 'message' => [
            'text' => "/start {$code}", 'from' => $from, 'chat' => ['id' => $from['id'], 'type' => $chat],
        ]]);
    }

    /** @param array<string, mixed> $from */
    private function press(string $code, array $from = ['id' => 777, 'first_name' => 'Анна', 'username' => 'anna'], string $chat = 'private'): void
    {
        resolve(TelegramBot::class)->handle(['update_id' => 2, 'callback_query' => [
            'id' => 'cb1', 'from' => $from, 'data' => "login:{$code}", 'message' => ['chat' => ['id' => $from['id'], 'type' => $chat]],
        ]]);
    }

    public function test_player_signs_in_after_pressing_start_in_the_bot(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $request = $this->postJson('/player/login/bot')->assertOk()->json();
        $this->assertSame("https://t.me/WhodokuBot?start={$request['code']}", $request['link']);
        $credentials = ['code' => $request['code'], 'secret' => $request['secret']];

        $this->postJson('/player/login/bot/check', $credentials)->assertStatus(202);

        // Start alone signs nobody in: the bot shows who asks and waits for the button.
        $this->start($request['code']);
        Http::assertSent(fn ($sent) => str_contains($sent->url(), 'sendMessage') && str_contains($sent['text'], 'Подтвердить') && str_contains($sent['reply_markup'], 'login:'.$request['code']));
        $this->postJson('/player/login/bot/check', $credentials)->assertStatus(202);

        $this->press($request['code']);
        Http::assertSent(fn ($sent) => str_contains($sent->url(), 'sendMessage') && str_contains($sent['text'], 'вы вошли'));

        $this->postJson('/player/login/bot/check', $credentials)->assertOk()->assertJsonPath('player.first_name', 'Анна');
        $this->assertDatabaseHas('whodoku.players', ['telegram_id' => 777]);
        $this->assertAuthenticated('player');
        // The request is spent: it cannot be collected twice.
        $this->postJson('/player/login/bot/check', $credentials)->assertStatus(410);
    }

    public function test_link_without_the_browser_secret_does_not_sign_anyone_in(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        $request = $this->postJson('/player/login/bot')->json();
        $this->start($request['code']);
        $this->press($request['code']);

        $this->postJson('/player/login/bot/check', ['code' => $request['code'], 'secret' => str_repeat('a', 32)])->assertStatus(410);
        $this->assertGuest('player');
    }

    public function test_unknown_code_group_chat_and_bots_are_ignored(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        $request = $this->postJson('/player/login/bot')->json();

        $this->start($request['code'], chat: 'group');
        $this->start($request['code'], ['id' => 5, 'first_name' => 'Бот', 'is_bot' => true]);
        $this->start(str_repeat('f', 32));
        $this->press($request['code'], chat: 'group');
        $this->press($request['code'], ['id' => 5, 'first_name' => 'Бот', 'is_bot' => true]);

        $this->postJson('/player/login/bot/check', ['code' => $request['code'], 'secret' => $request['secret']])->assertStatus(202);
        $this->assertDatabaseMissing('whodoku.players', ['telegram_id' => 5]);
    }

    public function test_banned_player_cannot_sign_in(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        Player::on('whodoku')->updateOrCreate(['telegram_id' => 777], ['first_name' => 'Анна', 'banned_at' => now()]);
        $request = $this->postJson('/player/login/bot')->json();
        $this->start($request['code']);
        $this->press($request['code']);

        $this->postJson('/player/login/bot/check', ['code' => $request['code'], 'secret' => $request['secret']])->assertForbidden();
        $this->assertGuest('player');
        // The bot says the same as the site, not «вы вошли».
        Http::assertSent(fn ($sent) => str_contains($sent->url(), 'sendMessage') && str_contains($sent['text'], 'заблокирован'));
        Http::assertNotSent(fn ($sent) => str_contains($sent->url(), 'sendMessage') && str_contains($sent['text'], 'вы вошли'));
    }

    public function test_bot_login_is_off_without_a_token(): void
    {
        config(['services.telegram.bot_token' => '']);

        $this->postJson('/player/login/bot')->assertNotFound();
        $this->getJson('/player/me')->assertJsonPath('login.bot', false);
    }

    public function test_the_bot_tells_where_the_sign_in_is_asked_from(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $request = $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Version/17.0 Mobile/15E148 Safari/604.1'])
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])->postJson('/player/login/bot')->json();
        $this->start($request['code']);

        Http::assertSent(fn ($sent) => str_contains($sent->url(), 'sendMessage') && str_contains($sent['text'], 'iPhone · Safari, IP 203.0.113.7'));
    }

    public function test_a_stranger_who_presses_the_button_of_a_spent_request_gets_nothing(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        $request = $this->postJson('/player/login/bot')->json();
        $credentials = ['code' => $request['code'], 'secret' => $request['secret']];

        $this->start($request['code']);
        $this->press($request['code']);
        // A second person with the same link cannot take the request over, and it can be collected only once.
        $this->press($request['code'], ['id' => 888, 'first_name' => 'Чужой']);
        $this->postJson('/player/login/bot/check', $credentials)->assertOk()->assertJsonPath('player.first_name', 'Анна');
        $this->postJson('/player/login/bot/check', $credentials)->assertStatus(410);
        $this->assertDatabaseMissing('whodoku.players', ['telegram_id' => 888]);
    }

    public function test_the_token_never_reaches_a_log_line(): void
    {
        $this->assertSame(
            'cURL error 28 for https://api.telegram.org/bot<token>/getUpdates',
            TelegramBot::redact('cURL error 28 for https://api.telegram.org/bot8748426615:AAHgB-x_y1/getUpdates'),
        );
    }

    public function test_the_poll_command_waits_instead_of_crash_looping_without_a_token(): void
    {
        config(['services.telegram.bot_token' => '']);
        Sleep::fake();

        $this->artisan('telegram:poll')->assertExitCode(1);
        Sleep::assertSlept(fn ($duration): bool => $duration->totalSeconds >= 3600);
    }
}
