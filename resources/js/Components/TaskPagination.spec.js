import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';

vi.mock('@inertiajs/vue3', () => ({
    Link: {
        props: { href: String, preserveScroll: Boolean },
        template: '<a :href="href" :data-preserve="preserveScroll ? \'yes\' : \'no\'"><slot /></a>',
    },
}));

import TaskPagination from './TaskPagination.vue';

const links = [
    { url: null, label: '&laquo; Previous', active: false },
    { url: '/projects/1/tasks?page=1', label: '1', active: true },
    { url: '/projects/1/tasks?status=done&page=2', label: '2', active: false },
];

describe('TaskPagination.vue', () => {
    it('renders every link of the paginator', () => {
        const wrapper = mount(TaskPagination, { props: { links } });

        expect(wrapper.findAll('[data-test="pagination-link"]')).toHaveLength(2);
        expect(wrapper.get('[data-test="pagination-disabled"]').attributes('aria-disabled')).toBe('true');
    });

    it('marks the current page as active', () => {
        const wrapper = mount(TaskPagination, { props: { links } });
        const [current, other] = wrapper.findAll('[data-test="pagination-link"]');

        expect(current.attributes('aria-current')).toBe('page');
        expect(current.attributes('href')).toBe('/projects/1/tasks?page=1');
        expect(other.attributes('aria-current')).toBeUndefined();
    });

    it('keeps the scroll position between pages', () => {
        const wrapper = mount(TaskPagination, { props: { links } });

        expect(wrapper.get('[data-test="pagination-link"]').attributes('data-preserve')).toBe('yes');
    });

    it('renders nothing without links', () => {
        const wrapper = mount(TaskPagination, { props: { links: [] } });

        expect(wrapper.find('[data-test="task-pagination"]').exists()).toBe(false);
    });
});
