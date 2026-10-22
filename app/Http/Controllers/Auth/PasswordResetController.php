<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/** A new password of the personal account by a link in a letter. */
class PasswordResetController extends Controller
{
    public const int MIN_PASSWORD = 12;

    public function create(): Response
    {
        return Inertia::render('ForgotPassword');
    }

    /** The answer is the same for a known and an unknown address: the form must not tell who has an account. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);

        try {
            Password::sendResetLink(['email' => $data['email']]);
        } catch (Throwable $e) {
            // The mail service may be down or not set up: the person gets the same answer, the reason goes to the log.
            report($e);
        }

        return back()->with('status', 'Если этот адрес есть в системе, мы отправили на него письмо со ссылкой. Она действует '.config('auth.passwords.users.expire').' минут.');
    }

    public function edit(Request $request, string $token): Response
    {
        return Inertia::render('ResetPassword', ['token' => $token, 'email' => (string) $request->query('email', '')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:'.self::MIN_PASSWORD, 'confirmed'],
        ]);

        $status = Password::reset(
            $data,
            function (CanResetPassword $user, string $password): void {
                // The remember token changes too: the old «remember me» cookies stop working.
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'Ссылка недействительна или устарела. Запросите новую.']);
        }

        return to_route('login')->with('status', 'Пароль изменён. Войдите с новым паролем.');
    }
}
