import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import ClueList from '../Components/ClueList.vue';
import PersonAvatar from '../Components/PersonAvatar.vue';
import { poseText } from '../composables/pose';
import NotebookPanel from '../Components/NotebookPanel.vue';
import DifficultyStars from '../Components/DifficultyStars.vue';
import CaseCard from '../Components/CaseCard.vue';
import AchievementDialog from '../Components/AchievementDialog.vue';
import DevelopmentPanel from '../Components/DevelopmentPanel.vue';
import PuzzleGrid from '../Components/PuzzleGrid.vue';
import { tutorialPuzzle } from '../composables/tutorialCase';

describe('PersonAvatar', () => {
    it('draws a portrait from the recipe', () => {
        const wrapper = mount(PersonAvatar, { props: { name: 'Борис Лисов', avatar: 'v1;g=m;skin=0;hair=1;style=0;shirt=1;extra=glasses' } });

        expect(wrapper.find('svg').exists()).toBe(true);
        expect(wrapper.findAll('circle').length).toBeGreaterThan(3);
    });

    it('uses an image when the avatar is an address', () => {
        const wrapper = mount(PersonAvatar, { props: { name: 'Борис', avatar: 'https://example.test/a.png' } });

        expect(wrapper.find('img').attributes('src')).toBe('https://example.test/a.png');
    });
});

describe('ClueList', () => {
    const props = { clues: tutorialPuzzle.clues, usedClues: [1], tokens: tutorialPuzzle.tokens, victim: tutorialPuzzle.victim, pinned: null };

    it('shows a card per statement with the speaker and the quote, and the card of the victim', () => {
        const wrapper = mount(ClueList, { props });

        expect(wrapper.findAll('.clue-card')).toHaveLength(3);
        expect(wrapper.find('.clue-card .clue-quote').text()).toBe('«Я стояла возле окна»');
        expect(wrapper.find('.victim-card').text()).toContain('Леонтий Арнаутов');
        expect(wrapper.findAll('.clue-card.is-used')).toHaveLength(1);
    });

    it('points at what a statement names and lets the player pin it', async () => {
        const wrapper = mount(ClueList, { props });

        await wrapper.findAll('.clue-card')[1].trigger('mouseenter');
        expect(wrapper.emitted('preview')?.[0]).toEqual([{ objects: ['armchair'], room: null, nearWindow: false }]);

        await wrapper.findAll('.clue-locate')[0].trigger('click');
        expect(wrapper.emitted('pin')?.[0]).toEqual([0]);
    });

    it('lists the conditions of the case apart and counts only statements', () => {
        const conditions = [
            { text: 'Из записей следователя: в тот вечер в каждой комнате был хотя бы один гость.', sort_order: 90, constraint_type: 'EVERY_ROOM_HAS_TOKEN', constraint: {} },
            { text: 'Из записей следователя: ни в одной комнате не было больше двух гостей.', sort_order: 91, constraint_type: 'ROOM_TOKEN_LIMIT', constraint: { max: 2 } },
        ];
        const wrapper = mount(ClueList, { props: { ...props, clues: [...tutorialPuzzle.clues, ...conditions], usedClues: [] } });

        expect(wrapper.findAll('.clue-card')).toHaveLength(3);
        expect(wrapper.find('.clue-progress').text()).toBe('0 из 3');
        const items = wrapper.findAll('.case-conditions li').map((item) => item.text());
        expect(items).toEqual(['В тот вечер в каждой комнате был хотя бы один гость.', 'Ни в одной комнате не было больше двух гостей.']);
    });

    it('gives no hint about the map when the ability is off', async () => {
        const wrapper = mount(ClueList, { props: { ...props, locate: false } });

        await wrapper.findAll('.clue-card')[1].trigger('mouseenter');
        await wrapper.findAll('.clue-card')[1].trigger('focusin');

        expect(wrapper.emitted('preview')).toBeUndefined();
        expect(wrapper.find('.clue-locate').exists()).toBe(false);
        expect(wrapper.find('.clue-note').exists()).toBe(false);
    });
});

