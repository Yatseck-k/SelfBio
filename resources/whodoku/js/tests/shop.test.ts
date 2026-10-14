import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import BuyHintsDialog from '../Components/BuyHintsDialog.vue';
import ShopPage from '../Pages/ShopPage.vue';
import ShopReturnPage from '../Pages/ShopReturnPage.vue';
import { playerState } from '../composables/usePlayer';

const product = { code: 'hints_5', title: '5 подсказок', hints: 5, price: '99' };

afterEach(() => {
    vi.unstubAllGlobals();
    vi.useRealTimers();
    playerState.player = null;
    playerState.loaded = false;
});

function stubDialog(): void {
    // jsdom has no <dialog>.showModal.
    HTMLDialogElement.prototype.showModal = vi.fn();
    HTMLDialogElement.prototype.close = vi.fn();
}

describe('buying hints', () => {
    it('asks for the consent, then goes to the page of the payment system', async () => {
        stubDialog();
        const assign = vi.fn();
        vi.stubGlobal('location', { assign });
        const fetch = vi.fn(async (url: string, init?: { method?: string; body?: string }) => ({
            ok: true, status: init?.method === 'POST' ? 201 : 200,
            json: async () => (init?.method === 'POST' ? { order: 'o1', confirmation_url: 'https://pay.example/1' } : { can_buy: true, reason: null, products: [product] }),
        }));
        vi.stubGlobal('fetch', fetch);

        const wrapper = mount(BuyHintsDialog, { props: { modelValue: false } });
        await wrapper.setProps({ modelValue: true });
        await flushPromises();

        expect(wrapper.find('h2').text()).toContain('99 ₽');
        expect(wrapper.find('.shop-alpha').exists()).toBe(false);
        const pay = wrapper.find('button.primary-action');
        expect(pay.attributes('disabled')).toBeDefined();

        await wrapper.find('input[type=checkbox]').setValue(true);
        await pay.trigger('click');
        await flushPromises();

        const post = fetch.mock.calls.find(([, init]) => init?.method === 'POST');
        expect(JSON.parse(post?.[1]?.body ?? '{}')).toEqual({ product: 'hints_5', consent: true });
        expect(assign).toHaveBeenCalledWith('https://pay.example/1');
    });

    it('tells in the alpha test that no real money is taken', async () => {
        stubDialog();
        vi.stubGlobal('fetch', vi.fn(async () => ({ ok: true, status: 200, json: async () => ({ can_buy: true, reason: null, test_mode: true, products: [product] }) })));

        const wrapper = mount(BuyHintsDialog, { props: { modelValue: false } });
        await wrapper.setProps({ modelValue: true });
        await flushPromises();

        expect(wrapper.find('.shop-alpha').text()).toContain('реальная оплата сейчас не применяется');
    });

    it('does not sell while the attention skill is closed and says why', async () => {
        stubDialog();
        vi.stubGlobal('fetch', vi.fn(async () => ({ ok: true, status: 200, json: async () => ({ can_buy: false, reason: 'hints_locked', products: [product] }) })));

        const wrapper = mount(BuyHintsDialog, { props: { modelValue: false } });
        await wrapper.setProps({ modelValue: true });
        await flushPromises();

        expect(wrapper.text()).toContain('Внимательность');
        expect(wrapper.find('button.primary-action').exists()).toBe(false);
    });

    it('shows the reason when the order is refused', async () => {
        stubDialog();
        vi.stubGlobal('fetch', vi.fn(async (_url: string, init?: { method?: string }) => init?.method === 'POST'
            ? { ok: false, status: 503, json: async () => ({ message: 'Оплата временно недоступна.' }) }
            : { ok: true, status: 200, json: async () => ({ can_buy: true, reason: null, products: [product] }) }));

        const wrapper = mount(BuyHintsDialog, { props: { modelValue: false } });
        await wrapper.setProps({ modelValue: true });
        await flushPromises();
        await wrapper.find('input[type=checkbox]').setValue(true);
        await wrapper.find('button.primary-action').trigger('click');
        await flushPromises();

        expect(wrapper.find('.shop-error').text()).toBe('Оплата временно недоступна.');
    });
});

describe('return page', () => {
    function answer(...statuses: string[]) {
        const queue = [...statuses];
        vi.stubGlobal('fetch', vi.fn(async (url: string) => ({
            ok: true, status: 200,
            json: async () => (url.includes('/player/me')
                ? { player: null, login: { bot_username: null, dev: true } }
                : { status: queue.length > 1 ? queue.shift() : queue[0], quantity: 5 }),
        })));
    }

    it('tells that the hints arrived', async () => {
        answer('paid');
        const wrapper = mount(ShopReturnPage, { props: { order: 'o1' } });
        await flushPromises();

        expect(wrapper.find('h1').text()).toBe('Оплата прошла');
        expect(wrapper.text()).toContain('5');
    });

    it('waits while the payment is processed and then shows the result', async () => {
        vi.useFakeTimers();
        answer('pending', 'paid');
        const wrapper = mount(ShopReturnPage, { props: { order: 'o1' } });
        await flushPromises();
        expect(wrapper.find('h1').text()).toBe('Проверяем оплату…');

        await vi.advanceTimersByTimeAsync(3100);
        await flushPromises();
        expect(wrapper.find('h1').text()).toBe('Оплата прошла');
    });

    it('says the payment was canceled', async () => {
        answer('canceled');
        const wrapper = mount(ShopReturnPage, { props: { order: 'o1' } });
        await flushPromises();

        expect(wrapper.find('h1').text()).toBe('Оплата отменена');
    });
});

describe('the shop page', () => {
    const stub = (offer: Record<string, unknown>) => vi.stubGlobal('fetch', vi.fn(async (url: string) => ({
        ok: true, status: 200,
        json: async () => (url.includes('/api/shop') ? offer : { player: null, login: { bot_username: null, dev: false } }),
    })));

    it('offers the pack with a buy button when the purchase is open', async () => {
        stubDialog();
        stub({ can_buy: true, reason: null, test_mode: true, products: [product] });

        const wrapper = mount(ShopPage);
        await flushPromises();

        expect(wrapper.text()).toContain('5 подсказок — 99 ₽');
        expect(wrapper.find('.shop-alpha').exists()).toBe(true);
        expect(wrapper.find('button.primary-action').text()).toContain('Купить за 99 ₽');
    });

    it('says which skill opens the purchase and links to the profile', async () => {
        stub({ can_buy: false, reason: 'hints_locked', products: [product] });

        const wrapper = mount(ShopPage);
        await flushPromises();

        expect(wrapper.text()).toContain('навык «Внимательность»');
        expect(wrapper.find('a.primary-action').attributes('href')).toBe('/profile');
        expect(wrapper.find('button.primary-action').exists()).toBe(false);
    });

    it('asks a guest to sign in', async () => {
        stub({ can_buy: false, reason: 'login_required', products: [product] });

        const wrapper = mount(ShopPage);
        await flushPromises();

        expect(wrapper.text()).toContain('Покупать могут вошедшие игроки');
    });
});
