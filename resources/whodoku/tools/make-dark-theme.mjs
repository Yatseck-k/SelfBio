#!/usr/bin/env node
/**
 * Builds resources/css/dark.css from resources/css/app.css: for every rule that sets a colour, a copy under
 * `:root[data-theme='dark']` with the colours turned into their dark counterparts. The result is committed and a
 * Vitest test regenerates it and compares, so a change of app.css without `npm run dark` fails the build.
 *
 *   node resources/tools/make-dark-theme.mjs          # write
 *   import { buildDark } from this file             # (tests)
 */
import { readFileSync, writeFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import postcss from 'postcss';

const COLOR_PROPS = /^(color|background|background-color|background-image|border|border-(top|right|bottom|left)(-color)?|border-color|outline|outline-color|box-shadow|text-shadow|fill|stroke|caret-color|accent-color|text-decoration-color)$/;
const COLOR = /#[0-9a-fA-F]{3,8}\b|rgba?\([^)]*\)/g;

function parse(text) {
    if (text.startsWith('#')) {
        let hex = text.slice(1);
        if (hex.length <= 4) hex = [...hex].map((c) => c + c).join('');
        const n = (i) => parseInt(hex.slice(i, i + 2), 16);
        return { r: n(0), g: n(2), b: n(4), a: hex.length === 8 ? n(6) / 255 : 1 };
    }
    const parts = text.replace(/rgba?\(|\)/g, '').split(/[\s,/]+/).filter(Boolean);
    const num = (v) => (v.endsWith('%') ? (parseFloat(v) / 100) * 255 : parseFloat(v));
    const alpha = parts[3] === undefined ? 1 : parts[3].endsWith('%') ? parseFloat(parts[3]) / 100 : parseFloat(parts[3]);
    return { r: num(parts[0]), g: num(parts[1]), b: num(parts[2]), a: alpha };
}

function toHsl({ r, g, b }) {
    r /= 255; g /= 255; b /= 255;
    const max = Math.max(r, g, b); const min = Math.min(r, g, b);
    const l = (max + min) / 2; const d = max - min;
    if (d === 0) return { h: 0, s: 0, l };
    const s = d / (1 - Math.abs(2 * l - 1));
    const h = max === r ? ((g - b) / d) % 6 : max === g ? (b - r) / d + 2 : (r - g) / d + 4;
    return { h: (h * 60 + 360) % 360, s, l };
}

function fromHsl({ h, s, l }) {
    const c = (1 - Math.abs(2 * l - 1)) * s; const x = c * (1 - Math.abs(((h / 60) % 2) - 1)); const m = l - c / 2;
    const [r, g, b] = h < 60 ? [c, x, 0] : h < 120 ? [x, c, 0] : h < 180 ? [0, c, x] : h < 240 ? [0, x, c] : h < 300 ? [x, 0, c] : [c, 0, x];
    return [r, g, b].map((v) => Math.round((v + m) * 255));
}

/** kind: 'bg' | 'text' | 'line' | 'shadow' */
function dark(literal, kind) {
    const color = parse(literal);
    const hsl = toHsl(color);
    let { l } = hsl; let { s } = hsl;
    if (kind === 'shadow') return literal;
    if (kind === 'bg') {
        if (l > 0.5) { l = 0.1 + (1 - l) * 0.55; s *= 0.55; }
    } else if (kind === 'text') {
        if (l < 0.55) { l = 0.95 - l * 0.55; s *= 0.7; }
    } else if (l > 0.45) { l = 0.3 + (1 - l) * 0.3; s *= 0.5; }
    const [r, g, b] = fromHsl({ h: hsl.h, s, l });

    return color.a < 1 ? `rgb(${r} ${g} ${b} / ${Math.round(color.a * 1000) / 10}%)` : `#${[r, g, b].map((v) => v.toString(16).padStart(2, '0')).join('')}`;
}

/** Palette variables (custom properties of the case pages) have no colour property to read the role from. */
const VARIABLES = {
    '--case-paper': '#1b221e',
    '--case-paper-light': '#252d28',
    '--case-ink': '#ebe6d8',
    '--case-muted': '#b9c1ba',
    '--case-wine': '#8c2f42',
    '--case-wine-ink': '#e98ea0',
    '--case-wine-dark-ink': '#f2a9b6',
    '--case-gold': '#d9b062',
    '--case-green': '#1f3029',
    // The desk behind the page is darker than the page itself.
    '--desk-bg': '#151011',
};

function kindOf(prop) {
    if (/^(color|fill|stroke|caret-color|accent-color|text-decoration-color)$/.test(prop)) return 'text';
    if (/^(background|background-color|background-image)$/.test(prop)) return 'bg';
    if (/shadow/.test(prop)) return 'shadow';

    return 'line';
}

function darkSelector(selector) {
    const scope = ":root[data-theme='dark']";
    const trimmed = selector.trim();
    if (trimmed === ':root' || trimmed === 'html') return scope;

    return trimmed.startsWith(':root') ? trimmed.replace(':root', scope) : `${scope} ${trimmed}`;
}

export function buildDark(source) {
    const root = postcss.parse(source);
    const out = [];
    const emit = (rule, wrap) => {
        const declarations = [];
        rule.each((node) => {
            if (node.type === 'decl' && VARIABLES[node.prop] !== undefined) {
                declarations.push(`    ${node.prop}: ${VARIABLES[node.prop]};`);
            } else if (node.type === 'decl' && COLOR_PROPS.test(node.prop)) {
                const kind = kindOf(node.prop);
                const value = node.value.replace(COLOR, (literal) => dark(literal, kind));
                declarations.push(`    ${node.prop}: ${value}${node.important ? ' !important' : ''};`);
            }
        });
        if (declarations.length === 0) return;
        const selectors = rule.selectors.map(darkSelector).join(',\n');
        const block = `${selectors} {\n${declarations.join('\n')}\n}`;
        out.push(wrap ? `${wrap} {\n${block.replace(/^/gm, '    ')}\n}` : block);
    };
    root.each((node) => {
        if (node.type === 'rule' && !node.selector.startsWith('@')) emit(node, null);
        if (node.type === 'atrule' && node.name === 'media' && !/prefers-/.test(node.params)) {
            node.each((child) => { if (child.type === 'rule') emit(child, `@media ${node.params}`); });
        }
    });

    return `/* Generated by resources/tools/make-dark-theme.mjs from app.css. Do not edit: run \`npm run dark\`. */\n${out.join('\n\n')}\n\n:root[data-theme='dark'] { color-scheme: dark; }\n`;
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
    const css = readFileSync(new URL('../css/app.css', import.meta.url), 'utf8');
    writeFileSync(new URL('../css/dark.css', import.meta.url), buildDark(css));
    console.log('dark.css written');
}
