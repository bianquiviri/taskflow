import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import Badge from './Badge.vue';

describe('Badge.vue', () => {
    it.each([
        ['indigo', 'bg-brand-soft'],
        ['sky', 'bg-info-soft'],
        ['gray', 'bg-sunken'],
        ['green', 'bg-success-soft'],
        ['amber', 'bg-warning-soft'],
        ['red', 'bg-danger-soft'],
    ])('renders the %s tone', (tone, expectedClass) => {
        const wrapper = mount(Badge, {
            props: { tone },
            slots: { default: 'Label' },
        });

        expect(wrapper.classes()).toContain(expectedClass);
        expect(wrapper.text()).toBe('Label');
    });
});
