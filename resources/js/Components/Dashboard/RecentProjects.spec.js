import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';

vi.mock('@inertiajs/vue3', () => ({
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

import RecentProjects from './RecentProjects.vue';

const EmptyStateStub = {
    props: ['title', 'description'],
    template: '<section data-test="empty-state"><p>{{ title }}</p><slot name="action" /></section>',
};

const BadgeStub = {
    props: ['tone'],
    template: '<span data-test="badge" :data-tone="tone"><slot /></span>',
};

const global = { stubs: { EmptyState: EmptyStateStub, Badge: BadgeStub } };

const projects = [
    { id: 4, name: 'Website', slug: 'website', tasks: 6, open: 2 },
    { id: 9, name: 'Platform', slug: 'platform', tasks: 2, open: 0 },
];

describe('Dashboard/RecentProjects.vue', () => {
    it('links every project to its page with its task counts', () => {
        const wrapper = mount(RecentProjects, { props: { projects }, global });

        const rows = wrapper.findAll('[data-test="recent-project"]');

        expect(rows).toHaveLength(2);
        expect(wrapper.get('[data-test="recent-project-link"]').attributes('href')).toBe('/projects/4');
        expect(wrapper.get('[data-test="recent-project-link"]').text()).toBe('Website');
        expect(wrapper.get('[data-test="recent-project-open"]').text()).toBe('2 open');
        expect(wrapper.get('[data-test="recent-project-tasks"]').text()).toBe('6 tasks');
    });

    it('offers a link to the whole project list', () => {
        const wrapper = mount(RecentProjects, { props: { projects }, global });

        expect(wrapper.get('[data-test="recent-projects-all"]').attributes('href')).toBe('/projects');
    });

    it('invites a new user to create their first project', () => {
        const wrapper = mount(RecentProjects, { props: { projects: [] }, global });

        expect(wrapper.get('[data-test="empty-state"] p').text()).toBe('No projects yet');
        expect(wrapper.get('[data-test="recent-project-action"]').attributes('href')).toBe('/projects');
    });
});
