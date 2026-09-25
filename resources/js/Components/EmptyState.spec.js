import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import EmptyState from './EmptyState.vue';

const IconStub = {
    props: ['name'],
    template: '<span data-test="empty-state-icon" :data-name="name" />',
};

describe('EmptyState.vue', () => {
    it('renders its icon, title, and description', () => {
        const wrapper = mount(EmptyState, {
            props: {
                icon: 'tasks',
                title: 'No tasks yet',
                description: 'Create a task to get started.',
            },
            global: {
                stubs: { Icon: IconStub },
            },
        });

        expect(wrapper.get('[data-test="empty-state-icon"]').attributes('data-name')).toBe('tasks');
        expect(wrapper.get('h3').text()).toBe('No tasks yet');
        expect(wrapper.text()).toContain('Create a task to get started.');
    });

    it('renders the action slot', () => {
        const wrapper = mount(EmptyState, {
            props: { title: 'No projects yet' },
            slots: { action: '<button>New project</button>' },
        });

        expect(wrapper.get('[data-test="empty-state-action"]').text()).toBe('New project');
    });

    it('omits optional icon, description, and action content', () => {
        const wrapper = mount(EmptyState, {
            props: { title: 'Nothing here' },
        });

        expect(wrapper.find('svg').exists()).toBe(false);
        expect(wrapper.text()).toBe('Nothing here');
        expect(wrapper.find('[data-test="empty-state-action"]').exists()).toBe(false);
    });
});
