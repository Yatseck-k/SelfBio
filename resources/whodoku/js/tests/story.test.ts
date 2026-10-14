import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import EndingCard from '../Components/EndingCard.vue';
import StoryPage from '../Pages/StoryPage.vue';
import { endless, playNext } from '../composables/useEndless';
import { playerState } from '../composables/usePlayer';

const chapter = (number: number, state: 'locked' | 'open' | 'solved', extra: Record<string, unknown> = {}) => ({
    number, title: `Глава ${number}`, rank: 'Стажёр', difficulty: 1, state, briefing: state === 'locked' ? null : `Вступление ${number}`,
    speaker: state === 'locked' ? null : { key: 'lesnikova', name: 'Агата Лесникова', role: 'Старший следователь', avatar: 'v1;g=f;skin=1;hair=1;style=2;shirt=3;extra=glasses' },
    outro: state === 'solved' ? `Конец ${number}` : null, lesson: null, grant: 1, slug: state === 'locked' ? null : `case-${number}`, built: true, ...extra,
});

afterEach(() => {
    vi.unstubAllGlobals();
    playerState.player = null;
    playerState.loaded = false;
    endless.busy = false;
    endless.error = '';
});

describe('story page', () => {
    it('shows the path with the state of every chapter and hides what is not reached', async () => {
        vi.stubGlobal('fetch', vi.fn(async (url: string) => ({
            ok: true, status: 200,
            json: async () => (url.includes('/player/me') ? { player: null, login: { bot_username: null, dev: true } } : { signed_in: true, chapters: [chapter(1, 'solved'), chapter(2, 'open'), chapter(3, 'locked')] }),
        })));

        const wrapper = mount(StoryPage);
        await flushPromises();

        const items = wrapper.findAll('.story-chapter');
        expect(items.map((item) => item.classes().find((name) => name.startsWith('is-')))).toEqual(['is-solved', 'is-open', 'is-locked']);
        expect(items[0].find('.story-outro').text()).toBe('Конец 1');
        expect(items[0].find('a.primary-action').text()).toContain('снова');
        expect(items[1].find('a.primary-action').attributes('href')).toBe('/puzzles/case-2');
        expect(items[2].find('.story-briefing').text()).toContain('Откроется после предыдущей главы');
        expect(items[2].find('.story-speaker').exists()).toBe(false);
        expect(items[1].find('.story-speaker strong').text()).toBe('Агата Лесникова');
        expect(items[1].find('.story-speaker svg').exists()).toBe(true);
        expect(items[2].find('a.primary-action').exists()).toBe(false);
    });

    it('groups the chapters by act and shows the rank that only the campaign gives', async () => {
        vi.stubGlobal('fetch', vi.fn(async (url: string) => ({
            ok: true, status: 200,
            json: async () => (url.includes('/player/me') ? { player: null, login: { bot_username: null, dev: true } } : {
                signed_in: true, solved: 1, total: 3,
                rank: { level: 1, title: 'Стажёр', chapters_from: 0, next_title: 'Практикант', next_at: 2, progress: 0.5 },
                acts: [{ title: 'Акт I', from: 1, to: 2, about: 'Обучение' }, { title: 'Акт II', from: 3, to: 3, about: 'Дела' }],
                chapters: [chapter(1, 'solved'), chapter(2, 'open'), chapter(3, 'locked')],
            }),
        })));

        const wrapper = mount(StoryPage);
        await flushPromises();

        expect(wrapper.findAll('.story-act-heading h2').map((item) => item.text())).toEqual(['Акт I', 'Акт II']);
        expect(wrapper.findAll('.story-act').map((act) => act.findAll('.story-chapter').length)).toEqual([2, 1]);
        const summary = wrapper.find('.story-summary');
        expect(summary.text()).toContain('Звание: Стажёр');
        expect(summary.text()).toContain('Пройдено глав: 1 из 3');
        expect(summary.text()).toContain('До звания «Практикант» — 1 глава');
        expect(wrapper.find('.story-chapter.is-solved details summary').text()).toBe('Прочитать главу');
    });

    it('asks a guest to sign in', async () => {
        vi.stubGlobal('fetch', vi.fn(async (url: string) => ({
            ok: true, status: 200,
            json: async () => (url.includes('/player/me') ? { player: null, login: { bot_username: null, dev: true } } : { signed_in: false, chapters: [chapter(1, 'locked')] }),
        })));

        const wrapper = mount(StoryPage);
        await flushPromises();

        expect(wrapper.text()).toContain('Сюжет открывается после входа');
    });
});

describe('ending of a story chapter', () => {
    const props = {
        ending: { culprit: 'Борис', lines: [] } as never, type: 'murder', tokens: [], scene: null, reward: null, guest: false,
        chapter: { number: 2, title: 'Чужая визитка', outro: 'Визитка остаётся у Лесниковой.', last: false, speaker: { key: 'lesnikova', name: 'Агата Лесникова', role: 'Старший следователь', avatar: 'v1;g=f;skin=1;hair=1;style=2;shirt=3;extra=glasses' } },
    };

    it('tells the closing lines and leads to the next chapter or the next case', async () => {
        const wrapper = mount(EndingCard, { props });

        expect(wrapper.find('.ending-chapter').text()).toContain('Визитка остаётся у Лесниковой.');
        expect(wrapper.find('.ending-chapter .story-speaker').text()).toContain('Старший следователь');
        expect(wrapper.find('.ending-next a.primary-action').text()).toContain('Следующая глава');
        await wrapper.find('.ending-next button').trigger('click');
        expect(wrapper.emitted('next')).toHaveLength(1);
    });

    it('closes the story after the last chapter and keeps the free play for guests out', () => {
        const last = mount(EndingCard, { props: { ...props, chapter: { ...props.chapter, last: true } } });
        expect(last.find('.ending-next a.primary-action').text()).toContain('К пути детектива');

        const guest = mount(EndingCard, { props: { ...props, guest: true, chapter: null } });
        expect(guest.find('.ending-next button').exists()).toBe(false);
        expect(guest.find('.ending-chapter').exists()).toBe(false);
    });
});

describe('free play', () => {
    it('opens the case the server chose', async () => {
        const assign = vi.fn();
        vi.stubGlobal('location', { assign });
        vi.stubGlobal('fetch', vi.fn(async () => ({ ok: true, status: 200, json: async () => ({ slug: 'next case' }) })));

        expect(await playNext()).toBeNull();
        expect(assign).toHaveBeenCalledWith('/puzzles/next%20case');
    });

    it('shows why there is no next case', async () => {
        vi.stubGlobal('fetch', vi.fn(async () => ({ ok: false, status: 401, json: async () => ({ message: 'Нужен вход.' }) })));

        expect(await playNext()).toBe('Нужен вход.');
        expect(endless.error).toBe('Нужен вход.');
        expect(endless.busy).toBe(false);
    });
});
