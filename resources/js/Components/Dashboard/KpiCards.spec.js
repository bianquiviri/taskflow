import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import KpiCards from './KpiCards.vue';

const IconStub = {
    props: ['name'],
    template: '<span data-test="kpi-icon" :data-name="name" />',
};

const global = { stubs: { Icon: IconStub } };

const kpis = { projects: 3, open: 12, overdue: 2, done: 8, total: 21 };

describe('Dashboard/KpiCards.vue', () => {
    it('renders one card per metric with its value', () => {
        const wrapper = mount(KpiCards, { props: { kpis }, global });

        const cards = wrapper.findAll('[data-test="kpi-card"]');

        expect(cards).toHaveLength(4);
        expect(wrapper.get('[data-card="projects"] [data-test="kpi-card-value"]').text()).toBe('3');
        expect(wrapper.get('[data-card="open"] [data-test="kpi-card-value"]').text()).toBe('12');
        expect(wrapper.get('[data-card="overdue"] [data-test="kpi-card-value"]').text()).toBe('2');
        expect(wrapper.get('[data-card="done"] [data-test="kpi-card-value"]').text()).toBe('8');
    });

    it('labels every card and gives each an icon', () => {
        const wrapper = mount(KpiCards, { props: { kpis }, global });

        expect(wrapper.get('[data-card="projects"] [data-test="kpi-card-label"]').text()).toBe('Active projects');
        expect(wrapper.get('[data-card="open"] [data-test="kpi-card-label"]').text()).toBe('Open tasks');
        expect(wrapper.get('[data-card="overdue"] [data-test="kpi-card-label"]').text()).toBe('Overdue tasks');
        expect(wrapper.get('[data-card="done"] [data-test="kpi-card-label"]').text()).toBe('Completed');

        expect(wrapper.get('[data-card="overdue"] [data-test="kpi-icon"]').attributes('data-name')).toBe('warning');
        expect(wrapper.findAll('[data-test="kpi-icon"]')).toHaveLength(4);
    });

    it('hints the share of completed tasks', () => {
        const wrapper = mount(KpiCards, { props: { kpis }, global });

        expect(wrapper.get('[data-card="done"] [data-test="kpi-card-hint"]').text()).toBe('38% of all tasks');
    });

    it('flags the overdue card only when something is late', async () => {
        const late = mount(KpiCards, { props: { kpis }, global });
        const calm = mount(KpiCards, { props: { kpis: { ...kpis, overdue: 0 } }, global });

        expect(late.get('[data-card="overdue"]').attributes('data-alert')).toBe('true');
        expect(calm.get('[data-card="overdue"]').attributes('data-alert')).toBe('false');
        await Promise.resolve();
    });

    it('renders zeroes for a user without any work', () => {
        const wrapper = mount(KpiCards, {
            props: { kpis: { projects: 0, open: 0, overdue: 0, done: 0, total: 0 } },
            global,
        });

        expect(wrapper.findAll('[data-test="kpi-card-value"]').map((value) => value.text())).toEqual([
            '0',
            '0',
            '0',
            '0',
        ]);
        expect(wrapper.find('[data-test="kpi-card-hint"]').exists()).toBe(false);
    });
});
