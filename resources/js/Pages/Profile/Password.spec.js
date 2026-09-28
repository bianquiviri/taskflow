import { beforeEach, describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import { mount } from '@vue/test-utils';

const { forms } = vi.hoisted(() => ({ forms: {} }));

vi.mock('@inertiajs/vue3', () => ({
    Head: { props: ['title'], template: '<div />' },
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
    useForm: (initial) => {
        const form = reactive({
            ...initial,
            processing: false,
            errors: {},
            put: vi.fn(),
            reset: vi.fn(),
        });

        forms[Object.keys(initial).join(',')] = form;

        return form;
    },
}));

import Password from './Password.vue';

const mountPage = () => mount(Password);

const form = () => forms['current_password,password,password_confirmation'];

describe('Pages/Profile/Password.vue', () => {
    beforeEach(() => {
        Object.keys(forms).forEach((key) => delete forms[key]);
        form();
    });

    it('asks for the current password twice over', () => {
        const wrapper = mountPage();

        expect(wrapper.find('input[name="current_password"]').exists()).toBe(true);
        expect(wrapper.findAll('input[type="password"]')).toHaveLength(3);
    });

    it('hides the new password behind a toggle', async () => {
        const wrapper = mountPage();
        const input = wrapper.find('input[name="password"]');

        expect(input.attributes('type')).toBe('password');

        await wrapper.find('[data-test="toggle-password"]').setValue(true);

        expect(input.attributes('type')).toBe('text');
    });

    it('saves the new password', async () => {
        const wrapper = mountPage();

        form().current_password = 'password';
        form().password = 'a-brand-new-secret';
        form().password_confirmation = 'a-brand-new-secret';

        await wrapper.find('[data-test="password-form"]').trigger('submit');

        expect(form().put).toHaveBeenCalledWith('/profile/password', expect.any(Object));
    });

    it('clears the fields after a successful change', () => {
        const wrapper = mountPage();

        wrapper.find('[data-test="password-form"]').trigger('submit');

        const [, options] = form().put.mock.calls[0];

        options.onSuccess();

        expect(form().reset).toHaveBeenCalled();
    });

    it('keeps the fields filled in when the change failed', () => {
        const wrapper = mountPage();

        form().current_password = 'password';
        form().password = 'a-brand-new-secret';

        wrapper.find('[data-test="password-form"]').trigger('submit');

        const [, options] = form().put.mock.calls[0];

        expect(options.onError).toBeUndefined();
        expect(form().reset).not.toHaveBeenCalled();
        expect(form().password).toBe('a-brand-new-secret');
    });

    it('shows the current password error', () => {
        const wrapper = mountPage();

        form().errors = { current_password: 'The password is incorrect.' };

        return wrapper.vm.$nextTick().then(() => {
            expect(wrapper.text()).toContain('The password is incorrect.');
        });
    });

    it('shows the confirmation error', () => {
        const wrapper = mountPage();

        form().errors = { password: 'The password confirmation does not match.' };

        return wrapper.vm.$nextTick().then(() => {
            expect(wrapper.text()).toContain('The password confirmation does not match.');
        });
    });

    it('links back to the profile page', () => {
        const wrapper = mountPage();

        expect(wrapper.find('[data-test="to-profile"]').attributes('href')).toBe('/profile');
    });
});
