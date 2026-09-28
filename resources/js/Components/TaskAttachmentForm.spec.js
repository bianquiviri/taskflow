import { beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import { mount } from '@vue/test-utils';

const { formState } = await vi.hoisted(async () => {
    const { reactive } = await import('vue');

    return {
        formState: reactive({
            file: null,
            processing: false,
            errors: {},
            post: vi.fn(),
            clearErrors: vi.fn(),
        }),
    };
});

vi.mock('@inertiajs/vue3', () => ({
    useForm: (initial) => {
        Object.assign(formState, initial);
        return formState;
    },
}));

import TaskAttachmentForm from './TaskAttachmentForm.vue';

const chosen = { name: 'spec.pdf', type: 'application/pdf' };

/** A file input cannot be given a value, so the picker is faked instead. */
const pick = async (wrapper) => {
    const input = wrapper.get('input[type="file"]');
    Object.defineProperty(input.element, 'files', { value: [chosen], configurable: true });

    await input.trigger('change');

    return input;
};

const mountForm = () => mount(TaskAttachmentForm, {
    props: {
        url: '/tasks/3/files',
        accept: ['.pdf', '.png'],
        maxSize: '5 MB',
    },
});

beforeEach(() => {
    Object.assign(formState, { file: null, processing: false, errors: {} });
    formState.post.mockReset();
    formState.clearErrors.mockReset();
});

describe('TaskAttachmentForm.vue', () => {
    it('keeps the submit disabled until a file is chosen', async () => {
        const wrapper = mountForm();

        expect(wrapper.get('button[type="submit"]').element.disabled).toBe(true);

        await pick(wrapper);

        expect(wrapper.get('button[type="submit"]').element.disabled).toBe(false);
    });

    it('restricts the picker to the accepted file types and names the size limit', () => {
        const wrapper = mountForm();

        expect(wrapper.get('input[type="file"]').attributes('accept')).toBe('.pdf,.png');
        expect(wrapper.text()).toContain('5 MB');
    });

    it('uploads the chosen file as multipart data and keeps the page in place', async () => {
        const wrapper = mountForm();

        await pick(wrapper);
        await wrapper.get('form').trigger('submit');

        expect(formState.file).toEqual(chosen);
        expect(formState.clearErrors).toHaveBeenCalledWith('file');
        expect(formState.post).toHaveBeenCalledWith('/tasks/3/files', expect.objectContaining({
            forceFormData: true,
            preserveScroll: true,
        }));
    });

    it('empties the picker after a successful upload', async () => {
        const wrapper = mountForm();
        const input = await pick(wrapper);

        await wrapper.get('form').trigger('submit');

        const [, options] = formState.post.mock.calls[0];
        options.onSuccess();
        await nextTick();

        expect(formState.file).toBeNull();
        expect(input.element.value).toBe('');
        expect(wrapper.get('button[type="submit"]').element.disabled).toBe(true);
    });

    it('displays the message the server refused the upload with', () => {
        formState.errors = { file: 'The file may not be larger than 5 MB.' };
        const wrapper = mountForm();

        const error = wrapper.get('[data-test="attachment-error"]');

        expect(error.text()).toBe('The file may not be larger than 5 MB.');
        expect(error.attributes('role')).toBe('alert');
        expect(wrapper.get('input[type="file"]').attributes('aria-invalid')).toBe('true');
    });

    it('disables the picker while the upload is on its way', () => {
        formState.processing = true;
        const wrapper = mountForm();

        expect(wrapper.get('input[type="file"]').attributes('disabled')).toBeDefined();
        expect(wrapper.get('button[type="submit"]').attributes('aria-busy')).toBe('true');
    });
});
