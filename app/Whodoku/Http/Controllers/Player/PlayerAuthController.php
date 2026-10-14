<?php

declare(strict_types=1);

namespace Whodoku\Http\Controllers\Player;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Whodoku\Application\Player\InvalidTelegramLogin;
use Whodoku\Application\Player\PlayerAccountDeleter;
use Whodoku\Application\Player\PlayerPresenter;
use Whodoku\Application\Player\PlayerRegistry;
use Whodoku\Application\Player\TelegramIdentity;
use Whodoku\Application\Player\TelegramIdentityVerifier;
use Whodoku\Http\Controllers\Concerns\ResolvesPlayer;
use Whodoku\Http\Controllers\Controller;
use Whodoku\Models\Player;

class PlayerAuthController extends Controller
{
    use ResolvesPlayer;

    public function me(Request $request, PlayerPresenter $presenter): JsonResponse
    {
        $player = $this->player($request);
        if ($player !== null && ($player->last_seen_at === null || $player->last_seen_at->lt(now()->subMinutes(5)))) {
            $player->forceFill(['last_seen_at' => now()])->save();
        }

        return response()->json([
            'player' => $player === null ? null : $presenter->present($player),
            'login' => ['bot_username' => config('services.telegram.bot_username'), 'dev' => $this->devLoginEnabled()],
        ]);
    }

    public function telegram(Request $request, TelegramIdentityVerifier $verifier, PlayerRegistry $registry, PlayerPresenter $presenter): JsonResponse
    {
        try {
            $identity = $verifier->verify($request->only(['id', 'first_name', 'last_name', 'username', 'photo_url', 'auth_date', 'hash']));
        } catch (InvalidTelegramLogin) {
            return response()->json(['message' => 'Не удалось подтвердить вход через Telegram. Попробуйте ещё раз.'], 422);
        }

        return $this->signIn($request, $registry->fromIdentity($identity), $presenter);
    }

    /** A stand-in for the Telegram widget on a machine without a public domain. */
    public function dev(Request $request, PlayerRegistry $registry, PlayerPresenter $presenter): JsonResponse
    {
        abort_unless($this->devLoginEnabled(), 404);
        $data = $request->validate(['id' => ['nullable', 'integer', 'min:1', 'max:9999'], 'name' => ['nullable', 'string', 'max:40']]);

        // Test accounts get negative ids, which Telegram never uses.
        $identity = new TelegramIdentity(-(int) ($data['id'] ?? 1), $data['name'] ?? 'Тестовый игрок');

        return $this->signIn($request, $registry->fromIdentity($identity), $presenter);
    }

    /** The player deletes their own account: the results and hints go with it. */
    public function destroy(Request $request, PlayerAccountDeleter $deleter): JsonResponse
    {
        $player = $this->player($request);
        abort_if($player === null, 401);
        $request->validate(['confirm' => ['required', 'accepted']]);

        $deleter->delete($player);
        Auth::guard('player')->logout();
        $request->session()->regenerateToken();

        return response()->json(['player' => null]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('player')->logout();
        $request->session()->regenerateToken();

        return response()->json(['player' => null]);
    }

    private function signIn(Request $request, Player $player, PlayerPresenter $presenter): JsonResponse
    {
        if ($player->isBanned()) {
            return response()->json(['message' => 'Этот аккаунт заблокирован.'], 403);
        }
        Auth::guard('player')->login($player, true);
        $request->session()->regenerate();

        return response()->json(['player' => $presenter->present($player)]);
    }

    private function devLoginEnabled(): bool
    {
        return (bool) config('services.telegram.dev_login') && !app()->environment('production');
    }
}
