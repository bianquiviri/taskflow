import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';

vi.mock('@inertiajs/vue3', () => ({
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

import QuickActions from './QuickActions.vue';

const IconStub = {
    props: ['name'],
    template: '<span data-test="quick-action-icon" :data-name="name" />',
};

const global = { stubs: { Icon: IconStub } };

describe('Dashboard/QuickActions.vue', () => {
    it('links to the pages holding the creation forms', () => {
        const wrapper = mount(QuickActions, { global });
        const actions = wrapper.findAll('[data-test="quick-action"]');

        expect(actions).toHaveLength(2);
        expect(wrapper.get('[data-test="quick-action-projects"]').attributes('href')).toBe('/projects');
        expect(wrapper.get('[data-test="quick-action-projects"]').text()).toContain('New project');
        expect(wrapper.get('[data-test="quick-action-tasks"]').attributes('href')).toBe('/tasks/mine');
        expect(wrapper.get('[data-test="quick-action-tasks"]').text()).toContain('My tasks');
    });

    it('describes where every action leads', () => {
        const wrapper = mount(QuickActions, { global });

        expect(wrapper.get('[data-test="quick-action-projects"] [data-test="quick-action-hint"]').text())
            .toBe('Create a project and invite your team.');
        expect(wrapper.get('[data-test="quick-action-tasks"] [data-test="quick-action-hint"]').text())
            .toBe('Everything assigned to you, soonest first.');
    });

    it('gives every action an icon', () => {
        const wrapper = mount(QuickActions, { global });

        expect(wrapper.get('[data-test="quick-action-projects"] [data-test="quick-action-icon"]').attributes('data-name'))
            .toBe('projects');
        expect(wrapper.get('[data-test="quick-action-tasks"] [data-test="quick-action-icon"]').attributes('data-name'))
            .toBe('tasks');
    });
});
