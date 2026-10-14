import { ref } from 'vue';

export type Theme = 'light' | 'dark';

const KEY = 'killdoku-theme';

export const theme = ref<Theme>('light');

function stored(): Theme | null {
    try {
        const value = globalThis.localStorage?.getItem(KEY);

        return value === 'dark' || value === 'light' ? value : null;
    } catch {
        return null;
    }
}

function apply(next: Theme): void {
    theme.value = next;
    document.documentElement.dataset.theme = next;
}

/** Use SelfBio's dark palette by default while keeping a visitor's saved choice. */
export function initTheme(): void {
    apply(stored() ?? 'dark');
}

export function toggleTheme(): void {
    const next: Theme = theme.value === 'dark' ? 'light' : 'dark';
    apply(next);
    try {
        globalThis.localStorage?.setItem(KEY, next);
    } catch {
        // The choice just lasts until the page is closed.
    }
}
