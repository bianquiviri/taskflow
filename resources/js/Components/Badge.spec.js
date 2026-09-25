import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import Badge from './Badge.vue';

describe('Badge.vue', () => {
    it.each([
        ['indigo', 'bg-indigo-100'],
        ['sky', 'bg-sky-100'],
        ['gray', 'bg-gray-100'],
        ['green', 'bg-green-100'],
        ['amber', 'bg-amber-100'],
        ['red', 'bg-red-100'],
    ])('renders the %s tone', (tone, expectedClass) => {
        const wrapper = mount(Badge, {
            props: { tone },
            slots: { default: 'Label' },
        });

        expect(wrapper.classes()).toContain(expectedClass);
        expect(wrapper.text()).toBe('Label');
    });
});
