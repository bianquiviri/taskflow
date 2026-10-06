import { describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import { mount } from '@vue/test-utils';

const { patch, pageState } = vi.hoisted(() => ({
    patch: vi.fn(),
    pageState: { url: '/tasks/9', props: { can: [] } },
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: { props: ['title'], template: '<div />' },
    usePage: () => pageState,
    router: { patch },
    useForm: (initial) => ({
        processing: false,
        errors: {},
        post: vi.fn(),
        patch: vi.fn(),
        reset: vi.fn(),
        clearErrors: vi.fn(),
        transform: vi.fn(),
        ...initial,
    }),
}));

import Show from './Show.vue';
import CommentForm from '../../Components/CommentForm.vue';
import TaskFormModal from '../../Components/TaskFormModal.vue';

const task = {
    id: 9,
    title: 'Ship the release',
    description: 'Tag, build, publish.',
    status: 'in_progress',
    priority: 'high',
    project: { id: 3, name: 'TaskFlow' },
    assignee: null,
};

const statuses = [
    { value: 'todo', label: 'To Do', allows: ['todo', 'in_progress', 'cancelled'] },
    { value: 'in_progress', label: 'In Progress', allows: ['todo', 'in_progress', 'in_review', 'cancelled'] },
    { value: 'in_review', label: 'In Review', allows: ['in_progress', 'in_review', 'done', 'cancelled'] },
    { value: 'done', label: 'Done', allows: ['in_progress', 'done'] },
    { value: 'cancelled', label: 'Cancelled', allows: ['todo', 'cancelled'] },
];

const priorities = [
    { value: 'low', label: 'Low' },
    { value: 'high', label: 'High' },
];

const comments = [
    {
        id: 1,
        body: 'Ready for review @grace-hopper',
        created_at: '2026-09-26T10:00:00.000000Z',
        user: { id: 7, name: 'Ada Lovelace' },
    },
];

function mountPage(props = {}) {
    return mount(Show, {
        props: {
            task,
            comments: [],
            priorities,
            statuses,
            permissions: ['tasks.update', 'tasks.changeStatus'],
            ...props,
        },
    });
}

describe('Tasks/Show.vue', () => {
    it('renders the task details', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain('Ship the release');
        expect(wrapper.text()).toContain('Tag, build, publish.');
        expect(wrapper.text()).toContain('TaskFlow');
        expect(wrapper.get('[data-test="task-priority"]').text()).toBe('High');
    });

    it('offers only the statuses the task may move to, with the current one selected', () => {
        const select = mountPage().get('[data-test="task-status"]');

        expect(select.findAll('option').map((option) => option.attributes('value')))
            .toEqual(['todo', 'in_progress', 'in_review', 'cancelled']);
        expect(select.element.value).toBe('in_progress');
    });

    it('changes the status of the task with an optimistic visit', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-test="task-status"]').setValue('in_review');

        expect(patch).toHaveBeenCalledWith(
            '/tasks/9/status',
            { status: 'in_review' },
            expect.objectContaining({ preserveState: true, preserveScroll: true, optimistic: expect.any(Function) }),
        );
        expect(patch.mock.calls[0][2].optimistic({ task })).toEqual({
            task: { ...task, status: 'in_review' },
        });
    });

    it('reports a status change the server refuses and lets Inertia roll it back', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-test="task-status"]').setValue('in_review');
        patch.mock.calls[0][2].onError({ status: 'Someone else already moved this task.' });
        await nextTick();

        expect(wrapper.get('[data-test="status-error"]').text()).toContain('Someone else already moved this task.');
    });

    it('hides the status control without the permission', () => {
        expect(mountPage({ permissions: [] }).find('[data-test="task-status"]').exists()).toBe(false);
    });

    it('shows the priority as a label and opens the edit dialog for those allowed', async () => {
        const wrapper = mountPage();
        const modal = wrapper.getComponent(TaskFormModal);

        expect(wrapper.get('[data-test="task-priority"]').text()).toBe('High');
        expect(wrapper.getComponent(TaskFormModal).props('priorities')).toEqual(priorities);
        expect(wrapper.getComponent(TaskFormModal).props('task')).toEqual(task);
        expect(modal.props('modelValue')).toBe(false);

        await wrapper.get('[data-test="task-edit"]').trigger('click');

        expect(modal.props('modelValue')).toBe(true);
    });

    it('hides the edit control without the permission', () => {
        expect(mountPage({ permissions: ['tasks.changeStatus'] }).find('[data-test="task-edit"]').exists()).toBe(false);
    });

    it('renders the comment thread and the comment form', () => {
        const wrapper = mountPage({ comments });

        expect(wrapper.get('[data-test="comment"]').text()).toContain('Ready for review @grace-hopper');
        expect(wrapper.get('textarea').attributes('name')).toBe('body');
        expect(wrapper.getComponent(CommentForm).props('url')).toBe('/tasks/9/comments');
    });

    it('shows the empty state when the task has no comment', () => {
        expect(mountPage().text()).toContain('No comments yet');
    });
});
