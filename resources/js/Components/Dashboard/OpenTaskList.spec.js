import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';

vi.mock('@inertiajs/vue3', () => ({
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

import OpenTaskList from './OpenTaskList.vue';

const EmptyStateStub = {
    props: ['title', 'description'],
    template: '<section data-test="empty-state"><p>{{ title }}</p><slot name="action" /></section>',
};

const StatusPillStub = {
    props: ['value'],
    template: '<span data-test="status-pill" :data-value="value" />',
};

const BadgeStub = {
    props: ['tone'],
    template: '<span data-test="badge" :data-tone="tone"><slot /></span>',
};

const global = { stubs: { EmptyState: EmptyStateStub, StatusPill: StatusPillStub, Badge: BadgeStub } };

const tasks = [
    {
        id: 12,
        title: 'Fix the checkout',
        status: 'in_progress',
        priority: 'urgent',
        due_date: '2026-09-01',
        overdue: true,
        project: { id: 3, name: 'Website' },
    },
    {
        id: 13,
        title: 'Ship the landing page',
        status: 'todo',
        priority: 'medium',
        due_date: '2026-12-24',
        overdue: false,
        project: { id: 3, name: 'Website' },
    },
    {
        id: 14,
        title: 'Write the runbook',
        status: 'todo',
        priority: 'low',
        due_date: null,
        overdue: false,
        project: { id: 5, name: 'Platform' },
    },
];

describe('Dashboard/OpenTaskList.vue', () => {
    it('links every open task to its page with its project', () => {
        const wrapper = mount(OpenTaskList, { props: { tasks }, global });
        const rows = wrapper.findAll('[data-test="open-task"]');

        expect(rows).toHaveLength(3);
        expect(wrapper.get('[data-test="open-task-link"]').attributes('href')).toBe('/tasks/12');
        expect(wrapper.get('[data-test="open-task-link"]').text()).toBe('Fix the checkout');
        expect(wrapper.get('[data-test="open-task-project"]').text()).toBe('Website');
    });

    it('shows the status and priority of every task', () => {
        const wrapper = mount(OpenTaskList, { props: { tasks }, global });

        expect(wrapper.findAll('[data-test="status-pill"]').map((pill) => pill.attributes('data-value'))).toEqual([
            'in_progress',
            'urgent',
            'todo',
            'medium',
            'todo',
            'low',
        ]);
    });

    it('replaces the due date of a late task with an overdue badge', () => {
        const wrapper = mount(OpenTaskList, { props: { tasks }, global });

        expect(wrapper.get('[data-test="open-task-overdue"]').text()).toBe('Overdue');
        expect(wrapper.get('[data-test="open-task-overdue"]').attributes('data-tone')).toBe('red');
        expect(wrapper.findAll('[data-test="open-task-due"]')).toHaveLength(1);
        expect(wrapper.get('[data-test="open-task-due"]').text()).toBe('Due 24 Dec 2026');
    });

    it('says nothing about a task without a due date', () => {
        const wrapper = mount(OpenTaskList, { props: { tasks: [tasks[2]] }, global });

        expect(wrapper.find('[data-test="open-task-due"]').exists()).toBe(false);
        expect(wrapper.find('[data-test="open-task-overdue"]').exists()).toBe(false);
    });

    it('links to the personal task list', () => {
        const wrapper = mount(OpenTaskList, { props: { tasks }, global });

        expect(wrapper.get('[data-test="open-tasks-all"]').attributes('href')).toBe('/tasks/mine');
    });

    it('celebrates a user with nothing left to do', () => {
        const wrapper = mount(OpenTaskList, { props: { tasks: [] }, global });

        expect(wrapper.get('[data-test="empty-state"] p').text()).toBe('Nothing open');
        expect(wrapper.find('[data-test="open-task"]').exists()).toBe(false);
    });
});
