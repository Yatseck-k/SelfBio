import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import ReportProblemDialog from '../Components/ReportProblemDialog.vue';
import { sendIncident } from '../composables/useIncident';

describe('report of a problem', () => {
    afterEach(() => {
        vi.restoreAllMocks();
    });

    const open = () => {
        HTMLDialogElement.prototype.showModal = vi.fn();
        return mount(ReportProblemDialog, { props: { modelValue: true, slug: 'case-1', placements: { a: [0, 1] } } });
    };

    it('does not send until the kind and a few words are given', async () => {
        const wrapper = open();
        await flushPromises();

        expect(wrapper.find('button[type="submit"]').attributes('disabled')).toBeDefined();
        await wrapper.find('input[value="unclear"]').setValue(true);
        await wrapper.find('textarea').setValue('нет');
        expect(wrapper.find('button[type="submit"]').attributes('disabled')).toBeDefined();
        await wrapper.find('textarea').setValue('непонятно, где окно у Станислава');
        expect(wrapper.find('button[type="submit"]').attributes('disabled')).toBeUndefined();
    });

    it('sends the words with the current board and thanks the player', async () => {
        const fetch = vi.fn().mockResolvedValue({ ok: true, status: 201, json: async () => ({ message: 'Спасибо! Мы проверим дело.' }) });
        vi.stubGlobal('fetch', fetch);
        const wrapper = open();
        await flushPromises();

        await wrapper.find('input[value="unclear"]').setValue(true);
        await wrapper.find('textarea').setValue('непонятно, где окно у Станислава');
        await wrapper.find('form').trigger('submit');
        await flushPromises();

        const [url, init] = fetch.mock.calls[0] as [string, RequestInit];
        expect(url).toBe('/api/puzzles/case-1/incidents');
        expect(JSON.parse(String(init.body))).toEqual({ category: 'unclear', message: 'непонятно, где окно у Станислава', placements: { a: [0, 1] } });
        expect(wrapper.find('.report-thanks').text()).toContain('Спасибо');
    });

    it('shows the reason when the server refuses', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false, status: 429, json: async () => ({}) }));

        const result = await sendIncident('case-1', { category: 'other', message: 'что-то не так' });

        expect(result).toEqual({ ok: false, error: 'Слишком много сообщений. Попробуйте чуть позже.' });
    });
});