describe('PuzzleGrid', () => {
    const base = { puzzle: tutorialPuzzle, notes: {}, selectedToken: null };

    it('crosses the lines of placed guests and keeps the placed guest clear', () => {
        const wrapper = mount(PuzzleGrid, { props: { ...base, placements: { anna: [1, 3] } } });
        const cells = wrapper.findAll('.map-cell');

        expect(cells[1 * 4 + 0].classes()).toContain('is-covered');
        expect(cells[3 * 4 + 3].classes()).toContain('is-covered');
        expect(cells[1 * 4 + 3].classes()).not.toContain('is-covered');
        expect(cells[2 * 4 + 0].classes()).not.toContain('is-covered');
    });

    it('does not cross any line while the auto-crossing is not learned', () => {
        const wrapper = mount(PuzzleGrid, { props: { ...base, placements: { anna: [1, 3] }, cover: false } });

        expect(wrapper.findAll('.map-cell.is-covered')).toHaveLength(0);
        expect(wrapper.findAll('.map-cell')[1 * 4 + 0].attributes('aria-label')).not.toContain('перекрыта');
    });

    it('shows the letters of guests marked as possible and outlines the helper candidates', () => {
        const wrapper = mount(PuzzleGrid, {
            props: { ...base, placements: {}, notes: { boris: { '0:2': 'possible' }, vera: { '0:2': 'possible' } }, candidates: new Set(['0:2']) },
        });
        const cell = wrapper.findAll('.map-cell')[2];

        expect(cell.find('.map-note-letters').text()).toBe('Б В');
        expect(cell.classes()).toContain('is-candidate');
    });

    it('shows a cross put without a guest, also while another guest is selected', () => {
        const wrapper = mount(PuzzleGrid, { props: { ...base, placements: {}, notes: { '*': { '1:1': 'excluded' } }, selectedToken: 'anna' } });
        const cell = wrapper.findAll('.map-cell')[1 * 4 + 1];

        expect(cell.classes()).toContain('is-excluded');
        expect(cell.text()).toContain('×');
    });

    it('marks the empty cell once the scene is given and draws the window', () => {
        const wrapper = mount(PuzzleGrid, {
            props: { ...base, placements: { anna: [1, 3], boris: [0, 2], vera: [3, 1] }, scene: { cell: [2, 0], icon: '❔', label: 'Пустая клетка' } },
        });

        expect(wrapper.findAll('.map-cell')[2 * 4 + 0].find('.map-scene').text()).toBe('❔');
        expect(wrapper.findAll('.map-cell')[1 * 4 + 3].find('.map-window.is-east').exists()).toBe(true);
    });
});

describe('DifficultyStars', () => {
    it('labels the level and fills that many stars', () => {
        const wrapper = mount(DifficultyStars, { props: { level: 3 } });

        expect(wrapper.attributes('aria-label')).toBe('Сложность: 3 из 5');
        expect(wrapper.findAll('.difficulty-star')).toHaveLength(5);
        expect(wrapper.findAll('.difficulty-star.is-on')).toHaveLength(3);
        expect(wrapper.text()).toContain('3/5');
    });
});

describe('pose', () => {
    it('lets a guest use the furniture instead of just standing on it', () => {
        expect(poseText({ type: 'bench', name: 'садовая скамья' })).toBe('Сидит: садовая скамья');
        expect(poseText({ type: 'bed', name: 'кровать' })).toBe('Лежит: кровать');
        expect(poseText({ type: 'rug', name: 'ковёр' })).toBe('Стоит: ковёр');
    });
});

describe('NotebookPanel', () => {
    const props = { notesOpen: true, helperOpen: true, markOpen: true, signedIn: true, helperOn: false, helperNote: '', canMark: false, canClear: false };

    it('opens the instruction in a window and closes it', async () => {
        const showModal = vi.fn();
        const close = vi.fn();
        HTMLDialogElement.prototype.showModal = showModal;
        HTMLDialogElement.prototype.close = close;
        const wrapper = mount(NotebookPanel, { props });

        expect(wrapper.find('.notebook-help-button').exists()).toBe(true);
        expect(wrapper.find('details').exists()).toBe(false);
        await wrapper.find('.notebook-help-button').trigger('click');
        expect(showModal).toHaveBeenCalledOnce();

        await wrapper.find('.notebook-dialog-close').trigger('click');
        expect(close).toHaveBeenCalledOnce();
    });

    it('is closed until expertise opens it, and says where to get it', () => {
        const guest = mount(NotebookPanel, { props: { ...props, notesOpen: false, helperOpen: false, markOpen: false, signedIn: false } });

        expect(guest.find('.notebook-locked').text()).toContain('Экспертность');
        expect(guest.find('.notebook-help-button').exists()).toBe(false);
        expect(guest.find('.notebook-locked a').text()).toBe('Войдите');

        const player = mount(NotebookPanel, { props: { ...props, notesOpen: false, signedIn: true } });
        expect(player.find('.notebook-locked a').text()).toBe('профиле');
    });

    it('keeps the helper and the mark button shut while they are not open', () => {
        const wrapper = mount(NotebookPanel, { props: { ...props, helperOpen: false, markOpen: false, helperOn: true, canMark: true } });

        expect(wrapper.find('input[type=checkbox]').attributes('disabled')).toBeDefined();
        expect(wrapper.find('.notebook-toggle').classes()).toContain('is-locked');
    });

    it('tells the page about the helper switch', async () => {
        const wrapper = mount(NotebookPanel, { props });
        await wrapper.find('input[type=checkbox]').trigger('change');

        expect(wrapper.emitted('toggleHelper')).toHaveLength(1);
    });
});

