import { beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import { mount } from '@vue/test-utils';

const { events, router } = vi.hoisted(() => {
    const events = {};
    const stop = vi.fn();

    return {
        events,
        router: {
            get: vi.fn(),
            on: vi.fn((name, handler) => {
                events[name] = handler;

                return stop;
            }),
        },
    };
});

vi.mock('@inertiajs/vue3', () => ({
    router,
    Head: { template: '<div data-test="head" />' },
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

import Index from './Index.vue';

const project = { id: 1, name: 'Website' };

const paginatedTasks = {
    data: [{
        id: 3,
        title: 'Ship the website',
        status: 'in_progress',
        priority: 'high',
        due_date: null,
        assignee: { id: 4, name: 'Alan Turing' },
        project: { id: 1, name: 'Website' },
    }],
    total: 1,
    current_page: 1,
    last_page: 1,
    links: [],
};

const filterOptions = {
    statuses: [{ value: 'in_progress', label: 'In Progress' }],
    priorities: [{ value: 'high', label: 'High' }],
    assignees: [{ id: 4, name: 'Alan Turing' }],
};

const filters = {
    search: null,
    status: null,
    priority: null,
    assignee_id: null,
    due_from: null,
    due_to: null,
};

const emptyTasks = { ...paginatedTasks, data: [], total: 0 };

function mountPage(props = {}) {
    return mount(Index, {
        props: { project, tasks: paginatedTasks, filters, filterOptions, ...props },
    });
}

beforeEach(() => {
    router.get.mockReset();
});

describe('Tasks/Index.vue', () => {
    it('renders the board of a project with every filter', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain('Website Tasks');
        expect(wrapper.get('[data-test="filter-assignee"]').exists()).toBe(true);
        expect(wrapper.get('[data-test="task-row"]').text()).toContain('Ship the website');
    });

    it('hides the project of a single project board', () => {
        const wrapper = mountPage();

        expect(wrapper.find('[data-test="task-row-project"]').exists()).toBe(false);
    });

    it('filters the board through its own url', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-test="filter-status"]').setValue('in_progress');
        await wrapper.get('form').trigger('submit');

        expect(router.get).toHaveBeenCalledWith(
            '/projects/1/tasks',
            { status: 'in_progress' },
            expect.any(Object),
        );
    });

    it('links to the my tasks listing', () => {
        const wrapper = mountPage();

        expect(wrapper.get('a[href="/tasks/mine"]').text()).toBe('My tasks');
    });

    it('paints a skeleton while the listing is on its way', async () => {
        const wrapper = mountPage();

        expect(wrapper.find('[data-test="page-skeleton"]').exists()).toBe(false);

        events.start({ method: 'get', only: [] });
        await nextTick();

        expect(wrapper.find('[data-test="page-skeleton"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="task-row"]').exists()).toBe(false);

        events.finish({ method: 'get', only: [] });
        await nextTick();

        expect(wrapper.find('[data-test="page-skeleton"]').exists()).toBe(false);
        expect(wrapper.find('[data-test="task-row"]').exists()).toBe(true);
    });

    it('points a first run at the project board that creates tasks', () => {
        const wrapper = mountPage({ tasks: emptyTasks });

        expect(wrapper.get('[data-test="task-list-empty"]').text()).toContain('No tasks in Website yet');
        expect(wrapper.get('[data-test="task-list-empty"] a').attributes('href')).toBe('/projects/1');
    });

    it('offers to clear the filters when they hide the tasks', () => {
        const wrapper = mountPage({ tasks: emptyTasks, filters: { ...filters, status: 'todo' } });

        expect(wrapper.get('[data-test="task-list-empty"]').text()).toContain('No task matches the filters');
        expect(wrapper.find('[data-test="task-list-empty"] a').exists()).toBe(false);
    });
});
