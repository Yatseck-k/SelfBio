import { apiHeaders } from './usePlayer';
import type { PlacementMap } from '../types/puzzle';

export const INCIDENT_CATEGORIES = [
    { value: 'unsolvable', label: 'Дело нельзя решить' },
    { value: 'unclear', label: 'Непонятное показание' },
    { value: 'display', label: 'Ошибка на экране' },
    { value: 'other', label: 'Другое' },
] as const;

export type IncidentCategory = typeof INCIDENT_CATEGORIES[number]['value'];

export const INCIDENT_MESSAGE_MAX = 1000;

/** Sends the report of a player about a case; resolves to the thanks, or to the reason in words. */
export async function sendIncident(
    slug: string,
    report: { category: IncidentCategory; message: string; placements?: PlacementMap },
): Promise<{ ok: true; message: string } | { ok: false; error: string }> {
    try {
        const response = await globalThis.fetch(`/api/puzzles/${encodeURIComponent(slug)}/incidents`, {
            method: 'POST', headers: apiHeaders(), body: JSON.stringify(report),
        });
        const data = await response.json().catch(() => ({})) as { message?: string; errors?: Record<string, string[]> };
        if (response.ok) {
            return { ok: true, message: data.message ?? 'Спасибо! Мы проверим дело.' };
        }
        if (response.status === 429) {
            return { ok: false, error: 'Слишком много сообщений. Попробуйте чуть позже.' };
        }

        return { ok: false, error: Object.values(data.errors ?? {})[0]?.[0] ?? data.message ?? 'Не удалось отправить. Попробуйте позже.' };
    } catch {
        return { ok: false, error: 'Нет связи с сервером. Попробуйте позже.' };
    }
}
