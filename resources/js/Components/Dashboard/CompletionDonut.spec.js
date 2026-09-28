import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import CompletionDonut from './CompletionDonut.vue';

const track = '[data-test="donut-track"]';
const progress = '[data-test="donut-progress"]';

describe('Dashboard/CompletionDonut.vue', () => {
    it('renders the completed share of the open work', () => {
        const wrapper = mount(CompletionDonut, { props: { done: 3, open: 5 } });

        expect(wrapper.get('[data-test="donut-percent"]').text()).toBe('38%');
        expect(wrapper.get(progress).attributes('stroke-dasharray')).toBe('113.10 301.59');
    });

    it('draws the whole ring when everything is done', () => {
        const wrapper = mount(CompletionDonut, { props: { done: 4, open: 0 } });

        expect(wrapper.get('[data-test="donut-percent"]').text()).toBe('100%');
        expect(wrapper.get(progress).attributes('stroke-dasharray')).toBe('301.59 301.59');
    });

    it('hides the coloured arc while nothing is done', () => {
        const wrapper = mount(CompletionDonut, { props: { done: 0, open: 6 } });

        expect(wrapper.get('[data-test="donut-percent"]').text()).toBe('0%');
        expect(wrapper.find(progress).exists()).toBe(false);
    });

    it('keeps the ring decorative and the numbers in the legend', () => {
        const wrapper = mount(CompletionDonut, { props: { done: 3, open: 5 } });

        expect(wrapper.get('svg').attributes('aria-hidden')).toBe('true');
        expect(wrapper.get('[data-legend="done"]').text()).toContain('3');
        expect(wrapper.get('[data-legend="open"]').text()).toContain('5');
    });

    it('shows an empty state for a user without tasks', () => {
        const wrapper = mount(CompletionDonut, { props: { done: 0, open: 0 } });

        expect(wrapper.get('[data-test="donut-empty"]').text()).toBe('No tasks yet');
        expect(wrapper.get(track).exists()).toBe(true);
        expect(wrapper.find(progress).exists()).toBe(false);
        expect(wrapper.find('[data-test="donut-percent"]').exists()).toBe(false);
    });
});
