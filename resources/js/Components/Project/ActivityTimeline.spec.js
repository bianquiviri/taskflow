import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';

vi.mock('@inertiajs/vue3', () => ({
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

import ActivityTimeline from './ActivityTimeline.vue';

const entry = (overrides = {}) => ({
    id: 1,
    event: 'task.status_changed',
    actor: { id: 3, name: 'Ada Lovelace' },
    label: 'moved the task to In Progress',
    target: { type: 'task', id: 12, title: 'Ship the release', url: '/tasks/12' },
    icon: 'check',
    at: '2026-09-28T10:15:00+00:00',
    day: '2026-09-28',
    ...overrides,
});

const paginator = (data, extra = {}) => ({
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: data.length,
    data,
    links: [],
    prev_page_url: null,
    next_page_url: null,
    ...extra,
});

describe('Project/ActivityTimeline.vue', () => {
    it('reads every entry as actor, action and target', () => {
        const wrapper = mount(ActivityTimeline, { props: { activity: paginator([entry()]) } });
        const item = wrapper.get('[data-test="activity-entry"]');

        expect(item.text()).toContain('Ada Lovelace');
        expect(item.text()).toContain('moved the task to In Progress');
        expect(wrapper.get('[data-test="activity-target"]').text()).toBe('Ship the release');
        expect(wrapper.get('[data-test="activity-time"]').attributes('datetime')).toBe('2026-09-28T10:15:00+00:00');
        expect(wrapper.get('[data-test="activity-time"]').text()).toContain('10:15');
    });

    it('links the target while it is still there and names it otherwise', () => {
        const wrapper = mount(ActivityTimeline, {
            props: {
                activity: paginator([
                    entry(),
                    entry({ id: 2, target: { type: 'project', id: 1, title: 'Website Redesign', url: null } }),
                ]),
            },
        });

        const targets = wrapper.findAll('[data-test="activity-target"]');

        expect(targets[0].element.tagName).toBe('A');
        expect(targets[0].attributes('href')).toBe('/tasks/12');
        expect(targets[1].element.tagName).not.toBe('A');
        expect(targets[1].text()).toBe('Website Redesign');
    });

    it('groups the entries under the day they happened on', () => {
        const wrapper = mount(ActivityTimeline, {
            props: {
                activity: paginator([
                    entry({ id: 1, day: '2026-09-28' }),
                    entry({ id: 2, day: '2026-09-28' }),
                    entry({ id: 3, day: '2026-09-25' }),
                ]),
            },
        });

        const days = wrapper.findAll('[data-test="activity-day"]');

        expect(days).toHaveLength(2);
        expect(days[0].get('[data-test="activity-day-label"]').text()).toBe('Mon 28 Sept 2026');
        expect(days[0].findAll('[data-test="activity-entry"]')).toHaveLength(2);
        expect(days[1].get('[data-test="activity-day-label"]').text()).toBe('Fri 25 Sept 2026');
        expect(days[1].findAll('[data-test="activity-entry"]')).toHaveLength(1);
    });

    it('shows the icon of every action', () => {
        const wrapper = mount(ActivityTimeline, {
            props: { activity: paginator([entry(), entry({ id: 2, icon: 'tasks' })]) },
        });

        expect(wrapper.findAll('[data-test="activity-icon"]').map((icon) => icon.attributes('data-icon')))
            .toEqual(['check', 'tasks']);
    });

    it('says so when nothing has happened yet', () => {
        const wrapper = mount(ActivityTimeline, { props: { activity: paginator([]) } });

        expect(wrapper.get('[data-test="activity-empty"]').text()).toContain('No activity yet');
        expect(wrapper.find('[data-test="activity-entry"]').exists()).toBe(false);
    });

    it('pages the feed with its own links', () => {
        const wrapper = mount(ActivityTimeline, {
            props: {
                activity: paginator([entry()], {
                    current_page: 2,
                    last_page: 3,
                    total: 25,
                    prev_page_url: '/projects/1?activity_page=1',
                    next_page_url: '/projects/1?activity_page=3',
                }),
            },
        });

        expect(wrapper.get('[data-test="activity-page"]').text()).toBe('Page 2 of 3');
        expect(wrapper.get('[data-test="activity-previous"]').attributes('href')).toBe('/projects/1?activity_page=1');
        expect(wrapper.get('[data-test="activity-next"]').attributes('href')).toBe('/projects/1?activity_page=3');
    });

    it('hides the pager and one of its links on the edges', () => {
        const last = mount(ActivityTimeline, {
            props: {
                activity: paginator([entry()], {
                    current_page: 3,
                    last_page: 3,
                    prev_page_url: '/projects/1?activity_page=2',
                }),
            },
        });

        expect(last.get('[data-test="activity-previous"]').exists()).toBe(true);
        expect(last.find('[data-test="activity-next"]').exists()).toBe(false);

        const single = mount(ActivityTimeline, { props: { activity: paginator([entry()]) } });

        expect(single.find('[data-test="activity-pagination"]').exists()).toBe(false);
    });

    describe('pager', () => {
        const paged = (overrides) => paginator([entry()], {
            current_page: 2,
            last_page: 3,
            prev_page_url: '/projects/1?activity_page=1',
            next_page_url: '/projects/1?activity_page=3',
            ...overrides,
        });

        it('announces the page it lands on', async () => {
            const wrapper = mount(ActivityTimeline, { props: { activity: paged() } });

            const status = wrapper.get('[data-test="activity-page"]');

            expect(status.attributes('role')).toBe('status');

            await wrapper.setProps({
                activity: paged({
                    current_page: 3,
                    prev_page_url: '/projects/1?activity_page=2',
                    next_page_url: null,
                }),
            });

            expect(wrapper.get('[data-test="activity-page"]').element).toBe(status.element);
            expect(status.text()).toBe('Page 3 of 3');
        });

        it('labels both page links', () => {
            const wrapper = mount(ActivityTimeline, { props: { activity: paged() } });

            const nav = wrapper.get('[data-test="activity-pagination"]');

            expect(nav.attributes('aria-label')).toBe('Activity pages');
            expect(wrapper.get('[data-test="activity-previous"]').text()).toBe('Newer');
            expect(wrapper.get('[data-test="activity-next"]').text()).toBe('Older');
        });
    });
});
