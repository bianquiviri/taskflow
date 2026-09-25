import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';

const { formState } = vi.hoisted(() => ({
    formState: {
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        remember: false,
        processing: false,
        errors: {},
        post: vi.fn(),
    },
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: {
        props: ['title'],
        template: '<div />',
    },
    Link: {
        props: ['href'],
        template: '<a :href="href"><slot /></a>',
    },
    useForm: (initial) => {
        Object.assign(formState, initial);
        return formState;
    },
}));

import ForgotPassword from './ForgotPassword.vue';
import Login from './Login.vue';
import Register from './Register.vue';
import ResetPassword from './ResetPassword.vue';
import VerifyEmail from './VerifyEmail.vue';

const global = {
    stubs: {
        AuthLayout: {
            template: '<main><slot /></main>',
        },
        InputError: {
            props: ['message'],
            template: '<p v-if="message" data-test="error">{{ message }}</p>',
        },
    },
};

beforeEach(() => {
    Object.assign(formState, {
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        remember: false,
        processing: false,
        errors: {},
    });
    formState.post.mockReset();
});

describe('authentication pages', () => {
    it('submits registration data', async () => {
        const wrapper = mount(Register, { props: { status: null }, global });

        await wrapper.find('input[name="name"]').setValue('Ada Lovelace');
        await wrapper.find('input[name="email"]').setValue('ada@example.com');
        await wrapper.find('input[name="password"]').setValue('correct-password');
        await wrapper.find('input[name="password_confirmation"]').setValue('correct-password');
        await wrapper.find('form').trigger('submit');

        expect(formState.name).toBe('Ada Lovelace');
        expect(formState.email).toBe('ada@example.com');
        expect(formState.post).toHaveBeenCalledWith('/register');
    });

    it('renders registration validation errors', () => {
        formState.errors = { email: 'The email has already been taken.' };
        const wrapper = mount(Register, { props: { status: null }, global });

        expect(wrapper.text()).toContain('The email has already been taken.');
    });

    it('submits login credentials and remember choice', async () => {
        const wrapper = mount(Login, { props: { status: null }, global });

        await wrapper.find('input[name="email"]').setValue('ada@example.com');
        await wrapper.find('input[name="password"]').setValue('correct-password');
        await wrapper.find('input[name="remember"]').setValue(true);
        await wrapper.find('form').trigger('submit');

        expect(formState.remember).toBe(true);
        expect(formState.post).toHaveBeenCalledWith('/login');
    });

    it('submits a password reset email request', async () => {
        const wrapper = mount(ForgotPassword, { props: { status: null }, global });

        await wrapper.find('input[name="email"]').setValue('ada@example.com');
        await wrapper.find('form').trigger('submit');

        expect(formState.post).toHaveBeenCalledWith('/forgot-password');
    });

    it('submits a new password with token and email props', async () => {
        const wrapper = mount(ResetPassword, {
            props: {
                email: 'ada@example.com',
                token: 'reset-token',
                status: null,
            },
            global,
        });

        expect(wrapper.find('input[name="token"]').element.value).toBe('reset-token');
        expect(wrapper.find('input[name="email"]').element.value).toBe('ada@example.com');

        await wrapper.find('input[name="password"]').setValue('new-password');
        await wrapper.find('input[name="password_confirmation"]').setValue('new-password');
        await wrapper.find('form').trigger('submit');

        expect(formState.post).toHaveBeenCalledWith('/reset-password');
    });

    it('requests another verification email', async () => {
        const wrapper = mount(VerifyEmail, { props: { status: null }, global });

        expect(wrapper.text()).toContain('Verify your email address');
        expect(wrapper.find('a[href="/logout"]').text()).toBe('Sign out');
        await wrapper.find('form').trigger('submit');

        expect(formState.post).toHaveBeenCalledWith('/email/verification-notification');
    });
});
