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

import LoginForm from './LoginForm.vue';

const mountForm = (props = {}) => mount(LoginForm, { props });

beforeEach(() => {
    formState.email = '';
    formState.password = '';
    formState.remember = false;
    formState.errors = {};
    formState.processing = false;
    formState.post.mockReset();
});

describe('LoginForm.vue', () => {
    it('asks for the credentials the login endpoint expects', () => {
        const wrapper = mountForm();

        expect(wrapper.get('input[name="email"]').attributes('type')).toBe('email');
        expect(wrapper.get('input[name="email"]').attributes('autocomplete')).toBe('email');
        expect(wrapper.get('input[name="email"]').attributes('required')).toBeDefined();
        expect(wrapper.get('input[name="password"]').attributes('type')).toBe('password');
        expect(wrapper.get('input[name="password"]').attributes('autocomplete')).toBe('current-password');
    });

    it('reveals the password on demand', async () => {
        const wrapper = mountForm();

        const password = wrapper.get('input[name="password"]');
        const toggle = wrapper.get('[data-test="password-toggle"]');

        expect(toggle.attributes('aria-pressed')).toBe('false');

        await toggle.trigger('click');

        expect(password.attributes('type')).toBe('text');
        expect(toggle.attributes('aria-pressed')).toBe('true');
    });

    it('posts the credentials and the remember choice to the login endpoint', async () => {
        const wrapper = mountForm();

        await wrapper.get('input[name="email"]').setValue('ada@example.com');
        await wrapper.get('input[name="password"]').setValue('correct-password');
        await wrapper.get('input[name="remember"]').setValue(true);
        await wrapper.get('form').trigger('submit');

        expect(formState.email).toBe('ada@example.com');
        expect(formState.password).toBe('correct-password');
        expect(formState.remember).toBe(true);
        expect(formState.post).toHaveBeenCalledWith('/login');
    });

    it('does not autofocus the email field unless it is asked for', () => {
        expect(mountForm().get('input[name="email"]').attributes('autofocus')).toBeUndefined();
        expect(mountForm({ autofocus: true }).get('input[name="email"]').attributes('autofocus')).toBeDefined();
    });

    it('shows the validation errors the server returned', () => {
        formState.errors = { email: 'These credentials do not match our records.' };

        const wrapper = mountForm();

        expect(wrapper.get('[role="alert"]').text()).toBe('These credentials do not match our records.');
        expect(wrapper.get('input[name="email"]').attributes('aria-invalid')).toBe('true');
    });

    it('locks the form while the request is in flight', () => {
        formState.processing = true;

        const wrapper = mountForm();

        expect(wrapper.get('input[name="email"]').attributes('disabled')).toBeDefined();
        expect(wrapper.get('input[name="password"]').attributes('disabled')).toBeDefined();
        expect(wrapper.get('input[name="remember"]').attributes('disabled')).toBeDefined();
        expect(wrapper.get('[data-test="password-toggle"]').attributes('disabled')).toBeDefined();
        expect(wrapper.get('button[type="submit"]').attributes('disabled')).toBeDefined();
        expect(wrapper.get('[data-test="button-spinner"]').exists()).toBe(true);
    });

    it('offers the password reset flow', () => {
        expect(mountForm().get('a[href="/forgot-password"]').text()).toBe('Forgot password?');
    });
});
