import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';

vi.mock('@inertiajs/vue3', () => ({
    Link: {
        props: ['href'],
        template: '<a :href="href"><slot /></a>',
    },
}));

import LandingHero from './LandingHero.vue';

const mountHero = (props = {}) => mount(LandingHero, {
    props: { appName: 'TaskFlow', ...props },
});

describe('LandingHero.vue', () => {
    it('explains the product in a single top-level heading', () => {
        const wrapper = mountHero();

        expect(wrapper.get('h1').text()).toContain('Plan the work');
        expect(wrapper.text()).toContain('TaskFlow');
    });

    it('offers a primary registration call to action', () => {
        const wrapper = mountHero();

        expect(wrapper.get('a[href="/register"]').text()).toBe('Create a free account');
    });

    it('points down to the feature section', () => {
        const wrapper = mountHero();

        expect(wrapper.get('a[href="#features"]').text()).toBe("See what's inside");
    });
});
