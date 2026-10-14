import { reactive } from 'vue';

export interface AppearanceState {
    loaded: boolean;
    /** Effective image per slot (`kind:sofa`, `finding:murder`, `floor:спальня`, `seal:solved`): a built-in file or the administrator's upload. */
    images: Record<string, string>;
    /** Seal captions. */
    seals: Record<string, string>;
    animations: Record<string, boolean>;
}

export const SEAL_DEFAULTS: Record<string, string> = { solved: 'ДЕЛО ЗАКРЫТО', new: 'НОВОЕ', guest: 'ГОСТЕВОЕ', rank: 'НОВОЕ ЗВАНИЕ' };

export const appearance = reactive<AppearanceState>({ loaded: false, images: {}, seals: { ...SEAL_DEFAULTS }, animations: { enabled: true } });

/** The room floor slot is the lower-cased room name, the same as the administrator sees it. */
export const floorSlot = (roomName: string): string => `floor:${roomName.trim().toLocaleLowerCase('ru')}`;

export function slotImage(slot: string): string | null {
    return appearance.images[slot] ?? null;
}

export function sealText(code: string): string {
    return appearance.seals[code] ?? SEAL_DEFAULTS[code] ?? code;
}

/** Switches the animations on `<html>` so CSS can follow the administrator's choices. */
export function applyAnimationFlags(root: HTMLElement = document.documentElement): void {
    const on = appearance.animations.enabled !== false;
    root.dataset.anim = on ? 'on' : 'off';
    for (const [code, value] of Object.entries(appearance.animations)) {
        if (code !== 'enabled') root.dataset[`anim${code[0].toUpperCase()}${code.slice(1)}`] = value === false ? 'off' : 'on';
    }
}

let loading: Promise<void> | null = null;

export function loadAppearance(): Promise<void> {
    loading ??= (async () => {
        try {
            const response = await globalThis.fetch('/api/appearance', { headers: { Accept: 'application/json' } });
            if (response.ok) {
                const payload = await response.json() as Partial<Pick<AppearanceState, 'images' | 'seals' | 'animations'>>;
                appearance.images = payload.images ?? {};
                appearance.seals = { ...SEAL_DEFAULTS, ...(payload.seals ?? {}) };
                appearance.animations = { enabled: true, ...(payload.animations ?? {}) };
            }
        } catch {
            // Without the appearance data the built-in emoji and captions are used.
        } finally {
            appearance.loaded = true;
            applyAnimationFlags();
        }
    })();

    return loading;
}
