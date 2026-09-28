import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';

const { router } = vi.hoisted(() => ({ router: { get: vi.fn() } }));

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

beforeEach(() => {
    router.get.mockReset();
});

describe('Tasks/Mine.vue', () => {
    it('renders the shared filters and the listing', () => {
        const wrapper = mount(Mine, {
            props: { tasks: paginatedTasks, filters, filterOptions },
        });

        expect(wrapper.get('[data-test="task-filter-form"]').exists()).toBe(true);
        expect(wrapper.get('[data-test="task-row"]').text()).toContain('Book the venue');
        expect(wrapper.find('[data-test="filter-assignee"]').exists()).toBe(false);
    });

    it('shows the project of every task', () => {
        const wrapper = mount(Mine, {
            props: { tasks: paginatedTasks, filters, filterOptions },
        });

        expect(wrapper.get('[data-test="task-row-project"]').text()).toBe('Website');
    });

    it('filters the my tasks listing', async () => {
        const wrapper = mount(Mine, {
            props: { tasks: paginatedTasks, filters, filterOptions },
        });

        await wrapper.get('[data-test="filter-search"]').setValue('venue');
        await wrapper.get('form').trigger('submit');

        expect(router.get).toHaveBeenCalledWith('/tasks/mine', { search: 'venue' }, expect.any(Object));
    });
});
