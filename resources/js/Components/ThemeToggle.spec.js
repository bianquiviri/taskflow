import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import ThemeToggle from './ThemeToggle.vue';

const { patch } = vi.hoisted(() => ({ patch: vi.fn() }));

vi.mock('@inertiajs/vue3', () => ({
    router: { patch },
}));

const IconStub = {
    props: ['name'],
    template: '<span data-test="icon">{{ name }}</span>',
};

const global = { stubs: { Icon: IconStub } };

describe('ThemeToggle.vue', () => {
    beforeEach(() => {
        patch.mockClear();
        document.documentElement.classList.remove('dark');
    });

    it('offers the dark theme while the light one is active', () => {
        const wrapper = mount(ThemeToggle, { props: { theme: 'light' }, global });

        expect(wrapper.find('[data-test="icon"]').text()).toBe('moon');
        expect(wrapper.attributes('aria-label')).toBe('Switch to dark mode');
    });

    it('offers the light theme while the dark one is active', () => {
        const wrapper = mount(ThemeToggle, { props: { theme: 'dark' }, global });

        expect(wrapper.find('[data-test="icon"]').text()).toBe('sun');
        expect(wrapper.attributes('aria-label')).toBe('Switch to light mode');
    });

    it('falls back to the light theme for an unsupported value', () => {
        const wrapper = mount(ThemeToggle, { props: { theme: 'neon' }, global });

        expect(wrapper.find('[data-test="icon"]').text()).toBe('moon');
    });

    it('applies the dark class and persists the choice', async () => {
        const wrapper = mount(ThemeToggle, { props: { theme: 'light' }, global });

        await wrapper.trigger('click');

        expect(patch).toHaveBeenCalledWith(
            '/theme',
            { theme: 'dark' },
            { preserveScroll: true, preserveState: true },
        );
        expect(document.documentElement.classList.contains('dark')).toBe(true);
    });

    it('drops the dark class when switching back to light', async () => {
        document.documentElement.classList.add('dark');
        const wrapper = mount(ThemeToggle, { props: { theme: 'dark' }, global });

        await wrapper.trigger('click');

        expect(patch).toHaveBeenCalledWith(
            '/theme',
            { theme: 'light' },
            { preserveScroll: true, preserveState: true },
        );
        expect(document.documentElement.classList.contains('dark')).toBe(false);
    });

    it('is a plain button that never submits a form', () => {
        const wrapper = mount(ThemeToggle, { props: { theme: 'light' }, global });

        expect(wrapper.element.tagName).toBe('BUTTON');
        expect(wrapper.attributes('type')).toBe('button');
    });
});
