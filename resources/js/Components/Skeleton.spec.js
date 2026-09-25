import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import Skeleton from './Skeleton.vue';

describe('Skeleton.vue', () => {
    it('renders a decorative shimmer block', () => {
        const wrapper = mount(Skeleton);

        expect(wrapper.attributes('aria-hidden')).toBe('true');
        expect(wrapper.classes()).toEqual(expect.arrayContaining([
            'animate-pulse',
            'bg-gray-200',
        ]));
    });

    it('forwards utility classes for its dimensions and shape', () => {
        const wrapper = mount(Skeleton, {
            attrs: { class: 'h-8 w-1/3 rounded-full' },
        });

        expect(wrapper.classes()).toEqual(expect.arrayContaining([
            'h-8',
            'w-1/3',
            'rounded-full',
        ]));
    });
});
