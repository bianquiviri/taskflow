import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';

vi.mock('@inertiajs/vue3', () => ({
    Link: {
        props: ['href'],
        template: '<a :href="href"><slot /></a>',
    },
}));

import LandingNav from './LandingNav.vue';

const mountNav = (props = {}) => mount(LandingNav, {
    props: { appName: 'TaskFlow', ...props },
});

describe('LandingNav.vue', () => {
    it('shows the product name and links back home', () => {
        const wrapper = mountNav();

        expect(wrapper.text()).toContain('TaskFlow');
        expect(wrapper.get('a[href="/"]')).toBeTruthy();
    });

    it('offers a way to sign in and a way to register', () => {
        const wrapper = mountNav();

        expect(wrapper.get('a[href="#signin"]').text()).toBe('Sign in');
        expect(wrapper.get('a[href="/register"]').text()).toBe('Get started');
    });

    it('labels the primary navigation', () => {
        const wrapper = mountNav();

        expect(wrapper.get('nav').attributes('aria-label')).toBe('Primary');
    });
});
