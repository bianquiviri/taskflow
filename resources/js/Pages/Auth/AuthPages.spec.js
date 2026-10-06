import { beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import { mount } from '@vue/test-utils';

const { formState } = await vi.hoisted(async () => {
    const { reactive } = await import('vue');

    return {
        formState: reactive({
            name: '',
            email: '',
            password: '',
            password_confirmation: '',
            remember: false,
            processing: false,
            errors: {},
            post: vi.fn(),
        }),
    };
});

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
    },
};

const mountPage = (page, props = {}) => mount(page, { props, global });

/**
 * Submits the way Inertia does: the request is in flight until it settles, so
 * the page has to show that it is busy.
 */
async function submitAsPending(wrapper) {
    formState.post.mockImplementation(() => {
        formState.processing = true;
    });

    await wrapper.get('form').trigger('submit');
    await nextTick();
}

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
        const wrapper = mountPage(Register, { status: null });

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
        const wrapper = mountPage(Register, { status: null });

        expect(wrapper.text()).toContain('The email has already been taken.');
    });

    it('submits login credentials and remember choice', async () => {
        const wrapper = mountPage(Login, { status: null });

        await wrapper.find('input[name="email"]').setValue('ada@example.com');
        await wrapper.find('input[name="password"]').setValue('correct-password');
        await wrapper.find('input[name="remember"]').setValue(true);
        await wrapper.find('form').trigger('submit');

        expect(formState.remember).toBe(true);
        expect(formState.post).toHaveBeenCalledWith('/login');
    });

    it('submits a password reset email request', async () => {
        const wrapper = mountPage(ForgotPassword, { status: null });

        await wrapper.find('input[name="email"]').setValue('ada@example.com');
        await wrapper.find('form').trigger('submit');

        expect(formState.post).toHaveBeenCalledWith('/forgot-password');
    });

    it('submits a new password with token and email props', async () => {
        const wrapper = mountPage(ResetPassword, {
            email: 'ada@example.com',
            token: 'reset-token',
            status: null,
        });

        expect(wrapper.find('input[name="token"]').element.value).toBe('reset-token');
        expect(wrapper.find('input[name="email"]').element.value).toBe('ada@example.com');

        await wrapper.find('input[name="password"]').setValue('new-password');
        await wrapper.find('input[name="password_confirmation"]').setValue('new-password');
        await wrapper.find('form').trigger('submit');

        expect(formState.post).toHaveBeenCalledWith('/reset-password');
    });

    it('requests another verification email', async () => {
        const wrapper = mountPage(VerifyEmail, { status: null });

        expect(wrapper.text()).toContain('Verify your email address');
        expect(wrapper.find('a[href="/logout"]').text()).toBe('Sign out');
        await wrapper.find('form').trigger('submit');

        expect(formState.post).toHaveBeenCalledWith('/email/verification-notification');
    });
});

describe('authentication pages while a request is in flight', () => {
    it.each([
        ['sign in', Login, '/login'],
        ['register', Register, '/register'],
        ['request a reset link', ForgotPassword, '/forgot-password'],
    ])('disables the submit button when asked to %s', async (_label, page, url) => {
        const wrapper = mountPage(page, { status: null });

        await submitAsPending(wrapper);

        const submit = wrapper.get('button[type="submit"]');

        expect(formState.post).toHaveBeenCalledWith(url);
        expect(submit.attributes('disabled')).toBeDefined();
        expect(submit.attributes('aria-busy')).toBe('true');
        expect(wrapper.get('[data-test="button-spinner"]').exists()).toBe(true);
    });

    it('locks the fields of the sign-in form', async () => {
        const wrapper = mountPage(Login, { status: null });

        await submitAsPending(wrapper);

        expect(wrapper.get('input[name="email"]').attributes('disabled')).toBeDefined();
        expect(wrapper.get('input[name="password"]').attributes('disabled')).toBeDefined();
        expect(wrapper.get('[data-test="password-toggle"]').attributes('disabled')).toBeDefined();
    });

    it('disables the verification resend button', async () => {
        const wrapper = mountPage(VerifyEmail, { status: null });

        await submitAsPending(wrapper);

        const submit = wrapper.get('button[type="submit"]');

        expect(submit.attributes('disabled')).toBeDefined();
        expect(submit.attributes('aria-busy')).toBe('true');
    });
});

describe('authentication page status messages', () => {
    it('reads the sign-in flash as a sentence', () => {
        formState.errors = { email: 'These credentials do not match our records.' };
        const wrapper = mountPage(Login, { status: 'email-verified' });

        expect(wrapper.get('[data-test="auth-status"]').text())
            .toBe('Your email address is verified. Welcome to TaskFlow!');
        expect(wrapper.get('input[name="email"]').attributes('aria-invalid')).toBe('true');
        expect(wrapper.get('[role="alert"]').text()).toBe('These credentials do not match our records.');
    });

    it('confirms the reset link request', () => {
        const wrapper = mountPage(ForgotPassword, { status: 'password-reset-link-sent' });

        expect(wrapper.get('[data-test="auth-status"]').text())
            .toBe('We have emailed you a password reset link. Check your inbox.');
    });

    it('confirms the verification resend', () => {
        const wrapper = mountPage(VerifyEmail, { status: 'verification-link-sent' });

        expect(wrapper.get('[data-test="auth-status"]').text())
            .toBe('A fresh verification link is on its way to your inbox.');
    });

    it('shows no banner when the server sent no status', () => {
        const wrapper = mountPage(Login, { status: null });

        expect(wrapper.find('[data-test="auth-status"]').exists()).toBe(false);
    });
});

describe('authentication page copy', () => {
    it('points a visitor to the sign-in and registration flows', () => {
        const login = mountPage(Login, { status: null });

        expect(login.get('a[href="/register"]').text()).toBe('Create an account');
        expect(login.get('a[href="/forgot-password"]').text()).toBe('Forgot password?');

        const register = mountPage(Register, { status: null });

        expect(register.get('a[href="/login"]').text()).toBe('Sign in');
    });

    it('labels every field and states the password rule on registration', () => {
        const wrapper = mountPage(Register, { status: null });

        expect(wrapper.get('input[name="name"]').attributes('autocomplete')).toBe('name');
        expect(wrapper.get('input[name="email"]').attributes('autocomplete')).toBe('email');
        expect(wrapper.get('input[name="password"]').attributes('autocomplete')).toBe('new-password');
        expect(wrapper.get('input[name="password_confirmation"]').attributes('autocomplete')).toBe('new-password');
        expect(wrapper.text()).toContain('at least 8 characters');
    });

    it('tells the visitor which address the new password belongs to', () => {
        const wrapper = mountPage(ResetPassword, {
            email: 'ada@example.com',
            token: 'reset-token',
            status: null,
        });

        expect(wrapper.text()).toContain('ada@example.com');
    });

    it('keeps the password fields masked until they are asked for', () => {
        const wrapper = mountPage(ResetPassword, {
            email: 'ada@example.com',
            token: 'reset-token',
            status: null,
        });

        const passwords = wrapper.findAll('input[type="password"]');

        expect(passwords).toHaveLength(2);
        expect(wrapper.findAll('[data-test="password-toggle"]')).toHaveLength(2);
    });

    it('offers a way back and a way out of the verification prompt', () => {
        const wrapper = mountPage(VerifyEmail, { status: null });

        expect(wrapper.get('a[href="/logout"]').text()).toBe('Sign out');
        expect(wrapper.text()).toContain('spam');
    });
});
