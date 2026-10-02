import { describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import { mount } from '@vue/test-utils';

const { events, pageState, router } = vi.hoisted(() => {
    const events = {};
    const stop = vi.fn();

    return {
        events,
        pageState: { url: '/dashboard', props: { can: [], auth: { team: null } } },
        router: {
            on: vi.fn((name, handler) => {
                events[name] = handler;

                return stop;
            }),
        },
    };
});

vi.mock('@inertiajs/vue3', () => ({
    Head: { props: ['title'], template: '<div data-test="head" :data-title="title" />' },
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
    router,
    usePage: () => pageState,
}));

import Index from './Index.vue';
import Checklist from '../../Components/Onboarding/Checklist.vue';
import CompletionDonut from '../../Components/Dashboard/CompletionDonut.vue';
import DashboardSkeleton from '../../Components/Dashboard/DashboardSkeleton.vue';
import KpiCards from '../../Components/Dashboard/KpiCards.vue';
import OpenTaskList from '../../Components/Dashboard/OpenTaskList.vue';
import QuickActions from '../../Components/Dashboard/QuickActions.vue';
import RecentProjects from '../../Components/Dashboard/RecentProjects.vue';
import StatusBreakdownChart from '../../Components/Dashboard/StatusBreakdownChart.vue';

const props = {
    kpis: { projects: 2, open: 3, overdue: 1, done: 4, total: 8 },
    statusBreakdown: [
        { value: 'todo', label: 'To Do', count: 2 },
        { value: 'in_progress', label: 'In Progress', count: 1 },
        { value: 'in_review', label: 'In Review', count: 0 },
        { value: 'done', label: 'Done', count: 4 },
        { value: 'cancelled', label: 'Cancelled', count: 1 },
    ],
    recentProjects: [{ id: 1, name: 'Website', slug: 'website', tasks: 6, open: 2 }],
    openTasks: [{
        id: 7,
        title: 'Fix the checkout',
        status: 'todo',
        priority: 'high',
        due_date: '2026-09-01',
        overdue: true,
        project: { id: 1, name: 'Website' },
    }],
};

const emptyProps = {
    kpis: { projects: 0, open: 0, overdue: 0, done: 0, total: 0 },
    statusBreakdown: props.statusBreakdown.map((row) => ({ ...row, count: 0 })),
    recentProjects: [],
    openTasks: [],
};

const mountPage = (overrides = {}) => mount(Index, { props: { ...props, ...overrides } });

describe('Dashboard/Index.vue', () => {
    it('hands the metrics to the cards and the charts without fetching anything', () => {
        const wrapper = mountPage();

        expect(wrapper.findComponent(KpiCards).props('kpis')).toEqual(props.kpis);
        expect(wrapper.findComponent(StatusBreakdownChart).props('series')).toEqual(props.statusBreakdown);
        expect(wrapper.findComponent(CompletionDonut).props()).toMatchObject({ done: 4, open: 3 });
    });

    it('hands the lists their rows', () => {
        const wrapper = mountPage();

        expect(wrapper.findComponent(RecentProjects).props('projects')).toEqual(props.recentProjects);
        expect(wrapper.findComponent(OpenTaskList).props('tasks')).toEqual(props.openTasks);
        expect(wrapper.findComponent(QuickActions).exists()).toBe(true);
    });

    it('renders the whole dashboard for a user with work', () => {
        const wrapper = mountPage();

        expect(wrapper.get('h1').text()).toBe('Dashboard');
        expect(wrapper.findAll('[data-test="kpi-card"]')).toHaveLength(4);
        expect(wrapper.get('[data-status="done"] [data-test="status-row-count"]').text()).toBe('4');
        expect(wrapper.get('[data-test="recent-project-link"]').text()).toBe('Website');
        expect(wrapper.get('[data-test="open-task-link"]').text()).toBe('Fix the checkout');
    });

    it('falls back to empty states for a brand new user', () => {
        const wrapper = mountPage(emptyProps);

        expect(wrapper.get('[data-test="status-chart-empty"]').text()).toBe('No tasks yet');
        expect(wrapper.get('[data-test="donut-empty"]').text()).toBe('No tasks yet');
        expect(wrapper.text()).toContain('No projects yet');
        expect(wrapper.text()).toContain('Nothing open');
        expect(wrapper.find('[data-test="recent-project"]').exists()).toBe(false);
        expect(wrapper.find('[data-test="open-task"]').exists()).toBe(false);
    });

    it('sets the page title', () => {
        const wrapper = mountPage();

        expect(wrapper.get('[data-test="head"]').attributes('data-title')).toBe('Dashboard');
    });

    it('paints the dashboard skeleton while the page is on its way', async () => {
        const wrapper = mountPage();

        expect(wrapper.findComponent(DashboardSkeleton).exists()).toBe(false);

        events.start({ method: 'get', only: [] });
        await nextTick();

        expect(wrapper.findComponent(DashboardSkeleton).exists()).toBe(true);
        expect(wrapper.findComponent(KpiCards).exists()).toBe(false);

        events.finish({ method: 'get', only: [] });
        await nextTick();

        expect(wrapper.findComponent(DashboardSkeleton).exists()).toBe(false);
        expect(wrapper.findComponent(KpiCards).exists()).toBe(true);
    });

    it('guides a first run through the checklist until the first project exists', () => {
        const wrapper = mountPage(emptyProps);

        expect(wrapper.findComponent(Checklist).exists()).toBe(true);
        expect(wrapper.findComponent(Checklist).props()).toMatchObject({
            hasTeam: false,
            hasProject: false,
            hasTask: false,
            teamId: null,
            projectId: null,
        });
    });

    it('hands the checklist the team of a first run user', () => {
        pageState.props.auth.team = { id: 4, name: 'Acme' };

        const wrapper = mountPage(emptyProps);

        expect(wrapper.findComponent(Checklist).props()).toMatchObject({ hasTeam: true, teamId: 4 });

        pageState.props.auth.team = null;
    });

    it('drops the checklist once the user has a project', () => {
        expect(mountPage().findComponent(Checklist).exists()).toBe(false);
    });
});
