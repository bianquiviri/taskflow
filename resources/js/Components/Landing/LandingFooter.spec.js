import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';

vi.mock('@inertiajs/vue3', () => ({
    Link: {
        props: ['href'],
        template: '<a :href="href"><slot /></a>',
    },
}));

import LandingFooter from './LandingFooter.vue';

const mountFooter = (props = {}) => mount(LandingFooter, {
    props: { appName: 'TaskFlow', version: 'v13.0.0', ...props },
});

describe('LandingFooter.vue', () => {
    it('names the product and shows the running version', () => {
        const wrapper = mountFooter();

        expect(wrapper.text()).toContain('TaskFlow');
        expect(wrapper.text()).toContain('v13.0.0');
    });

    it('repeats the sign-in and registration actions', () => {
        const wrapper = mountFooter();

        expect(wrapper.get('a[href="#signin"]').text()).toBe('Sign in');
        expect(wrapper.get('a[href="/register"]').text()).toBe('Create an account');
    });

    it('drops the guest calls to action when they are disabled', () => {
        const wrapper = mountFooter({ showCtas: false });

        expect(wrapper.find('a[href="#signin"]').exists()).toBe(false);
        expect(wrapper.find('a[href="/register"]').exists()).toBe(false);
    });

    it('keeps the product line and the credit line when the calls to action are disabled', () => {
        const wrapper = mountFooter({ showCtas: false });

        expect(wrapper.text()).toContain('TaskFlow');
        expect(wrapper.text()).toContain('CC BY-NC-ND 4.0');
    });

    it('does not repeat the brand lockup', () => {
        const wrapper = mountFooter();

        expect(wrapper.find('a[href="/"]').exists()).toBe(false);
        expect(wrapper.find('[aria-label="TaskFlow home"]').exists()).toBe(false);
    });

    it('shows the running version', () => {
        const wrapper = mountFooter({ version: 'v13.32.0' });

        expect(wrapper.text()).toContain('v13.32.0');
    });

    it('leaves the version blank when the build has none', () => {
        const wrapper = mountFooter({ version: '' });

        expect(wrapper.text()).not.toContain('v13.');
    });

    it('credits the author with a link to GitHub', () => {
        const wrapper = mountFooter();
        const link = wrapper.get('a[href="https://github.com/bianquiviri"]');

        expect(link.text()).toBe('Jose Antonio Bianco');
        expect(link.attributes('target')).toBe('_blank');
        expect(link.attributes('rel')).toContain('noopener');
    });

    it('links to the project license', () => {
        const wrapper = mountFooter();
        const link = wrapper.get('a[href="https://creativecommons.org/licenses/by-nc-nd/4.0/"]');

        expect(link.text()).toBe('CC BY-NC-ND 4.0');
    });
});
