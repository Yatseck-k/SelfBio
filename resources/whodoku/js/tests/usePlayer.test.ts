import { afterEach, describe, expect, it, vi } from 'vitest';
import { apiHeaders, loadPlayer, playerState, signInWithTelegram } from '../composables/usePlayer';

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

        expect(await signInWithTelegram({ id: 1, hash: 'x' })).toBeNull();
        expect(playerState.player?.id).toBe(7);
    });

    it('returns the message when Telegram data is refused', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false, status: 422, json: async () => ({ message: 'Не удалось подтвердить вход.' }) }));

        expect(await signInWithTelegram({})).toBe('Не удалось подтвердить вход.');
        expect(playerState.player).toBeNull();
    });

    it('stays a guest when the server cannot be reached', async () => {
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('offline')));

        await loadPlayer(true);

        expect(playerState.loaded).toBe(true);
        expect(playerState.player).toBeNull();
    });
});