describe('CaseCard', () => {
    const base = { slug: 'x', title: 'Дело', description: 'Описание', type: 'murder', difficulty: 3, rows: 6, cols: 6, revision: 1 };

    it('opens an available case', () => {
        const wrapper = mount(CaseCard, { props: { puzzle: base, solved: false } });

        expect(wrapper.find('a.primary-action').attributes('href')).toBe('/puzzles/x');
        expect(wrapper.find('.case-locked').exists()).toBe(false);
    });

    it('shows a closed case with what opens it instead of the way in', () => {
        const puzzle = { ...base, locked: true, unlock: { skill: 'intellect', label: 'Интеллект', at: 5 } };
        const wrapper = mount(CaseCard, { props: { puzzle, solved: false } });

        expect(wrapper.find('a.primary-action').exists()).toBe(false);
        expect(wrapper.find('.case-locked').text()).toContain('Откроется при 5 очках навыка «Интеллект»');
        expect(wrapper.classes()).toContain('is-locked');
    });
});

describe('DevelopmentPanel', () => {
    const development = {
        free: 2, earned: 3,
        skills: [
            { key: 'intellect' as const, label: 'Интеллект', about: 'Дела', points: 1, next_at: 2, steps: [{ at: 2, title: 'Дела сложности 2', open: false }] },
            { key: 'expertise' as const, label: 'Экспертность', about: 'Блокнот', points: 1, next_at: null, steps: [{ at: 1, title: 'Блокнот', open: true }] },
        ],
        reset: { allowed: true, next_level: null },
        abilities: { difficulty: 1, hints: false, locate: false, notes: true, cover: false, helper: false, mark: false, autoplace: false, hint_stock: 0, hint_pace: 3, free_hints: 0, points_bonus: 0, hard_bonus: 0 },
    };
    const level = { level: 4, max: 100, points_from: 18, next_at: 24, progress: 0.5 };

    it('shows the level, the free points and what each skill opens', () => {
        const wrapper = mount(DevelopmentPanel, { props: { development, level } });

        expect(wrapper.find('.development-level').text()).toContain('4');
        expect(wrapper.find('.development-free').text()).toBe('2');
        expect(wrapper.findAll('.skill-card')).toHaveLength(2);
        expect(wrapper.findAll('.skill-steps li.is-open')).toHaveLength(1);
        expect(wrapper.text()).toContain('раз в 20 уровней');
    });

    it('disables the buttons when no point is free', () => {
        const wrapper = mount(DevelopmentPanel, { props: { development: { ...development, free: 0 }, level } });

        expect(wrapper.findAll('.skill-add').every((button) => button.attributes('disabled') !== undefined)).toBe(true);
    });
});

describe('AchievementDialog', () => {
    const player = { development: { free: 3 } } as never;
    const reward = (extra: Record<string, unknown>) => ({ points: 13, bonus: true, first: true, rank_up: false, player, ...extra }) as never;

    it('stays out of the way when the level did not change', () => {
        const wrapper = mount(AchievementDialog, { props: { reward: reward({ level_up: null, promotion: null }) } });

        expect(wrapper.find('.achievement-dialog').exists()).toBe(false);
    });

    it('announces the new level and the points to spend', () => {
        const showModal = vi.fn();
        HTMLDialogElement.prototype.showModal = showModal;
        const wrapper = mount(AchievementDialog, { props: { reward: reward({ level_up: { from: 4, to: 6, skill_points: 2 }, promotion: null }) } });

        expect(wrapper.find('.achievement-rank').text()).toBe('Уровень 6');
        expect(wrapper.find('.achievement-level').text()).toContain('+2 очка развития');
        expect(wrapper.find('.achievement-level').text()).toContain('свободных: 3');
        expect(wrapper.find('a.primary-action').attributes('href')).toBe('/profile');
        expect(wrapper.find('.achievement-dialog').classes()).not.toContain('is-scene');
    });

    it('plays the scene of the new rank', () => {
        const wrapper = mount(AchievementDialog, { props: { reward: reward({ rank_up: true, level_up: { from: 10, to: 11, skill_points: 1 }, promotion: { rank: 'Помощник детектива', text: 'Лесникова кладёт перед вами значок.', speaker: { key: 'lesnikova', name: 'Агата Лесникова', role: 'Старший следователь', avatar: 'v1;g=f;skin=1;hair=1;style=2;shirt=3;extra=glasses' } } }) } });

        expect(wrapper.find('.achievement-rank').text()).toBe('Помощник детектива');
        expect(wrapper.find('.achievement-scene').text()).toContain('Лесникова');
        expect(wrapper.find('.achievement-speaker strong').text()).toBe('Агата Лесникова');
        expect(wrapper.find('.achievement-dialog').classes()).toContain('is-scene');
        expect(wrapper.find('.achievement-level').text()).toContain('+1 очко развития');
    });
});
