import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import LandingFeatures from './LandingFeatures.vue';

const mountFeatures = (props = {}) => mount(LandingFeatures, {
    props: { appName: 'TaskFlow', ...props },
});

describe('LandingFeatures.vue', () => {
    it('answers what the product is', () => {
        const wrapper = mountFeatures();

        expect(wrapper.get('h2').text()).toBe('What is TaskFlow?');
    });

    it('lists the six capabilities of the product', () => {
        const wrapper = mountFeatures();

        const titles = wrapper.findAll('li h3').map((node) => node.text());

        expect(titles).toEqual([
            'Projects',
            'Tasks & boards',
            'Teams & invitations',
            'Comments & mentions',
            'Activity trail',
            'Dashboard & metrics',
        ]);
    });

    it('describes every capability', () => {
        const wrapper = mountFeatures();

        const descriptions = wrapper.findAll('li p').map((node) => node.text());

        expect(descriptions).toHaveLength(6);
        expect(descriptions.every((text) => text.length > 20)).toBe(true);
    });

    it('marks the feature list as a list', () => {
        const wrapper = mountFeatures();

        expect(wrapper.get('ul').findAll('li')).toHaveLength(6);
    });
});
