import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';

import TaskCard from './TaskCard.vue';

const statuses = [
    { value: 'todo', label: 'To Do', allows: ['todo', 'in_progress', 'cancelled'] },
    { value: 'in_progress', label: 'In Progress', allows: ['todo', 'in_progress', 'in_review', 'cancelled'] },
    { value: 'done', label: 'Done', allows: ['in_progress', 'done'] },
];

const task = {
    id: 7,
    title: 'Fix the login redirect',
    status: 'todo',
    priority: 'urgent',
    due_date: '2026-10-12',
    assignee: { id: 4, name: 'Alan Turing' },
};

function mountCard(props = {}) {
    return mount(TaskCard, {
        props: { task, statuses, canChange: () => true, isPending: () => false, ...props },
        global: {
            stubs: {
                Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
            },
        },
    });
}

describe('TaskCard.vue', () => {
    it('links the title to the task and shows its priority, due date and assignee', () => {
        const wrapper = mountCard();

        expect(wrapper.get('a').attributes('href')).toBe('/tasks/7');
        expect(wrapper.get('a').text()).toBe('Fix the login redirect');
        expect(wrapper.get('[data-test="task-card-priority"]').text()).toBe('Urgent');
        expect(wrapper.get('[data-test="task-card-due"]').text()).toContain('2026');
        expect(wrapper.get('[data-test="task-card-assignee"]').text()).toContain('Alan Turing');
    });

    it('flags an unassigned task and a task without a due date', () => {
        const wrapper = mountCard({ task: { ...task, due_date: null, assignee: null } });

        expect(wrapper.get('[data-test="task-card-assignee"]').text()).toContain('Unassigned');
        expect(wrapper.find('[data-test="task-card-due"]').exists()).toBe(false);
    });

    it('can be dragged only while the user may change the status', () => {
        expect(mountCard().get('[data-test="task-card"]').attributes('draggable')).toBe('true');
        expect(mountCard({ canChange: () => false }).get('[data-test="task-card"]').attributes('draggable'))
            .toBe('false');
    });

    it('announces the grabbed task when a drag starts', async () => {
        const wrapper = mountCard();

        await wrapper.get('[data-test="task-card"]').trigger('dragstart');

        expect(wrapper.emitted('grab')).toEqual([[task]]);
    });

    it('offers only the statuses the domain allows from here, current one selected', () => {
        const wrapper = mountCard();
        const select = wrapper.get('[data-test="task-card-status"]');

        expect(select.findAll('option').map((option) => option.attributes('value')))
            .toEqual(['todo', 'in_progress']);
        expect(select.element.value).toBe('todo');
    });

    it('offers the moves a task in another status may take', () => {
        const wrapper = mountCard({ task: { ...task, status: 'done' } });

        expect(wrapper.get('[data-test="task-card-status"]').findAll('option').map((option) => option.attributes('value')))
            .toEqual(['in_progress', 'done']);
    });

    it('emits the picked status as a change request', async () => {
        const wrapper = mountCard();

        await wrapper.get('[data-test="task-card-status"]').setValue('in_progress');

        expect(wrapper.emitted('change')).toEqual([[task, 'in_progress']]);
    });

    it('hides the status control when the user may not change the status', () => {
        expect(mountCard({ canChange: () => false }).find('[data-test="task-card-status"]').exists())
            .toBe(false);
    });

    it('marks a task whose change is still in flight', () => {
        const wrapper = mountCard({ isPending: (id) => id === 7 });

        expect(wrapper.get('[data-test="task-card"]').attributes('aria-busy')).toBe('true');
        expect(wrapper.get('[data-test="task-card-pending"]').exists()).toBe(true);
    });
});
