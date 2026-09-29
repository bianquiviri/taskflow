import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';

import AuthStatusMessage from './AuthStatusMessage.vue';

const mountMessage = (status) => mount(AuthStatusMessage, { props: { status } });

describe('AuthStatusMessage.vue', () => {
    it('renders nothing without a status', () => {
        const wrapper = mountMessage(null);

        expect(wrapper.find('[data-test="auth-status"]').exists()).toBe(false);
        expect(mountMessage('   ').find('[data-test="auth-status"]').exists()).toBe(false);
    });

    it('turns the password reset flash key into a readable message', () => {
        const wrapper = mountMessage('password-reset-link-sent');

        const message = wrapper.get('[data-test="auth-status"]');

        expect(message.text()).toBe('We have emailed you a password reset link. Check your inbox.');
        expect(message.attributes('role')).toBe('status');
        expect(message.attributes('aria-live')).toBe('polite');
    });

    it('explains that a new verification link was sent', () => {
        expect(mountMessage('verification-link-sent').get('[data-test="auth-status"]').text())
            .toBe('A fresh verification link is on its way to your inbox.');
    });

    it('confirms a verified address', () => {
        expect(mountMessage('email-verified').get('[data-test="auth-status"]').text())
            .toBe('Your email address is verified. Welcome to TaskFlow!');
    });

    it('humanizes an unknown key instead of leaking it', () => {
        expect(mountMessage('some_new_status').get('[data-test="auth-status"]').text())
            .toBe('Some new status');
    });

    it('marks the message as a live region so screen readers announce it', () => {
        expect(mountMessage('email-verified').get('[data-test="auth-status"]').attributes('aria-live'))
            .toBe('polite');
    });
});
