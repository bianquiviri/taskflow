import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import PageSkeleton from './PageSkeleton.vue';
import Skeleton from './Skeleton.vue';

describe('PageSkeleton.vue', () => {
    it('announces the pending load without reading the blocks', () => {
        const wrapper = mount(PageSkeleton);

        expect(wrapper.attributes('role')).toBe('status');
        expect(wrapper.attributes('aria-busy')).toBe('true');
        expect(wrapper.text()).toContain('Loading');
        expect(wrapper.findAllComponents(Skeleton).every((block) => block.attributes('aria-hidden') === 'true'))
            .toBe(true);
    });

    it('paints three placeholder rows by default', () => {
        expect(mount(PageSkeleton).findAll('[data-test="page-skeleton-row"]')).toHaveLength(3);
    });

    it('takes the number of rows from the caller', () => {
        const wrapper = mount(PageSkeleton, { props: { rows: 1 } });

        expect(wrapper.findAll('[data-test="page-skeleton-row"]')).toHaveLength(1);
        expect(wrapper.findAllComponents(Skeleton).length).toBeGreaterThanOrEqual(2);
    });
});