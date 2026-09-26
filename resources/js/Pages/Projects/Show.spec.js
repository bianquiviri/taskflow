import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';

vi.mock('@inertiajs/vue3', () => ({
    Head: {
        props: ['title'],
        template: '<div />',
    },
}));

import Show from './Show.vue';

const project = { id: 1, name: 'Website Redesign', description: 'Revamp the marketing site.' };

const paginator = (data) => ({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: data.length,
    data,
});

const mountPage = (activity) =>
    mount(Show, { props: { project, activity: paginator(activity) } });

describe('Projects/Show.vue', () => {
    it('renders the project name and description', () => {
        const wrapper = mountPage([]);

        expect(wrapper.text()).toContain('Website Redesign');
        expect(wrapper.text()).toContain('Revamp the marketing site.');
    });

    it('renders a fallback description when the project has none', () => {
        const wrapper = mount(Show, {
            props: { project: { ...project, description: null }, activity: paginator([]) },
        });

        expect(wrapper.text()).toContain('No description.');
    });

    it('renders one activity entry per log with actor, event and time', () => {
        const wrapper = mountPage([
            {
                id: 12,
                event: 'project.archived',
                actor: { id: 3, name: 'Ada Lovelace' },
                created_at: '2026-03-12T10:15:00.000000Z',
            },
            {
                id: 11,
                event: 'project.created',
                actor: null,
                created_at: '2026-03-11T08:30:00.000000Z',
            },
        ]);

        const entries = wrapper.findAll('[data-test="activity-entry"]');

        expect(entries).toHaveLength(2);
        expect(entries[0].text()).toContain('Ada Lovelace');
        expect(entries[0].text()).toContain('archived the project');
        expect(entries[0].text()).toContain('2026');
        expect(entries[1].text()).toContain('Unknown actor');
        expect(entries[1].text()).toContain('created the project');
    });

    it('humanises unknown events', () => {
        const wrapper = mountPage([
            {
                id: 1,
                event: 'task.status_changed',
                actor: { id: 3, name: 'Ada Lovelace' },
                created_at: '2026-03-12T10:15:00.000000Z',
            },
        ]);

        expect(wrapper.find('[data-test="activity-entry"]').text()).toContain('Task status changed');
    });

    it('shows an empty state when the project has no activity', () => {
        const wrapper = mountPage([]);

        expect(wrapper.find('[data-test="activity-empty"]').exists()).toBe(true);
        expect(wrapper.findAll('[data-test="activity-entry"]')).toHaveLength(0);
    });
});
