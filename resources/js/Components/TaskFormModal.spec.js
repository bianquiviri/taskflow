import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';

const { formState } = vi.hoisted(() => ({
    formState: {
        title: '',
        description: '',
        priority: '',
        due_date: '',
        processing: false,
        errors: {},
        transformed: null,
        post: vi.fn(),
        patch: vi.fn(),
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

import TaskFormModal from './TaskFormModal.vue';

const priorities = [
    { value: 'low', label: 'Low' },
    { value: 'medium', label: 'Medium' },
    { value: 'urgent', label: 'Urgent' },
];

const task = {
    id: 9,
    title: 'Ship the release',
    description: 'Tag, build, publish.',
    status: 'in_progress',
    priority: 'urgent',
    due_date: '2026-10-12T00:00:00.000000Z',
};

let wrapper;

function field(name) {
    return document.querySelector(`[name="${name}"]`);
}

function mountModal(props = {}) {
    wrapper = mount(TaskFormModal, {
        props: { modelValue: true, projectId: 1, priorities, ...props },
        attachTo: document.body,
    });

    return wrapper;
}

beforeEach(() => {
    Object.assign(formState, {
        title: '',
        description: '',
        priority: '',
        due_date: '',
        processing: false,
        errors: {},
        transformed: null,
    });
    formState.post.mockReset();
    formState.patch.mockReset();
    formState.reset.mockReset();
});

afterEach(() => {
    wrapper?.unmount();
    wrapper = undefined;
    document.body.innerHTML = '';
});

describe('TaskFormModal.vue', () => {
    it('creates a task in the project when it is given none', async () => {
        mountModal();

        field('title').value = 'Draft the copy';
        field('title').dispatchEvent(new window.Event('input'));
        await wrapper.vm.$nextTick();
        document.querySelector('form').dispatchEvent(new window.Event('submit'));
        await wrapper.vm.$nextTick();

        expect(formState.post).toHaveBeenCalledWith('/projects/1/tasks', expect.any(Object));
        expect(formState.patch).not.toHaveBeenCalled();
        expect(formState.transformed).toEqual({
            title: 'Draft the copy',
            description: null,
            priority: 'medium',
            due_date: null,
        });
    });

    it('updates the task it was given instead of creating one', async () => {
        mountModal({ task });

        expect(document.querySelector('[role="dialog"] h2').textContent).toBe('Edit task');
        expect(formState.title).toBe('Ship the release');
        expect(formState.description).toBe('Tag, build, publish.');
        expect(formState.priority).toBe('urgent');
        expect(formState.due_date).toBe('2026-10-12');

        document.querySelector('form').dispatchEvent(new window.Event('submit'));
        await wrapper.vm.$nextTick();

        expect(formState.patch).toHaveBeenCalledWith('/tasks/9', expect.any(Object));
        expect(formState.post).not.toHaveBeenCalled();
    });

    it('keeps the filled in fields and the priority choice', async () => {
        mountModal({ task });

        expect(field('title').value).toBe('Ship the release');
        expect(field('description').value).toBe('Tag, build, publish.');
        expect(field('due_date').value).toBe('2026-10-12');
        expect([...field('priority').options].map((option) => option.value))
            .toEqual(['low', 'medium', 'urgent']);
        expect(field('priority').value).toBe('urgent');
    });

    it('keeps the user on the page and closes once the task is saved', async () => {
        mountModal({ task });

        document.querySelector('form').dispatchEvent(new window.Event('submit'));
        const [, options] = formState.patch.mock.calls[0];

        expect(options.preserveScroll).toBe(true);
        expect(options.preserveState).toBe(true);

        options.onSuccess();

        expect(formState.reset).toHaveBeenCalled();
        expect(wrapper.emitted('update:modelValue')).toEqual([[false]]);
    });

    it('shows the validation errors of the fields', () => {
        formState.errors = {
            title: 'The title field is required.',
            due_date: 'The due date is not a valid date.',
        };

        mountModal();

        expect([...document.querySelectorAll('[role="alert"]')].map((alert) => alert.textContent))
            .toEqual(['The title field is required.', 'The due date is not a valid date.']);
    });

    it('locks the form while the task is being saved', () => {
        formState.processing = true;

        mountModal();

        expect([...document.querySelectorAll('input, textarea, select')].every((input) => input.disabled))
            .toBe(true);
        expect(document.querySelector('button[type="submit"]').disabled).toBe(true);
    });

    it('loads another task when the dialog is opened for it', async () => {
        mountModal({ modelValue: false, task });

        expect(formState.title).toBe('');

        await wrapper.setProps({
            modelValue: true,
            task: { id: 10, title: 'Write the release notes', description: '', priority: 'low', due_date: null },
        });

        expect(formState.title).toBe('Write the release notes');
        expect(formState.priority).toBe('low');
        expect(formState.due_date).toBe('');
    });
});
