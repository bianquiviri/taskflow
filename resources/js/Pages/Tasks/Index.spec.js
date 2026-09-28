import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';

const { router } = vi.hoisted(() => ({ router: { get: vi.fn() } }));

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

beforeEach(() => {
    router.get.mockReset();
});

describe('Tasks/Index.vue', () => {
    it('renders the board of a project with every filter', () => {
        const wrapper = mount(Index, {
            props: { project, tasks: paginatedTasks, filters, filterOptions },
        });

        expect(wrapper.text()).toContain('Website Tasks');
        expect(wrapper.get('[data-test="filter-assignee"]').exists()).toBe(true);
        expect(wrapper.get('[data-test="task-row"]').text()).toContain('Ship the website');
    });

    it('hides the project of a single project board', () => {
        const wrapper = mount(Index, {
            props: { project, tasks: paginatedTasks, filters, filterOptions },
        });

        expect(wrapper.find('[data-test="task-row-project"]').exists()).toBe(false);
    });

    it('filters the board through its own url', async () => {
        const wrapper = mount(Index, {
            props: { project, tasks: paginatedTasks, filters, filterOptions },
        });

        await wrapper.get('[data-test="filter-status"]').setValue('in_progress');
        await wrapper.get('form').trigger('submit');

        expect(router.get).toHaveBeenCalledWith(
            '/projects/1/tasks',
            { status: 'in_progress' },
            expect.any(Object),
        );
    });

    it('links to the my tasks listing', () => {
        const wrapper = mount(Index, {
            props: { project, tasks: paginatedTasks, filters, filterOptions },
        });

        expect(wrapper.get('a[href="/tasks/mine"]').text()).toBe('My tasks');
    });
});
