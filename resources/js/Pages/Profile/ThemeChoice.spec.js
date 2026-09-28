import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import ThemeChoice from './ThemeChoice.vue';

const { patch } = vi.hoisted(() => ({ patch: vi.fn() }));

vi.mock('@inertiajs/vue3', () => ({
    router: { patch },
}));

const mountChoice = (props = {}) =>
    mount(ThemeChoice, { props: { modelValue: 'light', ...props } });

describe('ThemeChoice.vue', () => {
    beforeEach(() => {
        patch.mockClear();
        document.documentElement.classList.remove('dark');
    });

    it('offers the light and dark themes', () => {
        const wrapper = mountChoice();

        const options = wrapper.findAll('input[type="radio"]');

        expect(options).toHaveLength(2);
        expect(options.map((option) => option.attributes('value'))).toEqual(['light', 'dark']);
    });

    it('checks the theme of the account', () => {
        expect(mountChoice().find('input[value="light"]').element.checked).toBe(true);
        expect(mountChoice({ modelValue: 'dark' }).find('input[value="dark"]').element.checked).toBe(true);
    });

    it('falls back to the light theme for an unsupported value', () => {
        const wrapper = mountChoice({ modelValue: 'neon' });

        expect(wrapper.find('input[value="light"]').element.checked).toBe(true);
    });

    it('applies and persists the chosen theme', async () => {
        const wrapper = mountChoice();

        await wrapper.find('input[value="dark"]').setValue(true);

        expect(wrapper.emitted('update:modelValue')).toEqual([['dark']]);
        expect(patch).toHaveBeenCalledWith(
            '/theme',
            { theme: 'dark' },
            { preserveScroll: true, preserveState: true },
        );
        expect(document.documentElement.classList.contains('dark')).toBe(true);
    });

    it('switches back to the light theme', async () => {
        document.documentElement.classList.add('dark');
        const wrapper = mountChoice({ modelValue: 'dark' });

        await wrapper.find('input[value="light"]').setValue(true);

        expect(patch).toHaveBeenCalledWith(
            '/theme',
            { theme: 'light' },
            { preserveScroll: true, preserveState: true },
        );
        expect(document.documentElement.classList.contains('dark')).toBe(false);
    });

    it('groups the options under a single label', () => {
        const wrapper = mountChoice();

        expect(wrapper.find('fieldset').exists()).toBe(true);
    });
});
