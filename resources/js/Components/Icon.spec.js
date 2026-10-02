import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import Icon from './Icon.vue';

describe('Icon.vue', () => {
    it.each([
        'bell',
        'check',
        'close',
        'home',
        'info',
        'logo',
        'logout',
        'menu',
        'moon',
        'plus',
        'projects',
        'settings',
        'sun',
        'tasks',
        'user',
        'warning',
    ])('renders an svg for the %s icon', (name) => {
        const wrapper = mount(Icon, { props: { name } });

        expect(wrapper.find('svg').exists()).toBe(true);
    });

    it('renders nothing for an unknown icon name', () => {
        const wrapper = mount(Icon, { props: { name: 'unknown' } });

        expect(wrapper.find('svg').exists()).toBe(false);
    });

    it('forwards the class attribute to the svg element', () => {
        const wrapper = mount(Icon, { props: { name: 'home' }, attrs: { class: 'size-5 text-red-500' } });

        expect(wrapper.find('svg').classes()).toContain('size-5');
        expect(wrapper.find('svg').classes()).toContain('text-red-500');
    });

    it('hides every icon from assistive technology', () => {
        const wrapper = mount(Icon, { props: { name: 'home' } });

        expect(wrapper.get('svg').attributes('aria-hidden')).toBe('true');
        expect(wrapper.get('svg').attributes('focusable')).toBe('false');
    });

    it('carries no accessible name of its own', () => {
        const wrapper = mount(Icon, { props: { name: 'home' } });

        expect(wrapper.get('svg').attributes('role')).toBeUndefined();
        expect(wrapper.get('svg').attributes('aria-label')).toBeUndefined();
    });
});