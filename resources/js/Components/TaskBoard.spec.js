import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';

import TaskBoard from './TaskBoard.vue';

const statuses = [
    { value: 'todo', label: 'To Do', allows: ['todo', 'in_progress', 'cancelled'] },
    { value: 'in_progress', label: 'In Progress', allows: ['todo', 'in_progress', 'in_review', 'cancelled'] },
    { value: 'in_review', label: 'In Review', allows: ['in_progress', 'in_review', 'done', 'cancelled'] },
    { value: 'done', label: 'Done', allows: ['in_progress', 'done'] },
    { value: 'cancelled', label: 'Cancelled', allows: ['todo', 'cancelled'] },
];

const task = (id, status, title = `Task ${id}`) => ({ id, title, status, priority: 'medium' });

const todo = task(1, 'todo', 'Draft the copy');
const reviewing = task(2, 'in_review', 'Review the mockups');
const done = task(3, 'done', 'Ship the landing page');

function paginator(data) {
    return { data, total: data.length, current_page: 1, last_page: 1, links: [] };
}

function mountBoard(props = {}) {
    return mount(TaskBoard, {
        props: {
            tasks: paginator([todo, reviewing, done]),
            statuses,
            canChange: () => true,
            isPending: () => false,
            ...props,
        },
        global: {
            stubs: {
                Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
            },
        },
    });
}

function column(wrapper, status) {
    return wrapper.get(`[data-column="${status}"]`);
}

function dropzone(wrapper, status) {
    return column(wrapper, status).get('[data-test="board-dropzone"]');
}

async function dragTo(wrapper, from, to) {
    await column(wrapper, from).get('[data-test="task-card"]').trigger('dragstart');
    await dropzone(wrapper, to).trigger('drop');
}

describe('TaskBoard.vue', () => {
    it('renders one column per status with its label', () => {
        const wrapper = mountBoard();

        expect(wrapper.findAll('[data-test="board-column"]')).toHaveLength(5);
        expect(wrapper.findAll('[data-test="board-column-label"]').map((entry) => entry.text()))
            .toEqual(['To Do', 'In Progress', 'In Review', 'Done', 'Cancelled']);
    });

    it('files every task under its own status', () => {
        const wrapper = mountBoard();

        expect(column(wrapper, 'todo').text()).toContain('Draft the copy');
        expect(column(wrapper, 'in_review').text()).toContain('Review the mockups');
        expect(column(wrapper, 'done').text()).toContain('Ship the landing page');
        expect(column(wrapper, 'in_progress').find('[data-test="task-card"]').exists()).toBe(false);
    });

    it('counts the tasks of every column', () => {
        const wrapper = mountBoard();

        expect(column(wrapper, 'todo').get('[data-test="board-column-count"]').text()).toBe('1');
        expect(column(wrapper, 'cancelled').get('[data-test="board-column-count"]').text()).toBe('0');
    });

    it('marks a column as a drop target while a task is dragged over it', async () => {
        const wrapper = mountBoard();

        await dropzone(wrapper, 'in_progress').trigger('dragover');

        expect(dropzone(wrapper, 'in_progress').classes()).toContain('border-brand-400');
        expect(dropzone(wrapper, 'in_progress').attributes('data-over')).toBe('true');
    });

    it('moves a dropped task to the status of the column', async () => {
        const wrapper = mountBoard();

        await dragTo(wrapper, 'todo', 'in_progress');

        expect(wrapper.emitted('move')).toEqual([[todo, 'in_progress']]);
    });

    it('refuses a move the domain does not allow and says so while dragging', async () => {
        const wrapper = mountBoard();

        await column(wrapper, 'todo').get('[data-test="task-card"]').trigger('dragstart');

        expect(dropzone(wrapper, 'done').attributes('data-allowed')).toBe('false');

        await dropzone(wrapper, 'done').trigger('drop');

        expect(wrapper.emitted('move')).toBeUndefined();
    });

    it('ignores a drop that carries no task', async () => {
        const wrapper = mountBoard();

        await dropzone(wrapper, 'in_progress').trigger('drop');

        expect(wrapper.emitted('move')).toBeUndefined();
    });

    it('ignores a task dropped on the status it already has', async () => {
        const wrapper = mountBoard();

        await dragTo(wrapper, 'in_review', 'in_review');

        expect(wrapper.emitted('move')).toBeUndefined();
    });

    it('hints at an empty column as a drop target', () => {
        const wrapper = mountBoard();

        expect(dropzone(wrapper, 'in_progress').text()).toContain('Drop a task here');
    });

    it('forgets the highlighted column once the drag leaves it', async () => {
        const wrapper = mountBoard();

        await column(wrapper, 'todo').get('[data-test="task-card"]').trigger('dragstart');
        await dropzone(wrapper, 'in_progress').trigger('dragover');

        expect(dropzone(wrapper, 'in_progress').attributes('data-over')).toBe('true');

        await dropzone(wrapper, 'in_progress').trigger('dragleave');

        expect(dropzone(wrapper, 'in_progress').attributes('data-over')).toBe('false');
    });

    it('asks to move a task from the status control of its card', async () => {
        const wrapper = mountBoard();

        await column(wrapper, 'todo').get('[data-test="task-card-status"]').setValue('in_progress');

        expect(wrapper.emitted('move')).toEqual([[todo, 'in_progress']]);
    });

    it('lets the page decide which cards may be dragged and which are busy', () => {
        const wrapper = mountBoard({ canChange: (entry) => entry.id === 1, isPending: (id) => id === 2 });

        expect(column(wrapper, 'todo').get('[data-test="task-card"]').attributes('draggable')).toBe('true');
        expect(column(wrapper, 'in_review').get('[data-test="task-card"]').attributes('draggable')).toBe('false');
        expect(column(wrapper, 'in_review').get('[data-test="task-card"]').attributes('aria-busy')).toBe('true');
    });

    it('shows an empty state instead of columns when the board holds no task', () => {
        const wrapper = mountBoard({
            tasks: paginator([]),
            emptyTitle: 'No tasks on this board',
            emptyDescription: 'Create the first task of the project.',
        });

        expect(wrapper.findAll('[data-test="board-column"]')).toHaveLength(0);
        expect(wrapper.get('[data-test="board-empty"]').text()).toContain('No tasks on this board');
        expect(wrapper.get('[data-test="board-empty"]').text()).toContain('Create the first task of the project.');
    });

    it('ignores a board prop it cannot read', () => {
        expect(mountBoard({ tasks: null }).findAll('[data-test="board-column"]')).toHaveLength(0);
    });
});
