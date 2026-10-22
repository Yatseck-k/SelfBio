<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Support\SiteName;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const string NEW_PASSWORD = 'a-brand-new-password-1';

    public function test_the_pages_are_public_and_not_for_search_bots(): void
    {
        $this->get('/forgot-password')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertInertia(fn (AssertableInertia $page) => $page->component('ForgotPassword', false));
        $this->get('/reset-password/some-token?email=a@example.test')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertInertia(fn (AssertableInertia $page) => $page->component('ResetPassword', false)->where('token', 'some-token')->where('email', 'a@example.test'));
        $this->assertStringContainsString('noindex', $this->get('/forgot-password')->getContent());
    }

    public function test_a_known_address_gets_the_letter_in_russian_with_the_site_name(): void
    {
        Notification::fake();
        config(['app.name' => 'Моя студия']);
        $user = User::factory()->create(['email' => 'anna@example.test']);

        $this->post('/forgot-password', ['email' => 'anna@example.test'])->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use ($user): bool {
            $mail = $notification->toMail($user);

            return $mail->subject === 'Сброс пароля — Моя студия'
                && str_contains($mail->actionUrl, '/reset-password/')
                && str_contains($mail->actionUrl, urlencode('anna@example.test'))
                && $mail->actionText === 'Задать новый пароль';
        });
    }

    public function test_an_unknown_address_gets_the_same_answer_and_no_letter(): void
    {
        Notification::fake();
        $known = User::factory()->create(['email' => 'anna@example.test']);

        $answerKnown = $this->post('/forgot-password', ['email' => 'anna@example.test'])->getSession()->get('status');
        RateLimiter::clear('x');
        $answerUnknown = $this->post('/forgot-password', ['email' => 'nobody@example.test'])->assertSessionHasNoErrors()->getSession()->get('status');

        $this->assertSame($answerKnown, $answerUnknown);
        Notification::assertSentToTimes($known, ResetPasswordNotification::class, 1);
        Notification::assertCount(1);
    }

    public function test_a_broken_mail_service_does_not_show_the_person_an_error_or_reveal_anything(): void
    {
        $user = User::factory()->create(['email' => 'anna@example.test']);
        config(['mail.default' => 'resend', 'services.resend.key' => null]);

        $this->post('/forgot-password', ['email' => 'anna@example.test'])->assertSessionHas('status')->assertSessionHasNoErrors();
        $this->assertNotNull($user->fresh());
    }

    public function test_the_link_sets_a_new_password_once(): void
    {
        $user = User::factory()->create(['email' => 'anna@example.test', 'password' => 'the-old-password-1']);
        $token = Password::createToken($user);

        $this->post('/reset-password', ['token' => $token, 'email' => 'anna@example.test', 'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD])
            ->assertRedirect('/login')->assertSessionHas('status');

        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->fresh()->password));
        // The link is spent.
        $this->post('/reset-password', ['token' => $token, 'email' => 'anna@example.test', 'password' => 'another-password-22', 'password_confirmation' => 'another-password-22'])
            ->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->fresh()->password));
        // And the new password signs in.
        $this->post('/login', ['email' => 'anna@example.test', 'password' => self::NEW_PASSWORD])->assertRedirect('/account');
    }

    public function test_a_wrong_token_a_short_password_and_a_mismatch_change_nothing(): void
    {
        $user = User::factory()->create(['email' => 'anna@example.test', 'password' => 'the-old-password-1']);
        $token = Password::createToken($user);
        $good = ['token' => $token, 'email' => 'anna@example.test', 'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD];

        $this->post('/reset-password', [...$good, 'token' => 'wrong'])->assertSessionHasErrors('email');
        $this->post('/reset-password', [...$good, 'password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');
        $this->post('/reset-password', [...$good, 'password_confirmation' => 'different-password-1'])->assertSessionHasErrors('password');
        $this->post('/reset-password', [...$good, 'email' => 'other@example.test'])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('the-old-password-1', $user->fresh()->password));
    }

    public function test_the_link_expires(): void
    {
        $user = User::factory()->create(['email' => 'anna@example.test']);
        $token = Password::createToken($user);
        $this->travel(61)->minutes();

        $this->post('/reset-password', ['token' => $token, 'email' => 'anna@example.test', 'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD])
            ->assertSessionHasErrors('email');
    }

    public function test_the_request_is_limited(): void
    {
        Notification::fake();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/forgot-password', ['email' => "u{$i}@example.test"])->assertSessionHas('status');
        }

        $this->post('/forgot-password', ['email' => 'u6@example.test'])->assertStatus(429);
    }

    public function test_requests_for_a_new_password_do_not_lock_the_sign_in(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'anna@example.test', 'password' => 'the-old-password-1']);
        for ($i = 0; $i < 5; $i++) {
            $this->post('/forgot-password', ['email' => 'anna@example.test'])->assertSessionHas('status');
        }
        $this->post('/forgot-password', ['email' => 'anna@example.test'])->assertStatus(429);

        $this->post('/login', ['email' => 'anna@example.test', 'password' => 'the-old-password-1'])->assertRedirect('/account');
        $this->assertAuthenticatedAs($user);
    }

    public function test_the_sign_in_page_shows_the_message_once(): void
    {
        $user = User::factory()->create(['email' => 'anna@example.test']);
        $token = Password::createToken($user);
        $this->post('/reset-password', ['token' => $token, 'email' => 'anna@example.test', 'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD]);

        $this->get('/login')->assertInertia(fn (AssertableInertia $page) => $page->where('status', 'Пароль изменён. Войдите с новым паролем.'));
        $this->get('/login')->assertInertia(fn (AssertableInertia $page) => $page->where('status', null));
    }

    public function test_a_signed_in_user_is_not_shown_the_forms(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/forgot-password')->assertRedirect();
    }

    public function test_the_mail_check_command_names_the_problems(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'mailpit', 'mail.from.address' => 'admin@local.test']);
        $this->artisan('mail:check')->expectsOutputToContain('mailpit')->assertExitCode(1);

        config(['mail.default' => 'array', 'mail.from.address' => 'no-reply@yatseck.ru']);
        $this->artisan('mail:check', ['to' => 'me@example.test'])->expectsOutputToContain('Письмо отправлено')->assertExitCode(0);
    }

    public function test_the_mail_check_command_notices_an_empty_resend_key(): void
    {
        // «RESEND_KEY=» in .env, as in .env.example, gives an empty string.
        config(['mail.default' => 'resend', 'services.resend.key' => '', 'mail.from.address' => 'no-reply@yatseck.ru']);

        $this->artisan('mail:check')->expectsOutputToContain('RESEND_KEY')->assertExitCode(1);
    }

    public function test_the_sender_follows_the_site_name_unless_it_was_set(): void
    {
        config(['app.name' => 'SelfBio', 'mail.from.name' => 'SelfBio']);
        SiteName::save('Моя студия');
        $this->assertSame('Моя студия', config('mail.from.name'));

        config(['app.name' => 'SelfBio', 'mail.from.name' => 'Служба заботы']);
        SiteName::save('Другое имя');
        $this->assertSame('Служба заботы', config('mail.from.name'));
    }

    public function test_the_notification_class_is_the_one_of_the_user(): void
    {
        $this->assertTrue(is_subclass_of(ResetPasswordNotification::class, ResetPassword::class));
    }
}
