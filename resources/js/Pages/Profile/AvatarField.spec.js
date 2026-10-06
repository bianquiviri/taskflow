import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import AvatarField from './AvatarField.vue';

const AvatarStub = {
    props: ['name', 'src'],
    template: '<span data-test="avatar" :data-src="src ?? \'\'">{{ name }}</span>',
};

const global = { stubs: { Avatar: AvatarStub } };

const mountField = (props = {}) =>
    mount(AvatarField, {
        props: { name: 'Ada Lovelace', src: null, ...props },
        global,
    });

function selectFile(wrapper, files) {
    const input = wrapper.find('[data-test="avatar-input"]');

    Object.defineProperty(input.element, 'files', { value: files, configurable: true });

    return input.trigger('change');
}

describe('AvatarField.vue', () => {
    it('falls back to the initials when no avatar was uploaded', () => {
        const wrapper = mountField();

        expect(wrapper.find('[data-test="avatar"]').attributes('data-src')).toBe('');
        expect(wrapper.find('[data-test="avatar-remove"]').exists()).toBe(false);
    });

    it('shows the stored avatar and offers to replace or remove it', () => {
        const wrapper = mountField({ src: '/profile/avatar' });

        expect(wrapper.find('[data-test="avatar"]').attributes('data-src')).toBe('/profile/avatar');
        expect(wrapper.find('[data-test="avatar-remove"]').exists()).toBe(true);
    });

    it('accepts jpeg, png and webp files only', () => {
        const wrapper = mountField();

        expect(wrapper.find('[data-test="avatar-input"]').attributes('accept')).toBe(
            'image/jpeg,image/png,image/webp',
        );
    });

    it('labels the file input', () => {
        const wrapper = mountField();
        const input = wrapper.find('[data-test="avatar-input"]');

        expect(wrapper.find(`label[for="${input.attributes('id')}"]`).exists()).toBe(true);
    });

    it('emits the selected file', async () => {
        const wrapper = mountField();
        const file = new File(['binary'], 'me.png', { type: 'image/png' });

        await selectFile(wrapper, [file]);

        expect(wrapper.emitted('select')).toEqual([[file]]);
    });

    it('stays silent when the dialog is dismissed', async () => {
        const wrapper = mountField();

        await selectFile(wrapper, []);

        expect(wrapper.emitted('select')).toBeUndefined();
    });

    it('emits remove only for a stored avatar', async () => {
        const wrapper = mountField();

        expect(wrapper.find('[data-test="avatar-remove"]').exists()).toBe(false);

        await wrapper.setProps({ src: '/profile/avatar' });
        await wrapper.find('[data-test="avatar-remove"]').trigger('click');

        expect(wrapper.emitted('remove')).toHaveLength(1);
    });

    it('locks both actions while an upload is in flight', () => {
        const wrapper = mountField({ src: '/profile/avatar', processing: true });

        expect(wrapper.find('[data-test="avatar-input"]').attributes('disabled')).toBeDefined();
        expect(wrapper.find('[data-test="avatar-remove"]').attributes('disabled')).toBeDefined();
    });

    it('shows the upload error', () => {
        const wrapper = mountField({ error: 'The avatar must be an image.' });

        expect(wrapper.find('[data-test="avatar-error"]').text()).toBe('The avatar must be an image.');
    });
});
