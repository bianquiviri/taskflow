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

import Mine from './Mine.vue';

const filterOptions = {
    statuses: [{ value: 'todo', label: 'To Do' }],
    priorities: [{ value: 'urgent', label: 'Urgent' }],
    assignees: [],
};

const paginatedTasks = {
    data: [{
        id: 3,
        title: 'Book the venue',
        status: 'todo',
        priority: 'urgent',
        due_date: null,
        assignee: { id: 4, name: 'Alan Turing' },
        project: { id: 1, name: 'Website' },
    }],
    total: 1,
    current_page: 1,
    last_page: 1,
    links: [],
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
    return mount(Mine, {
        props: { tasks: paginatedTasks, filters, filterOptions, ...props },
    });
}

beforeEach(() => {
    router.get.mockReset();
});

describe('Tasks/Mine.vue', () => {
    it('renders the shared filters and the listing', () => {
        const wrapper = mountPage();

        expect(wrapper.get('[data-test="task-filter-form"]').exists()).toBe(true);
        expect(wrapper.get('[data-test="task-row"]').text()).toContain('Book the venue');
        expect(wrapper.find('[data-test="filter-assignee"]').exists()).toBe(false);
    });

    it('shows the project of every task', () => {
        const wrapper = mountPage();

        expect(wrapper.get('[data-test="task-row-project"]').text()).toBe('Website');
    });

    it('filters the my tasks listing', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-test="filter-search"]').setValue('venue');
        await wrapper.get('form').trigger('submit');

        expect(router.get).toHaveBeenCalledWith('/tasks/mine', { search: 'venue' }, expect.any(Object));
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

    it('explains an empty personal queue and offers the projects', () => {
        const wrapper = mountPage({ tasks: emptyTasks });

        expect(wrapper.get('[data-test="task-list-empty"]').text()).toContain('Nothing is assigned to you');
        expect(wrapper.get('[data-test="task-list-empty"] a').attributes('href')).toBe('/projects');
    });

    it('offers to clear the filters when they hide the tasks', () => {
        const wrapper = mountPage({ tasks: emptyTasks, filters: { ...filters, search: 'venue' } });

        expect(wrapper.get('[data-test="task-list-empty"]').text()).toContain('No task matches the filters');
        expect(wrapper.find('[data-test="task-list-empty"] a').exists()).toBe(false);
    });
});
