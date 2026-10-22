import { afterEach, describe, expect, it, vi } from 'vitest';
import { apiHeaders, checkBotLogin, loadPlayer, playerState, signInForTesting, startBotLogin } from '../composables/usePlayer';

afterEach(() => {
    vi.restoreAllMocks();
    document.cookie = 'XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 GMT';
    playerState.player = null;
    playerState.loaded = false;
});

describe('apiHeaders', () => {
    it('sends the CSRF token Laravel keeps in the XSRF-TOKEN cookie', () => {
        document.cookie = `XSRF-TOKEN=${encodeURIComponent('abc=def')}`;

        expect(apiHeaders()['X-XSRF-TOKEN']).toBe('abc=def');
        expect(apiHeaders().Accept).toBe('application/json');
    });

    it('works without the cookie', () => {
        expect(apiHeaders()['X-XSRF-TOKEN']).toBeUndefined();
    });
});

describe('signing in', () => {
    it('keeps the player the server answers with', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true, status: 200, json: async () => ({ player: { id: 7, points: 0 } }) }));

        expect(await signInForTesting()).toBeNull();
        expect(playerState.player?.id).toBe(7);
    });

    it('returns the message when the sign-in is refused', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false, status: 422, json: async () => ({ message: 'Не удалось подтвердить вход.' }) }));

        expect(await signInForTesting()).toBe('Не удалось подтвердить вход.');
        expect(playerState.player).toBeNull();
    });

    it('answers with a message instead of throwing when the bot sign-in cannot start offline', async () => {
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('offline')));

        expect(await startBotLogin()).toBe('Вход через Telegram недоступен.');
    });

    it('stays a guest when the server cannot be reached', async () => {
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('offline')));

        await loadPlayer(true);

        expect(playerState.loaded).toBe(true);
        expect(playerState.player).toBeNull();
    });
});

describe('waiting for the bot', () => {
    const request = { code: 'c', secret: 's', link: 'https://t.me/bot', expires_in: 300 };

    it('keeps waiting when the connection drops', async () => {
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('offline')));

        expect(await checkBotLogin(request)).toBe('pending');
    });

    it('keeps waiting while the site is in maintenance', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false, status: 503, json: async () => ({ message: 'Service Unavailable' }) }));

        expect(await checkBotLogin(request)).toBe('pending');
    });

    it('stops on a refusal', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false, status: 403, json: async () => ({ message: 'Этот аккаунт заблокирован.' }) }));

        expect(await checkBotLogin(request)).toBe('Этот аккаунт заблокирован.');
    });
});
