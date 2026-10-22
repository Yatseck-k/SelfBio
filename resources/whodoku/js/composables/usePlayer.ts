import { reactive } from 'vue';
import type { PlayerProfile } from '../types/puzzle';

interface PlayerState {
    loaded: boolean;
    player: PlayerProfile | null;
    login: { bot_username: string | null; bot: boolean; dev: boolean };
}

/** The signed-in player is shared by every page; guests simply have no player. */
export const playerState = reactive<PlayerState>({ loaded: false, player: null, login: { bot_username: null, bot: false, dev: false } });

/** Laravel puts the CSRF token into the XSRF-TOKEN cookie; sending it back proves the request comes from our pages. */
export function apiHeaders(): Record<string, string> {
    const cookie = document.cookie.split('; ').find((part) => part.startsWith('XSRF-TOKEN='));

    return {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        ...(cookie ? { 'X-XSRF-TOKEN': decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) } : {}),
    };
}

export async function loadPlayer(force = false): Promise<void> {
    if (playerState.loaded && !force) {
        return;
    }
    try {
        const response = await globalThis.fetch('/player/me', { headers: apiHeaders() });
        if (response.ok) {
            const payload = await response.json() as { player: PlayerProfile | null; login: PlayerState['login'] };
            playerState.player = payload.player;
            playerState.login = payload.login;
        }
    } catch {
        // Offline: the game stays playable as a guest.
    } finally {
        playerState.loaded = true;
    }
}

/** A request that never reached the server (offline, connection dropped) comes back as status 0 instead of throwing. */
async function post(path: string, body: unknown = {}): Promise<{ ok: boolean; status: number; data: Record<string, unknown> }> {
    let response: Response;
    try {
        response = await globalThis.fetch(path, { method: 'POST', headers: apiHeaders(), body: JSON.stringify(body) });
    } catch {
        return { ok: false, status: 0, data: {} };
    }
    const data = await response.json().catch(() => ({})) as Record<string, unknown>;

    return { ok: response.ok, status: response.status, data };
}

export interface BotLoginRequest {
    code: string;
    secret: string;
    link: string;
    expires_in: number;
}

/** Sign-in through the bot, step 1. @returns the link that opens the bot, or an error text */
export async function startBotLogin(): Promise<BotLoginRequest | string> {
    const result = await post('/player/login/bot');
    if (!result.ok) {
        return String(result.data.message ?? 'Вход через Telegram недоступен.');
    }

    return result.data as unknown as BotLoginRequest;
}

/**
 * Step 2: asks whether the player has pressed Start in the bot.
 * A lost connection or a server that is briefly down (503 while a deploy runs) counts as «still waiting»: the next poll retries.
 */
export async function checkBotLogin(request: BotLoginRequest): Promise<'pending' | 'signed-in' | string> {
    const result = await post('/player/login/bot/check', { code: request.code, secret: request.secret });
    if (result.status === 202 || result.status === 0 || result.status >= 500) {
        return 'pending';
    }
    if (!result.ok) {
        return String(result.data.message ?? 'Не удалось войти через Telegram.');
    }
    playerState.player = result.data.player as PlayerProfile;

    return 'signed-in';
}

export async function signInForTesting(): Promise<string | null> {
    const result = await post('/player/login/dev', { id: 1, name: 'Тестовый игрок' });
    if (!result.ok) {
        return String(result.data.message ?? 'Тестовый вход недоступен.');
    }
    playerState.player = result.data.player as PlayerProfile;

    return null;
}

export async function signOut(): Promise<void> {
    await post('/player/logout');
    playerState.player = null;
}

export interface DetectiveInput {
    name: string;
    gender: 'm' | 'f';
    skin: number;
    hair: number;
    style: number;
    shirt: number;
    extra: string;
}

/** @returns an error text for the name field, or null when the detective is saved */
export async function saveDetective(input: DetectiveInput): Promise<string | null> {
    const result = await post('/player/detective', input);
    if (!result.ok) {
        const errors = result.data.errors as { name?: string[] } | undefined;

        return errors?.name?.[0] ?? String(result.data.message ?? 'Не удалось сохранить детектива.');
    }
    playerState.player = result.data.player as PlayerProfile;

    return null;
}

/** Puts one development point into a skill. @returns an error text, or null when the point is spent */
export async function spendSkillPoint(skill: string): Promise<string | null> {
    const result = await post('/player/skills', { skill });
    if (!result.ok) {
        return String(result.data.message ?? 'Не удалось вложить очко.');
    }
    playerState.player = result.data.player as PlayerProfile;

    return null;
}

/** Takes the points back. @returns an error text, or null when they are free again */
export async function resetSkills(): Promise<string | null> {
    const result = await post('/player/skills/reset');
    if (!result.ok) {
        return String(result.data.message ?? 'Не удалось сбросить очки.');
    }
    playerState.player = result.data.player as PlayerProfile;

    return null;
}

/** Deletes the account of the signed-in player together with their results. @returns an error text, or null when it is gone */
export async function deleteAccount(): Promise<string | null> {
    const response = await globalThis.fetch('/player/account', { method: 'DELETE', headers: apiHeaders(), body: JSON.stringify({ confirm: true }) });
    if (!response.ok) {
        return 'Не удалось удалить аккаунт. Попробуйте ещё раз.';
    }
    playerState.player = null;

    return null;
}
