import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';

import PasswordField from './PasswordField.vue';

const mountField = (props = {}) => mount(PasswordField, {
    props: {
        modelValue: '',
        name: 'password',
        label: 'Password',
        ...props,
    },
});

describe('PasswordField.vue', () => {
    it('masks the value and offers a way to reveal it', () => {
        const wrapper = mountField();

        const input = wrapper.get('input[name="password"]');
        const toggle = wrapper.get('[data-test="password-toggle"]');

        expect(input.attributes('type')).toBe('password');
        expect(toggle.attributes('type')).toBe('button');
        expect(toggle.attributes('aria-pressed')).toBe('false');
        expect(toggle.text()).toBe('Show password');
    });

    it('reveals and hides the value again', async () => {
        const wrapper = mountField();
        const input = wrapper.get('input[name="password"]');
        const toggle = wrapper.get('[data-test="password-toggle"]');

        await toggle.trigger('click');

        expect(input.attributes('type')).toBe('text');
        expect(toggle.attributes('aria-pressed')).toBe('true');
        expect(toggle.text()).toBe('Hide password');

        await toggle.trigger('click');

        expect(input.attributes('type')).toBe('password');
        expect(toggle.attributes('aria-pressed')).toBe('false');
    });

    it('emits the typed value', async () => {
        const wrapper = mountField();

        await wrapper.get('input[name="password"]').setValue('secret-value');

        expect(wrapper.emitted('update:modelValue').at(-1)).toEqual(['secret-value']);
    });

    it('shows the server error and marks the input invalid', () => {
        const wrapper = mountField({ error: 'These credentials do not match our records.' });

        const input = wrapper.get('input[name="password"]');
        const error = wrapper.get('[role="alert"]');

        expect(error.text()).toBe('These credentials do not match our records.');
        expect(input.attributes('aria-invalid')).toBe('true');
    });

    it('locks the field and the toggle while the request is on its way', () => {
        const wrapper = mountField({ disabled: true });

        expect(wrapper.get('input[name="password"]').attributes('disabled')).toBeDefined();
        expect(wrapper.get('[data-test="password-toggle"]').attributes('disabled')).toBeDefined();
    });

    it('forwards the browser hints an auth form needs', () => {
        const wrapper = mountField({ autocomplete: 'new-password', minlength: 8 });

        const input = wrapper.get('input[name="password"]');

        expect(input.attributes('autocomplete')).toBe('new-password');
        expect(input.attributes('minlength')).toBe('8');
        expect(input.attributes('required')).toBeDefined();
        expect(wrapper.get('label').attributes('for')).toBe(input.attributes('id'));
    });

    it('leaves the length rule to the server when none is given', () => {
        expect(mountField().get('input[name="password"]').attributes('minlength')).toBeUndefined();
    });

    it('states the password rule only when a hint is given', () => {
        expect(mountField().find('[data-test="password-hint"]').exists()).toBe(false);

        const wrapper = mountField({ hint: 'Use at least 8 characters.' });

        expect(wrapper.get('[data-test="password-hint"]').text()).toBe('Use at least 8 characters.');
    });
});
