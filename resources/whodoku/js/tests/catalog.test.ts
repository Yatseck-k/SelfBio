import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import PuzzleCatalogPage from '../Pages/PuzzleCatalogPage.vue';
import { playerState } from '../composables/usePlayer';

const summary = (slug: string, extra: Record<string, unknown>) => ({
    slug, title: slug, description: null, type: 'murder', difficulty: 2, rows: 6, cols: 6, revision: 1, ...extra,
});

function stubServer(catalog: Record<string, unknown>, player: unknown = null): void {
    vi.stubGlobal('fetch', vi.fn(async (url: string) => ({
        ok: true,
        status: 200,
        json: async () => (url.includes('/player/me')
            ? { player, login: { bot_username: null, dev: true } }
            : catalog),
    })));
}

afterEach(() => {
    vi.unstubAllGlobals();
    playerState.player = null;
    playerState.loaded = false;
});

describe('catalog page', () => {
    it('divides the cases by difficulty, easiest first', async () => {
        stubServer({ data: [summary('hard', { difficulty: 4 }), summary('easy', { difficulty: 1 }), summary('easy2', { difficulty: 1 })], solved: [], locked: 0 });

        const wrapper = mount(PuzzleCatalogPage);
        await flushPromises();

        expect(wrapper.findAll('.level-heading h2').map((title) => title.text())).toEqual(['Уровень 1 · Разминка', 'Уровень 4 · Сложные']);
        expect(wrapper.findAll('.level-section').map((section) => section.findAll('.case-card').length)).toEqual([2, 1]);
        expect(wrapper.findAll('.level-chip').map((chip) => chip.text())).toEqual(['1. Разминка 2', '4. Сложные 1']);
    });

    it('tells a guest how many cases wait behind the sign-in and labels the cases', async () => {
        stubServer({
            data: [summary('today', { collection: 'daily', is_new: true, guest_access: false }), summary('guest', { collection: 'base', guest_access: true })],
            solved: [],
            locked: 7,
        });

        const wrapper = mount(PuzzleCatalogPage);
        await flushPromises();

        expect(wrapper.find('.locked-banner').text()).toContain('Ещё 7 дел ждут за входом');
        expect(wrapper.findAll('.level-heading h2').map((title) => title.text())).toEqual(['Новые сегодня', 'Уровень 2 · Лёгкие']);
        expect(wrapper.findAll('.level-chip')).toHaveLength(2);
        expect(wrapper.text()).toContain('Дело дня');
        expect(wrapper.text()).toContain('ГОСТЕВОЕ');
        expect(wrapper.findAll('.case-card')).toHaveLength(2);
    });

    it('shows signed-in players the server-side «solved» mark and no invitation', async () => {
        stubServer({ data: [summary('a', { collection: 'base', guest_access: false })], solved: ['a'], locked: 0 }, {
            id: 1, first_name: 'Аня', telegram_username: null, photo_url: null, detective: { name: 'Лис', avatar: null }, points: 0, solved: 1,
            rank: { level: 1, title: 'Стажёр', chapters_from: 0, next_title: null, next_at: null, progress: 0 }, hints: { available: 1, earned: 1, spent: 0, next_in: 3 },
        });

        const wrapper = mount(PuzzleCatalogPage);
        await flushPromises();

        expect(wrapper.find('.locked-banner').exists()).toBe(false);
        expect(wrapper.find('.case-card').classes()).toContain('is-solved');
        expect(wrapper.findAll('.level-heading h2').map((title) => title.text())).toEqual(['Уровень 2 · Лёгкие']);
        expect(wrapper.find('.level-nav').exists()).toBe(false);
        expect(wrapper.text()).toContain('Базовое');
    });
});
