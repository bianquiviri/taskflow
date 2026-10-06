import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';

const { formState } = vi.hoisted(() => ({
    formState: {
        body: '',
        processing: false,
        errors: {},
        post: vi.fn(),
        reset: vi.fn(),
    },
}));

vi.mock('@inertiajs/vue3', () => ({
    useForm: (initial) => {
        Object.assign(formState, initial);
        return formState;
    },
}));

import CommentForm from './CommentForm.vue';

beforeEach(() => {
    Object.assign(formState, { body: '', processing: false, errors: {} });
    formState.post.mockReset();
    formState.reset.mockReset();
    formState.reset.mockImplementation(() => {
        formState.body = '';
    });
});

describe('CommentForm.vue', () => {
    it('posts the comment body to the task thread', async () => {
        const wrapper = mount(CommentForm, { props: { url: '/tasks/9/comments' } });

        await wrapper.get('textarea').setValue('Please review @grace-hopper');
        await wrapper.get('form').trigger('submit');

        expect(formState.body).toBe('Please review @grace-hopper');
        expect(formState.post).toHaveBeenCalledWith('/tasks/9/comments', expect.any(Object));
    });

    it('clears the body after a successful post while keeping the page in place', async () => {
        const wrapper = mount(CommentForm, { props: { url: '/tasks/9/comments' } });

        await wrapper.get('textarea').setValue('Done');
        await wrapper.get('form').trigger('submit');

        const [, options] = formState.post.mock.calls[0];
        options.onSuccess();

        expect(options.preserveScroll).toBe(true);
        expect(formState.reset).toHaveBeenCalled();
        expect(formState.body).toBe('');
    });

    it('displays the validation error of the body field', () => {
        formState.errors = { body: 'The body field is required.' };
        const wrapper = mount(CommentForm, { props: { url: '/tasks/9/comments' } });

        expect(wrapper.get('[role="alert"]').text()).toBe('The body field is required.');
    });

    it('disables the fields while the comment is being sent', () => {
        formState.processing = true;
        const wrapper = mount(CommentForm, { props: { url: '/tasks/9/comments' } });

        expect(wrapper.get('textarea').attributes('disabled')).toBeDefined();
        expect(wrapper.get('button[type="submit"]').attributes('disabled')).toBeDefined();
    });

    it('documents the mention syntax', () => {
        const wrapper = mount(CommentForm, { props: { url: '/tasks/9/comments' } });

        expect(wrapper.text()).toContain('@handle');
    });
});
