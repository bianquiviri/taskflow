import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';

vi.mock('@inertiajs/vue3', () => ({
    Head: {
        props: ['title'],
        template: '<div />',
    },
}));

import Welcome from './Welcome.vue';

describe('Welcome.vue', () => {
    it('renders the application name', () => {
        const wrapper = mount(Welcome, {
            props: { appName: 'TaskFlow', version: 'v13.0.0' },
        });

        expect(wrapper.text()).toContain('TaskFlow');
    });

    it('renders the Laravel framework version', () => {
        const wrapper = mount(Welcome, {
            props: { appName: 'TaskFlow', version: 'v13.32.0' },
        });

        expect(wrapper.text()).toContain('v13.32.0');
        expect(wrapper.text()).toContain('Laravel');
    });
});