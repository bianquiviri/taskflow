import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';

const formState = vi.hoisted(() => ({
    email: '',
    password: '',
    remember: false,
    errors: {},
    processing: false,
    post: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: {
        props: ['title'],
        template: '<div data-test="head" />',
    },
    Link: {
        props: ['href'],
        template: '<a :href="href"><slot /></a>',
    },
    useForm: () => formState,
}));

import Welcome from './Welcome.vue';

const mountPage = (props = {}) => mount(Welcome, {
    props: { appName: 'TaskFlow', version: 'v13.0.0', ...props },
});

describe('Welcome.vue', () => {
    it('introduces the product with a single top-level heading', () => {
        const wrapper = mountPage();

        expect(wrapper.findAll('h1')).toHaveLength(1);
        expect(wrapper.get('h1').text()).toContain('Plan the work');
    });

    it('explains what the product is and how it works', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain('What is TaskFlow?');
        expect(wrapper.text()).toContain('How it works');
        expect(wrapper.text()).toContain('Projects');
        expect(wrapper.text()).toContain('Dashboard & metrics');
    });

    it('lets a visitor sign in without leaving the page', () => {
        const wrapper = mountPage();

        expect(wrapper.get('#signin')).toBeTruthy();
        expect(wrapper.get('#signin input[name="email"]')).toBeTruthy();
        expect(wrapper.get('#signin input[name="password"]')).toBeTruthy();
    });

    it('offers the registration and sign-in routes', () => {
        const wrapper = mountPage();

        expect(wrapper.get('a[href="/register"]')).toBeTruthy();
        expect(wrapper.find('a[href="/login"]').exists()).toBe(false);
        expect(wrapper.get('a[href="#signin"]')).toBeTruthy();
    });

    it('shows the running version in the footer', () => {
        const wrapper = mountPage({ version: 'v13.32.0' });

        expect(wrapper.text()).toContain('v13.32.0');
    });
});
