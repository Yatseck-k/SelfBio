import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import CaseSeal from '../Components/CaseSeal.vue';
import SiteImage from '../Components/SiteImage.vue';
import { appearance, applyAnimationFlags, floorSlot, loadAppearance, SEAL_DEFAULTS } from '../composables/appearance';

afterEach(() => {
    appearance.images = {};
    appearance.seals = { ...SEAL_DEFAULTS };
    appearance.animations = { enabled: true };
});

describe('appearance', () => {
    it('shows the fallback until a slot has an image, then the image', () => {
        const wrapper = mount(SiteImage, { props: { name: 'kind:sofa', fallback: '🛋️' } });
        expect(wrapper.text()).toBe('🛋️');
        expect(wrapper.find('img').exists()).toBe(false);

        appearance.images = { 'kind:sofa': '/icons/kind/sofa.svg' };
        return wrapper.vm.$nextTick().then(() => {
            expect(wrapper.find('img').attributes('src')).toBe('/icons/kind/sofa.svg');
        });
    });

    it('prints the caption of a seal and its picture when one is uploaded', async () => {
        const wrapper = mount(CaseSeal, { props: { code: 'solved' } });
        expect(wrapper.text()).toBe('ДЕЛО ЗАКРЫТО');

        appearance.seals = { ...SEAL_DEFAULTS, solved: 'РАСКРЫТО' };
        appearance.images = { 'seal:solved': '/storage/appearance/s.svg' };
        await wrapper.vm.$nextTick();
        expect(wrapper.text()).toBe('РАСКРЫТО');
        expect(wrapper.find('img').attributes('src')).toBe('/storage/appearance/s.svg');
    });

    it('names the floor slot by the lower-cased room', () => {
        expect(floorSlot('  Зимний Сад ')).toBe('floor:зимний сад');
    });

    it('turns the switches into attributes of the page', () => {
        const root = document.createElement('html');
        appearance.animations = { enabled: true, place: true, reveal: false };
        applyAnimationFlags(root);
        expect(root.dataset.anim).toBe('on');
        expect(root.dataset.animPlace).toBe('on');
        expect(root.dataset.animReveal).toBe('off');

        appearance.animations = { enabled: false };
        applyAnimationFlags(root);
        expect(root.dataset.anim).toBe('off');
    });

    it('keeps the built-in look when the request fails', async () => {
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('offline')));
        await loadAppearance();
        expect(appearance.loaded).toBe(true);
        expect(appearance.seals.new).toBe('НОВОЕ');
        vi.unstubAllGlobals();
    });
});
