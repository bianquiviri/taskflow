import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';

const { router } = vi.hoisted(() => ({
    router: { get: vi.fn() },
}));

vi.mock('@inertiajs/vue3', () => ({ router }));

import TaskFilterForm from './TaskFilterForm.vue';

const statuses = [
    { value: 'todo', label: 'To Do' },
    { value: 'done', label: 'Done' },
];
const priorities = [{ value: 'urgent', label: 'Urgent' }];
const assignees = [{ id: 4, name: 'Alan Turing' }];

const emptyFilters = {
    search: null,
    status: null,
    priority: null,
    assignee_id: null,
    due_from: null,
    due_to: null,
};

function mountForm(props = {}) {
    return mount(TaskFilterForm, {
        props: {
            url: '/projects/1/tasks',
            filters: { ...emptyFilters },
            statuses,
            priorities,
            assignees,
            ...props,
        },
    });
}

beforeEach(() => {
    router.get.mockReset();
});

describe('TaskFilterForm.vue', () => {
    it('offers every filter as a field with an empty default option', () => {
        const wrapper = mountForm();

        expect(wrapper.findAll('select option')).toHaveLength(7);
        expect(wrapper.get('[data-test="filter-search"]').element.value).toBe('');
        expect(wrapper.get('[data-test="filter-due-from"]').attributes('type')).toBe('date');
    });

    it('prefills the fields from the applied filters', () => {
        const wrapper = mountForm({
            filters: { ...emptyFilters, search: 'auth', status: 'done', assignee_id: 4 },
        });

        expect(wrapper.get('[data-test="filter-search"]').element.value).toBe('auth');
        expect(wrapper.get('[data-test="filter-status"]').element.value).toBe('done');
        expect(wrapper.get('[data-test="filter-assignee"]').element.value).toBe('4');
    });

    it('visits the listing url with only the filled filters', async () => {
        const wrapper = mountForm();

        await wrapper.get('[data-test="filter-search"]').setValue('login');
        await wrapper.get('[data-test="filter-status"]').setValue('todo');
        await wrapper.get('form').trigger('submit');

        expect(router.get).toHaveBeenCalledWith(
            '/projects/1/tasks',
            { search: 'login', status: 'todo' },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    });

    it('resets to the first page by dropping the page when filters change', async () => {
        const wrapper = mountForm();

        await wrapper.get('[data-test="filter-search"]').setValue('login');
        await wrapper.get('form').trigger('submit');

        expect(router.get.mock.calls[0][1]).not.toHaveProperty('page');
    });

    it('clears every filter and reloads the unfiltered listing', async () => {
        const wrapper = mountForm({ filters: { ...emptyFilters, search: 'auth', status: 'done' } });

        await wrapper.get('[data-test="filter-clear"]').trigger('click');

        expect(wrapper.get('[data-test="filter-search"]').element.value).toBe('');
        expect(wrapper.get('[data-test="filter-status"]').element.value).toBe('');
        expect(router.get).toHaveBeenCalledWith('/projects/1/tasks', {}, expect.any(Object));
    });

    it('hides the clear action while no filter is set', () => {
        const wrapper = mountForm();

        expect(wrapper.find('[data-test="filter-clear"]').exists()).toBe(false);
    });

    it('hides the assignee filter when the listing has no assignee to choose', () => {
        const wrapper = mountForm({ assignees: [] });

        expect(wrapper.find('[data-test="filter-assignee"]').exists()).toBe(false);
    });

    it('follows the filters of a new response', async () => {
        const wrapper = mountForm();

        await wrapper.setProps({ filters: { ...emptyFilters, search: 'queue' } });

        expect(wrapper.get('[data-test="filter-search"]').element.value).toBe('queue');
    });
});
