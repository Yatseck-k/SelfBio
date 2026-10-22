import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import TelegramLogin from '../Components/TelegramLogin.vue';
import { playerState } from '../composables/usePlayer';

vi.mock('../composables/usePlayer', async (importOriginal) => ({
    ...await importOriginal<typeof import('../composables/usePlayer')>(),
    startBotLogin: vi.fn(),
    checkBotLogin: vi.fn(async () => 'pending'),
}));

const { startBotLogin } = await import('../composables/usePlayer');

describe('TelegramLogin in Safari', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        playerState.login = { bot_username: 'WhodokuBot', bot: true, dev: false };
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.restoreAllMocks();
    });

    it('opens the window inside the tap, before the request, and points it at the bot afterwards', async () => {
        const tab = { opener: {}, close: vi.fn(), location: { href: '' } };
        const open = vi.spyOn(globalThis, 'open').mockReturnValue(tab as unknown as Window);
        let finish: (value: { link: string; expires_in: number }) => void = () => undefined;
        vi.mocked(startBotLogin).mockReturnValue(new Promise((resolve) => { finish = resolve as typeof finish; }) as ReturnType<typeof startBotLogin>);

        const wrapper = mount(TelegramLogin);
        await wrapper.find('button').trigger('click');

        // The window exists while the request is still pending: that is what Safari needs.
        expect(open).toHaveBeenCalledTimes(1);
        expect(tab.location.href).toBe('');

        finish({ link: 'https://t.me/WhodokuBot?start=abc', expires_in: 300 });
        await flushPromises();

        expect(tab.location.href).toBe('https://t.me/WhodokuBot?start=abc');
        expect(tab.opener).toBeNull();
        expect(wrapper.find('a').attributes('href')).toBe('https://t.me/WhodokuBot?start=abc');
        wrapper.unmount();
    });

    it('closes the empty window when the request fails', async () => {
        const tab = { opener: {}, close: vi.fn(), location: { href: '' } };
        vi.spyOn(globalThis, 'open').mockReturnValue(tab as unknown as Window);
        vi.mocked(startBotLogin).mockResolvedValue('Не удалось начать вход.');

        const wrapper = mount(TelegramLogin);
        await wrapper.find('button').trigger('click');
        await flushPromises();

        expect(tab.close).toHaveBeenCalled();
        expect(wrapper.find('[role="alert"]').text()).toBe('Не удалось начать вход.');
    });

    it('keeps the visible link when the browser blocks the window', async () => {
        vi.spyOn(globalThis, 'open').mockReturnValue(null);
        vi.mocked(startBotLogin).mockResolvedValue({ link: 'https://t.me/WhodokuBot?start=xyz', expires_in: 300 } as Awaited<ReturnType<typeof startBotLogin>>);

        const wrapper = mount(TelegramLogin);
        await wrapper.find('button').trigger('click');
        await flushPromises();

        expect(wrapper.find('a').attributes('href')).toBe('https://t.me/WhodokuBot?start=xyz');
        wrapper.unmount();
    });

    it('does not start a second sign-in on a double tap', async () => {
        vi.mocked(startBotLogin).mockClear();
        const open = vi.spyOn(globalThis, 'open').mockReturnValue({ opener: {}, close: vi.fn(), location: { href: '' } } as unknown as Window);
        let finish: (value: { link: string; expires_in: number }) => void = () => undefined;
        vi.mocked(startBotLogin).mockReturnValue(new Promise((resolve) => { finish = resolve as typeof finish; }) as ReturnType<typeof startBotLogin>);

        const wrapper = mount(TelegramLogin);
        await wrapper.find('button').trigger('click');
        await wrapper.find('button').trigger('click');
        finish({ link: 'https://t.me/WhodokuBot?start=abc', expires_in: 300 });
        await flushPromises();

        expect(open).toHaveBeenCalledTimes(1);
        expect(startBotLogin).toHaveBeenCalledTimes(1);
        wrapper.unmount();
    });
});
