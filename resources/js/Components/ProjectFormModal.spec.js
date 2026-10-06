import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';

const { formState } = vi.hoisted(() => ({
    formState: {
        name: '',
        description: '',
        processing: false,
        errors: {},
        transformed: null,
        post: vi.fn(),
        reset: vi.fn(),
        clearErrors: vi.fn(),
        transform: vi.fn(),
    },
}));

vi.mock('@inertiajs/vue3', () => ({
    useForm: (initial) => {
        Object.assign(formState, initial);
        formState.transform = vi.fn((callback) => {
            formState.transformed = callback({ ...formState });

            return formState;
        });

        return formState;
    },
}));

import ProjectFormModal from './ProjectFormModal.vue';

let wrapper;

function field(name) {
    return document.querySelector(`[name="${name}"]`);
}

function mountModal(props = {}) {
    wrapper = mount(ProjectFormModal, {
        props: { modelValue: true, ...props },
        attachTo: document.body,
    });

    return wrapper;
}

function submit() {
    document.querySelector('[role="dialog"] form').dispatchEvent(new window.Event('submit'));
}

beforeEach(() => {
    Object.assign(formState, { name: '', description: '', processing: false, errors: {}, transformed: null });
    formState.post.mockReset();
    formState.reset.mockReset();
});

afterEach(() => {
    wrapper?.unmount();
    wrapper = undefined;
    document.body.innerHTML = '';
});

describe('ProjectFormModal.vue', () => {
    it('posts a new project to the existing create route', async () => {
        mountModal();

        field('name').value = 'Website';
        field('name').dispatchEvent(new window.Event('input'));
        field('description').value = 'Rebuild the marketing site.';
        field('description').dispatchEvent(new window.Event('input'));
        await wrapper.vm.$nextTick();

        submit();
        await wrapper.vm.$nextTick();

        expect(formState.post).toHaveBeenCalledWith('/projects', expect.any(Object));
        expect(formState.transformed).toEqual({
            name: 'Website',
            description: 'Rebuild the marketing site.',
        });
    });

    it('sends an empty description as null', async () => {
        mountModal();

        field('name').value = 'Website';
        field('name').dispatchEvent(new window.Event('input'));
        await wrapper.vm.$nextTick();

        submit();
        await wrapper.vm.$nextTick();

        expect(formState.transformed).toEqual({ name: 'Website', description: null });
    });

    it('starts every dialog with an empty form', () => {
        mountModal();

        expect(document.querySelector('[role="dialog"] h2').textContent).toBe('New project');
        expect(field('name').value).toBe('');
        expect(field('description').value).toBe('');
        expect(formState.clearErrors).not.toHaveBeenCalled();
    });

    it('closes and empties the form once the project is stored', async () => {
        mountModal();

        submit();
        await wrapper.vm.$nextTick();

        const [, options] = formState.post.mock.calls[0];

        expect(options.preserveScroll).toBe(true);

        options.onSuccess();

        expect(formState.reset).toHaveBeenCalled();
        expect(wrapper.emitted('update:modelValue')).toEqual([[false]]);
    });

    it('shows the validation errors of the fields', () => {
        formState.errors = { name: 'The name field is required.' };

        mountModal();

        expect(document.querySelector('[role="alert"]').textContent).toBe('The name field is required.');
    });

    it('locks the form while the project is being stored', () => {
        formState.processing = true;

        mountModal();

        expect([...document.querySelectorAll('input, textarea')].every((input) => input.disabled)).toBe(true);
        expect(document.querySelector('button[type="submit"]').disabled).toBe(true);
    });

    it('stays closed until it is opened', () => {
        mountModal({ modelValue: false });

        expect(document.querySelector('[role="dialog"]')).toBeNull();
    });
});