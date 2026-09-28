import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';

import TaskList from './TaskList.vue';

const tasks = {
    data: [
        {
            id: 7,
            title: 'Fix the login redirect',
            status: 'in_progress',
            priority: 'urgent',
            due_date: '2026-10-12',
            assignee: { id: 4, name: 'Alan Turing' },
            project: { id: 1, name: 'Website' },
        },
        {
            id: 8,
            title: 'Write the release notes',
            status: 'todo',
            priority: 'low',
            due_date: null,
            assignee: null,
            project: { id: 1, name: 'Website' },
        },
    ],
    total: 2,
    current_page: 1,
    last_page: 1,
    links: [],
};

function mountList(props = {}) {
    return mount(TaskList, {
        props: { tasks, ...props },
        global: {
            stubs: {
                Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
                StatusPill: { props: ['value'], template: '<span data-test="pill">{{ value }}</span>' },
            },
        },
    });
}

describe('TaskList.vue', () => {
    it('renders one row per task with its status, priority and assignee', () => {
        const wrapper = mountList();
        const rows = wrapper.findAll('[data-test="task-row"]');

        expect(rows).toHaveLength(2);
        expect(rows[0].text()).toContain('Fix the login redirect');
        expect(rows[0].find('[data-test="task-row-assignee"]').text()).toContain('Alan Turing');
        expect(rows[0].findAll('[data-test="pill"]').map((pill) => pill.text()))
            .toEqual(['in_progress', 'urgent']);
        expect(rows[0].get('a').attributes('href')).toBe('/tasks/7');
    });

    it('reports how many tasks the listing holds', () => {
        expect(mountList().get('[data-test="task-count"]').text()).toBe('2 tasks');
        expect(mountList({ tasks: { ...tasks, total: 1, data: [tasks.data[0]] } })
            .get('[data-test="task-count"]').text()).toBe('1 task');
    });

    it('formats the due date and flags unassigned tasks', () => {
        const wrapper = mountList();
        const rows = wrapper.findAll('[data-test="task-row"]');

        expect(rows[0].get('[data-test="task-row-due"]').text()).toContain('2026');
        expect(rows[1].find('[data-test="task-row-due"]').exists()).toBe(false);
        expect(rows[1].get('[data-test="task-row-assignee"]').text()).toContain('Unassigned');
    });

    it('shows the project of a cross project listing only when asked', () => {
        expect(mountList().find('[data-test="task-row-project"]').exists()).toBe(false);
        expect(mountList({ showProject: true }).get('[data-test="task-row-project"]').text())
            .toBe('Website');
    });

    it('renders an empty state without tasks', () => {
        const wrapper = mountList({ tasks: { ...tasks, data: [], total: 0 } });

        expect(wrapper.findAll('[data-test="task-row"]')).toHaveLength(0);
        expect(wrapper.get('[data-test="task-list-empty"]').text()).toContain('No tasks found');
        expect(wrapper.find('[data-test="task-count"]').exists()).toBe(false);
    });

    it('renders the paginator links of the listing', () => {
        const links = [
            { url: null, label: '&laquo; Previous', active: false },
            { url: '/projects/1/tasks?page=1', label: '1', active: true },
            { url: '/projects/1/tasks?status=done&page=2', label: '2', active: false },
        ];
        const wrapper = mountList({ tasks: { ...tasks, links } });

        expect(wrapper.findAll('[data-test="pagination-link"]').map((link) => link.attributes('href')))
            .toEqual(['/projects/1/tasks?page=1', '/projects/1/tasks?status=done&page=2']);
        expect(wrapper.get('[data-test="pagination-disabled"]').text()).toContain('Previous');
    });
});
