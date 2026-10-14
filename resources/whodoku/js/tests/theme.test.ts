import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import { initTheme, theme, toggleTheme } from '../composables/theme';
// @ts-expect-error -- a plain build script without type declarations
import { buildDark } from '../../tools/make-dark-theme.mjs';

describe('theme', () => {
    it('toggles, remembers and marks the page', () => {
        localStorage.clear();
        initTheme();
        expect(theme.value).toBe('dark');

        toggleTheme();
        expect(theme.value).toBe('light');
        expect(document.documentElement.dataset.theme).toBe('light');
        expect(localStorage.getItem('killdoku-theme')).toBe('light');

        initTheme();
        expect(theme.value).toBe('light');
        toggleTheme();
        expect(document.documentElement.dataset.theme).toBe('dark');
    });
});

describe('dark.css', () => {
    it('is up to date with app.css (run `npm run dark` after changing the colours)', () => {
        const app = readFileSync('resources/whodoku/css/app.css', 'utf8');
        const dark = readFileSync('resources/whodoku/css/dark.css', 'utf8');
        expect(dark).toBe(buildDark(app));
    });

    it('turns light surfaces dark and dark text light', () => {
        const css = buildDark('.card { background: #ffffff; color: #17212b; border: 1px solid #d7c7a6; }');
        const [, bg] = /background: (#[0-9a-f]{6})/.exec(css) ?? [];
        const [, text] = /color: (#[0-9a-f]{6})/.exec(css) ?? [];
        const lightness = (hex: string): number => (parseInt(hex.slice(1, 3), 16) + parseInt(hex.slice(3, 5), 16) + parseInt(hex.slice(5, 7), 16)) / 765;
        expect(lightness(bg)).toBeLessThan(0.25);
        expect(lightness(text)).toBeGreaterThan(0.75);
        expect(css).toContain(":root[data-theme='dark'] .card");
    });
});
