import { beforeEach, describe, expect, it, vi } from 'vitest';
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
    Link: {
        props: ['href'],
        template: '<a :href="href"><slot /></a>',
    },
    useForm: () => formState,
}));

import LandingSignIn from './LandingSignIn.vue';

const mountForm = (props = {}) => mount(LandingSignIn, {
    props: { appName: 'TaskFlow', ...props },
});

beforeEach(() => {
    formState.email = '';
    formState.password = '';
    formState.remember = false;
    formState.errors = {};
    formState.processing = false;
    formState.post.mockReset();
});

describe('LandingSignIn.vue', () => {
    it('labels the credentials it asks for', () => {
        const wrapper = mountForm();

        expect(wrapper.get('input[name="email"]').attributes('type')).toBe('email');
        expect(wrapper.get('input[name="password"]').attributes('type')).toBe('password');
        expect(wrapper.text()).toContain('Sign in to TaskFlow');
    });

    it('posts the credentials to the login endpoint without leaving the page', async () => {
        const wrapper = mountForm();

        await wrapper.get('input[name="email"]').setValue('ada@example.com');
        await wrapper.get('input[name="password"]').setValue('correct-password');
        await wrapper.get('form').trigger('submit');

        expect(formState.email).toBe('ada@example.com');
        expect(formState.password).toBe('correct-password');
        expect(formState.post).toHaveBeenCalledWith('/login');
    });

    it('carries the remember choice', async () => {
        const wrapper = mountForm();

        await wrapper.get('input[name="remember"]').setValue(true);
        await wrapper.get('form').trigger('submit');

        expect(formState.remember).toBe(true);
    });

    it('shows the validation errors the server returned', () => {
        formState.errors = { email: 'These credentials do not match our records.' };

        const wrapper = mountForm();

        expect(wrapper.get('[role="alert"]').text())
            .toBe('These credentials do not match our records.');
        expect(wrapper.get('input[name="email"]').attributes('aria-invalid')).toBe('true');
    });

    it('locks the form while the request is in flight', () => {
        formState.processing = true;

        const wrapper = mountForm();

        expect(wrapper.get('input[name="email"]').attributes('disabled')).toBeDefined();
        expect(wrapper.get('input[name="password"]').attributes('disabled')).toBeDefined();
        expect(wrapper.get('button[type="submit"]').attributes('disabled')).toBeDefined();
        expect(wrapper.get('[data-test="button-spinner"]').exists()).toBe(true);
    });

    it('offers the password reset and registration flows', () => {
        const wrapper = mountForm();

        expect(wrapper.get('a[href="/forgot-password"]').text()).toBe('Forgot password?');
        expect(wrapper.get('a[href="/register"]').text()).toBe('Create an account');
    });
});
