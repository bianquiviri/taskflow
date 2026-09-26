import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
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
});
