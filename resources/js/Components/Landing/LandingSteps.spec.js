import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import LandingSteps from './LandingSteps.vue';

const mountSteps = () => mount(LandingSteps);

describe('LandingSteps.vue', () => {
    it('explains how to get started in three steps', () => {
        const wrapper = mountSteps();

        expect(wrapper.get('h2').text()).toBe('How it works');
        expect(wrapper.findAll('li h3').map((node) => node.text())).toEqual([
            'Create a project',
            'Add and assign tasks',
            'Track it to done',
        ]);
    });

    it('numbers the steps in order', () => {
        const wrapper = mountSteps();

        expect(wrapper.findAll('li span').map((node) => node.text()))
            .toEqual(['1', '2', '3']);
    });

    it('keeps the steps in a single ordered list', () => {
        const wrapper = mountSteps();

        expect(wrapper.findAll('ol > li')).toHaveLength(3);
    });
});
