import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';

vi.mock('@inertiajs/vue3', () => ({
    Link: {
        props: ['href'],
        template: '<a :href="href"><slot /></a>',
    },
}));

import Button from './Button.vue';

describe('Button.vue', () => {
    it.each([
        ['primary', 'bg-brand-600'],
        ['secondary', 'border-line-strong'],
        ['danger', 'bg-danger'],
        ['ghost', 'text-content-muted'],
    ])('renders the %s variant', (variant, expectedClass) => {
        const wrapper = mount(Button, {
            props: { variant },
            slots: { default: 'Save' },
        });

        expect(wrapper.classes()).toContain(expectedClass);
    });

    it('uses a non-submitting button type by default', () => {
        const wrapper = mount(Button);

        expect(wrapper.attributes('type')).toBe('button');
    });

    it('disables the button and exposes its loading state', () => {
        const wrapper = mount(Button, {
            props: { loading: true },
            slots: { default: 'Saving' },
        });

        expect(wrapper.attributes('disabled')).toBeDefined();
        expect(wrapper.attributes('aria-busy')).toBe('true');
        expect(wrapper.get('[data-test="button-spinner"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Saving');
    });

    it('forwards click events', async () => {
        const onClick = vi.fn();
        const wrapper = mount(Button, {
            attrs: { onClick },
        });

        await wrapper.trigger('click');

        expect(onClick).toHaveBeenCalledOnce();
    });

    it('does not emit clicks while loading', async () => {
        const onClick = vi.fn();
        const wrapper = mount(Button, {
            props: { loading: true },
            attrs: { onClick },
        });

        await wrapper.trigger('click');

        expect(onClick).not.toHaveBeenCalled();
    });

    it('renders a link when href is provided', () => {
        const wrapper = mount(Button, {
            props: { href: '/register', variant: 'secondary' },
            slots: { default: 'Get started' },
        });

        expect(wrapper.element.tagName).toBe('A');
        expect(wrapper.attributes('href')).toBe('/register');
        expect(wrapper.attributes('type')).toBeUndefined();
        expect(wrapper.classes()).toContain('border-line-strong');
        expect(wrapper.text()).toContain('Get started');
    });

    it('renders a plain anchor for a same-page target', () => {
        const wrapper = mount(Button, {
            props: { href: '#signin' },
            slots: { default: 'Sign in' },
        });

        expect(wrapper.element.tagName).toBe('A');
        expect(wrapper.attributes('href')).toBe('#signin');
    });
});
