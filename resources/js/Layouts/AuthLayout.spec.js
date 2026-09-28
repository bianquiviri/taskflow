import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';

vi.mock('@inertiajs/vue3', () => ({
    Link: {
        props: ['href'],
        template: '<a :href="href"><slot /></a>',
    },
}));

import AuthLayout from './AuthLayout.vue';

const mountLayout = (props = {}) => mount(AuthLayout, {
    props,
    slots: { default: '<p data-test="page">Sign in form</p>' },
});

describe('AuthLayout.vue', () => {
    it('frames the page and renders the slot', () => {
        const wrapper = mountLayout();

        expect(wrapper.get('main').get('[data-test="page"]').text()).toBe('Sign in form');
        expect(wrapper.get('main').attributes('class')).toContain('max-w-md');
    });

    it('links the wordmark back to the app', () => {
        const wrapper = mountLayout();

        const wordmark = wrapper.get('a[aria-label="TaskFlow home"]');

        expect(wordmark.attributes('href')).toBe('/');
        expect(wordmark.text()).toBe('TaskFlow');
    });

    it('names the application when asked to', () => {
        expect(mountLayout({ appName: 'Acme Flow' }).get('a[aria-label="TaskFlow home"]').text())
            .toBe('Acme Flow');
    });

    it('keeps the backdrop out of the accessibility tree and out of the way of clicks', () => {
        const backdrop = mountLayout().get('main').element.previousElementSibling;

        expect(backdrop.getAttribute('aria-hidden')).toBe('true');
        expect(backdrop.className).toContain('pointer-events-none');
    });

    it('closes with the product tagline', () => {
        expect(mountLayout().text()).toContain('Project management, organized.');
    });
});
