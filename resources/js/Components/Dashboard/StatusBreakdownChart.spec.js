import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import StatusBreakdownChart from './StatusBreakdownChart.vue';

const series = [
    { value: 'todo', label: 'To Do', count: 3 },
    { value: 'in_progress', label: 'In Progress', count: 1 },
    { value: 'in_review', label: 'In Review', count: 0 },
    { value: 'done', label: 'Done', count: 4 },
    { value: 'cancelled', label: 'Cancelled', count: 0 },
];

describe('Dashboard/StatusBreakdownChart.vue', () => {
    it('renders a labelled row per status with its count', () => {
        const wrapper = mount(StatusBreakdownChart, { props: { series } });
        const rows = wrapper.findAll('[data-test="status-row"]');

        expect(rows).toHaveLength(5);
        expect(wrapper.get('[data-status="todo"] [data-test="status-row-label"]').text()).toBe('To Do');
        expect(wrapper.get('[data-status="todo"] [data-test="status-row-count"]').text()).toBe('3');
        expect(wrapper.get('[data-status="done"] [data-test="status-row-count"]').text()).toBe('4');
    });

    it('sizes every bar against the biggest status', () => {
        const wrapper = mount(StatusBreakdownChart, { props: { series } });
        const width = (status) => wrapper.get(`[data-status="${status}"] [data-test="status-row-bar"]`).attributes('style');

        expect(width('done')).toContain('100%');
        expect(width('todo')).toContain('75%');
        expect(width('in_progress')).toContain('25%');
    });

    it('reports the total and the share of each status', () => {
        const wrapper = mount(StatusBreakdownChart, { props: { series } });

        expect(wrapper.get('[data-test="status-chart-total"]').text()).toBe('8 tasks in your projects');
        expect(wrapper.get('[data-status="todo"] [data-test="status-row-share"]').text()).toBe('38%');
    });

    it('hides the bars of an empty status but keeps its column', () => {
        const wrapper = mount(StatusBreakdownChart, { props: { series } });

        expect(wrapper.get('[data-status="cancelled"] [data-test="status-row-bar"]').attributes('style')).toContain('0%');
        expect(wrapper.get('[data-status="cancelled"] [data-test="status-row-share"]').text()).toBe('0%');
    });

    it('keeps the bars decorative so the numbers are the accessible content', () => {
        const wrapper = mount(StatusBreakdownChart, { props: { series } });
        const bar = wrapper.get('[data-test="status-row-bar"]');

        expect(bar.element.closest('[aria-hidden="true"]')).not.toBe(null);
    });

    it('shows an empty state when the user has no tasks', () => {
        const wrapper = mount(StatusBreakdownChart, {
            props: { series: series.map((row) => ({ ...row, count: 0 })) },
        });

        expect(wrapper.get('[data-test="status-chart-empty"]').text()).toBe('No tasks yet');
        expect(wrapper.find('[data-test="status-row"]').exists()).toBe(false);
        expect(wrapper.find('[data-test="status-chart-total"]').exists()).toBe(false);
    });
});
