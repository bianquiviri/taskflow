import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import DashboardSkeleton from './DashboardSkeleton.vue';
import Skeleton from '../Skeleton.vue';

describe('Dashboard/DashboardSkeleton.vue', () => {
    it('announces the pending load', () => {
        const wrapper = mount(DashboardSkeleton);

        expect(wrapper.attributes('role')).toBe('status');
        expect(wrapper.attributes('aria-busy')).toBe('true');
        expect(wrapper.text()).toContain('Loading');
    });

    it('mirrors the dashboard layout with placeholders', () => {
        const wrapper = mount(DashboardSkeleton);

        expect(wrapper.findAll('[data-test="dashboard-skeleton-kpi"]')).toHaveLength(4);
        expect(wrapper.findAll('[data-test="dashboard-skeleton-panel"]')).toHaveLength(5);
        expect(wrapper.findAllComponents(Skeleton).length).toBeGreaterThan(4);
    });
});