import { describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import { mount } from '@vue/test-utils';

const { events, pageState, router } = vi.hoisted(() => {
    const events = {};
    const stop = vi.fn();

    return {
        events,
        pageState: { url: '/projects/1', props: { can: [] } },
        router: {
            get: vi.fn(),
            patch: vi.fn(),
            on: vi.fn((name, handler) => {
                events[name] = handler;

                return stop;
            }),
        },
    };
});

vi.mock('@inertiajs/vue3', () => ({
    Head: { props: ['title'], template: '<div />' },
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
    router,
    usePage: () => pageState,
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
import TaskBoard from '../../Components/TaskBoard.vue';
import TaskFormModal from '../../Components/TaskFormModal.vue';
import TaskList from '../../Components/TaskList.vue';

const project = { id: 1, name: 'Website Redesign', description: 'Revamp the marketing site.' };

const task = (id, status) => ({
    id,
    title: `Task ${id}`,
    status,
    priority: 'medium',
    due_date: null,
    assignee: null,
    project: { id: 1, name: 'Website Redesign' },
});

const statuses = [
    { value: 'todo', label: 'To Do' },
    { value: 'done', label: 'Done' },
];
const priorities = [{ value: 'urgent', label: 'Urgent' }];
const members = [
    { id: 3, name: 'Ada Lovelace', role: 'owner' },
    { id: 4, name: 'Alan Turing', role: 'member' },
];

const paginator = (data, extra = {}) => ({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: data.length,
    data,
    links: [],
    ...extra,
});

const emptyFilters = {
    search: null,
    status: null,
    priority: null,
    assignee_id: null,
    due_from: null,
    due_to: null,
};

function mountPage(props = {}) {
    return mount(Show, {
        props: {
            project,
            view: 'board',
            tasks: paginator([task(1, 'todo'), task(2, 'done')]),
            filters: { ...emptyFilters },
            filterOptions: { statuses, priorities, assignees: [{ id: 4, name: 'Alan Turing' }] },
            statuses,
            members,
            progress: { total: 8, done: 4, percent: 50 },
            permissions: ['tasks.create', 'tasks.1.changeStatus', 'tasks.2.changeStatus'],
            activity: paginator([]),
            ...props,
        },
    });
}

function visit(method, only = []) {
    return { method, only, url: '/projects/1' };
}

function visitOptions() {
    return router.patch.mock.calls[0][2];
}

describe('Projects/Show.vue', () => {
    it('renders the project, its description and its progress', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain('Website Redesign');
        expect(wrapper.text()).toContain('Revamp the marketing site.');
        expect(wrapper.get('[data-test="project-progress"]').text()).toContain('4 of 8 tasks done');
        expect(wrapper.get('[data-test="project-progress-bar"]').attributes('aria-valuenow')).toBe('50');
    });

    it('shows an empty board as no progress at all', () => {
        const wrapper = mountPage({ progress: { total: 0, done: 0, percent: 0 } });

        expect(wrapper.get('[data-test="project-progress"]').text()).toContain('No tasks yet');
    });

    it('lists the people on the project with their role', () => {
        const rows = mountPage().findAll('[data-test="project-member"]');

        expect(rows).toHaveLength(2);
        expect(rows[0].text()).toContain('Ada Lovelace');
        expect(rows[0].text()).toContain('owner');
        expect(rows[1].text()).toContain('Alan Turing');
    });

    it('shows the board with the statuses and options the server sent', () => {
        const wrapper = mountPage();
        const board = wrapper.getComponent(TaskBoard);

        expect(board.exists()).toBe(true);
        expect(wrapper.findComponent(TaskList).exists()).toBe(false);
        expect(board.props('statuses')).toEqual(statuses);
        expect(wrapper.getComponent(TaskFormModal).props('priorities')).toEqual(priorities);
        expect(wrapper.getComponent(TaskFormModal).props('projectId')).toBe(1);
    });

    it('shows the filtered list instead of the board on request', () => {
        const wrapper = mountPage({ view: 'list' });

        expect(wrapper.findComponent(TaskBoard).exists()).toBe(false);
        expect(wrapper.getComponent(TaskList).props('tasks').data).toHaveLength(2);
    });

    it('marks the current view and switches to the other one with the filters kept', async () => {
        const wrapper = mountPage({ filters: { ...emptyFilters, status: 'todo' } });

        expect(wrapper.get('[data-test="view-board"]').attributes('aria-pressed')).toBe('true');
        expect(wrapper.get('[data-test="view-list"]').attributes('aria-pressed')).toBe('false');

        await wrapper.get('[data-test="view-list"]').trigger('click');

        expect(router.get).toHaveBeenCalledWith(
            '/projects/1',
            { status: 'todo', view: 'list' },
            expect.objectContaining({ preserveState: true, preserveScroll: true }),
        );
    });

    it('drops the view parameter when the board is already the default', async () => {
        const wrapper = mountPage({ view: 'list' });

        await wrapper.get('[data-test="view-board"]').trigger('click');

        expect(router.get).toHaveBeenCalledWith('/projects/1', {}, expect.any(Object));
    });

    it('stays put when the view that is already shown is picked again', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-test="view-board"]').trigger('click');

        expect(router.get).not.toHaveBeenCalled();
    });

    it('keeps the filter form pointed at the project', () => {
        const wrapper = mountPage();

        expect(wrapper.getComponent({ name: 'TaskFilterForm' }).props('url')).toBe('/projects/1');
    });

    it('lets the user create a task only with the permission', async () => {
        const wrapper = mountPage();
        const modal = wrapper.getComponent(TaskFormModal);

        expect(wrapper.get('[data-test="task-create"]').exists()).toBe(true);
        expect(modal.props('modelValue')).toBe(false);

        await wrapper.get('[data-test="task-create"]').trigger('click');

        expect(modal.props('modelValue')).toBe(true);

        const viewer = mountPage({ permissions: [] });

        expect(viewer.find('[data-test="task-create"]').exists()).toBe(false);
    });

    it('only lets the user move the tasks the page resolved for them', () => {
        const wrapper = mountPage({ permissions: ['tasks.create', 'tasks.2.changeStatus'] });

        expect(wrapper.getComponent(TaskBoard).props('canChange')(task(2, 'done'))).toBe(true);
        expect(wrapper.getComponent(TaskBoard).props('canChange')(task(1, 'todo'))).toBe(false);
    });

    it('changes a status with an optimistic visit and reports a refusal', async () => {
        const wrapper = mountPage();
        const board = wrapper.getComponent(TaskBoard);

        await board.vm.$emit('move', task(1, 'todo'), 'done');
        await nextTick();

        expect(router.patch).toHaveBeenCalledWith(
            '/tasks/1/status',
            { status: 'done' },
            expect.objectContaining({ preserveState: true, preserveScroll: true, optimistic: expect.any(Function) }),
        );
        expect(wrapper.find('[data-test="status-error"]').exists()).toBe(false);

        visitOptions().onError({ status: 'The selected status is invalid.' });
        await nextTick();

        expect(wrapper.get('[data-test="status-error"]').text()).toContain('The selected status is invalid.');
    });

    it('shows skeletons while a full task listing visit is on its way', async () => {
        const wrapper = mountPage();

        expect(wrapper.find('[data-test="board-skeleton"]').exists()).toBe(false);

        events.start(visit('get'));
        await nextTick();

        expect(wrapper.find('[data-test="board-skeleton"]').exists()).toBe(true);

        events.finish(visit('get'));
        await nextTick();

        expect(wrapper.find('[data-test="board-skeleton"]').exists()).toBe(false);
    });

    it('keeps the board in place for status changes and partial reloads', async () => {
        const wrapper = mountPage();

        events.start(visit('patch'));
        events.start(visit('get', ['tasks']));
        await nextTick();

        expect(wrapper.find('[data-test="board-skeleton"]').exists()).toBe(false);
    });

    it('watches the router for task visits and leaves it again with the page', () => {
        const wrapper = mountPage();

        expect(router.on).toHaveBeenCalledWith('start', expect.any(Function));
        expect(router.on).toHaveBeenCalledWith('finish', expect.any(Function));

        const [stopStart, stopFinish] = router.on.mock.results.map((result) => result.value);
        wrapper.unmount();

        expect(stopStart).toHaveBeenCalled();
        expect(stopFinish).toHaveBeenCalled();
    });

    it('renders the audit trail of the project', () => {
        const wrapper = mountPage({
            activity: paginator([
                {
                    id: 12,
                    event: 'task.created',
                    actor: { id: 3, name: 'Ada Lovelace' },
                    created_at: '2026-03-12T10:15:00.000000Z',
                },
            ]),
        });

        expect(wrapper.get('[data-test="activity-entry"]').text()).toContain('Ada Lovelace');
        expect(wrapper.get('[data-test="activity-entry"]').text()).toContain('2026');
    });

    it('falls back to a placeholder without activity or description', () => {
        const wrapper = mountPage({ project: { ...project, description: null }, activity: paginator([]) });

        expect(wrapper.text()).toContain('No description.');
        expect(wrapper.get('[data-test="activity-empty"]').text()).toContain('No activity yet');
    });
});
