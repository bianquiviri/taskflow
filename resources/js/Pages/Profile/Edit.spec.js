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
            post: vi.fn(),
            patch: vi.fn(),
            put: vi.fn(),
            delete: vi.fn(),
            reset: vi.fn(),
        });

        forms[Object.keys(initial).join(',')] = form;

        return form;
    },
}));

import Edit from './Edit.vue';
import AvatarField from './AvatarField.vue';
import ThemeChoice from './ThemeChoice.vue';

const user = {
    name: 'Ada Lovelace',
    email: 'ada@example.com',
    theme: 'light',
    avatar: null,
    emailVerified: true,
};

const mountPage = (props = {}) => mount(Edit, { props: { user, ...props } });

const identity = () => forms['name,email'];
const avatar = () => forms.avatar;

describe('Pages/Profile/Edit.vue', () => {
    beforeEach(() => {
        Object.keys(forms).forEach((key) => delete forms[key]);
    });

    it('shows the identity of the account', () => {
        const wrapper = mountPage();

        expect(wrapper.find('input[name="name"]').element.value).toBe('Ada Lovelace');
        expect(wrapper.find('input[name="email"]').element.value).toBe('ada@example.com');
    });

    it('saves the identity', async () => {
        const wrapper = mountPage();

        await wrapper.find('[data-test="identity-form"]').trigger('submit');

        expect(identity().patch).toHaveBeenCalledWith('/profile', { preserveScroll: true });
    });

    it('shows the identity errors', () => {
        const wrapper = mountPage();

        identity().errors = { name: 'The name field is required.' };

        return wrapper.vm.$nextTick().then(() => {
            expect(wrapper.text()).toContain('The name field is required.');
        });
    });

    it('uploads the selected avatar as multipart data', () => {
        const wrapper = mountPage();
        const file = new File(['binary'], 'me.png', { type: 'image/png' });

        wrapper.findComponent(AvatarField).vm.$emit('select', file);

        expect(avatar().avatar.name).toBe('me.png');
        expect(avatar().post).toHaveBeenCalledWith('/profile/avatar', expect.any(Object));
    });

    it('clears the avatar form after a successful upload', () => {
        const wrapper = mountPage();

        wrapper.findComponent(AvatarField).vm.$emit('select', new File([''], 'me.png'));
        const [url, options] = avatar().post.mock.calls[0];

        expect(url).toBe('/profile/avatar');

        options.onSuccess();

        expect(avatar().reset).toHaveBeenCalled();
    });

    it('removes the avatar', () => {
        const wrapper = mountPage({ user: { ...user, avatar: '/profile/avatar' } });

        wrapper.findComponent(AvatarField).vm.$emit('remove');

        expect(avatar().delete).toHaveBeenCalledWith('/profile/avatar', expect.any(Object));
    });

    it('shows the avatar error', () => {
        const wrapper = mountPage();

        avatar().errors = { avatar: 'The avatar must be less than 2 MB.' };

        return wrapper.vm.$nextTick().then(() => {
            expect(wrapper.text()).toContain('The avatar must be less than 2 MB.');
        });
    });

    it('passes the stored theme to the theme choice', () => {
        const wrapper = mountPage({ user: { ...user, theme: 'dark' } });

        expect(wrapper.findComponent(ThemeChoice).props('modelValue')).toBe('dark');
    });

    it('follows the theme choice', async () => {
        const wrapper = mountPage();

        wrapper.findComponent(ThemeChoice).vm.$emit('update:modelValue', 'dark');

        await wrapper.vm.$nextTick();

        expect(wrapper.findComponent(ThemeChoice).props('modelValue')).toBe('dark');
    });

    it('stays quiet about verification while the address is confirmed', () => {
        const wrapper = mountPage();

        expect(wrapper.find('[data-test="unverified"]').exists()).toBe(false);
    });

    it('warns an unverified account that it has to confirm the address', () => {
        const wrapper = mountPage({ user: { ...user, emailVerified: false } });

        expect(wrapper.find('[data-test="unverified"]').text()).toContain('ada@example.com');
        expect(wrapper.find('input[name="email"]').element.value).toBe('ada@example.com');
    });

    it('offers to resend the verification link while unverified', () => {
        const wrapper = mountPage({ user: { ...user, emailVerified: false } });

        expect(wrapper.find('[data-test="resend-verification"]').attributes('href')).toBe(
            '/email/verify',
        );
    });

    it('links to the password page', () => {
        const wrapper = mountPage();

        expect(wrapper.find('[data-test="to-password"]').attributes('href')).toBe('/profile/password');
    });
});
