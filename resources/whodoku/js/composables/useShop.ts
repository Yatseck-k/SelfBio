import { apiHeaders } from './usePlayer';
import type { ShopOffer } from '../types/puzzle';

export async function loadOffer(): Promise<ShopOffer | null> {
    try {
        const response = await globalThis.fetch('/api/shop', { headers: apiHeaders() });

        return response.ok ? await response.json() as ShopOffer : null;
    } catch {
        return null;
    }
}

/** Creates the order and returns the page of the payment system, or the reason in words. */
export async function startPurchase(product: string): Promise<{ url: string } | { error: string }> {
    try {
        const response = await globalThis.fetch('/api/shop/orders', {
            method: 'POST', headers: apiHeaders(), body: JSON.stringify({ product, consent: true }),
        });
        const data = await response.json().catch(() => ({})) as { confirmation_url?: string; message?: string };
        if (!response.ok || !data.confirmation_url) {
            return { error: data.message ?? 'Не удалось начать оплату. Попробуйте позже.' };
        }

        return { url: data.confirmation_url };
    } catch {
        return { error: 'Нет связи с сервером. Попробуйте позже.' };
    }
}

export interface OrderState {
    status: 'pending' | 'paid' | 'canceled' | 'refunded' | 'failed';
    quantity: number;
}

export async function loadOrder(order: string): Promise<OrderState | null> {
    try {
        const response = await globalThis.fetch(`/api/shop/orders/${encodeURIComponent(order)}`, { headers: apiHeaders() });

        return response.ok ? await response.json() as OrderState : null;
    } catch {
        return null;
    }
}
