import { reactive } from 'vue';
import { apiHeaders } from './usePlayer';

/** The free play: «следующее дело» chosen on the server by the difficulty the player has reached. */
export const endless = reactive({ busy: false, error: '' });

/** Asks for the next case and opens it. @returns an error text, or null when the browser is going there */
export async function playNext(): Promise<string | null> {
    if (endless.busy) {
        return null;
    }
    endless.busy = true;
    endless.error = '';
    try {
        const response = await globalThis.fetch('/api/endless/next', { method: 'POST', headers: apiHeaders(), body: '{}' });
        const body = await response.json().catch(() => ({})) as { slug?: string; message?: string };
        if (!response.ok || !body.slug) {
            endless.error = body.message ?? (response.status === 429 ? 'Слишком много запросов. Подождите минуту.' : 'Не удалось подобрать дело.');

            return endless.error;
        }
        globalThis.location.assign(`/puzzles/${encodeURIComponent(body.slug)}`);

        return null;
    } catch {
        endless.error = 'Нет соединения. Попробуйте позже.';

        return endless.error;
    } finally {
        endless.busy = false;
    }
}
