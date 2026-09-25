import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import Avatar from './Avatar.vue';

describe('Avatar.vue', () => {
    it('renders initials for a two-word name', () => {
        const wrapper = mount(Avatar, { props: { name: 'Jane Doe' } });

        expect(wrapper.text()).toBe('JD');
    });

    it('renders a single initial for a one-word name', () => {
        const wrapper = mount(Avatar, { props: { name: 'jose' } });

        expect(wrapper.text()).toBe('J');
    });

    it('renders the fallback user glyph when no name is given', () => {
        const wrapper = mount(Avatar, { props: { name: '' } });

        expect(wrapper.text()).toBe('');
        expect(wrapper.find('svg').exists()).toBe(true);
    });

    it('renders the image when a src is provided', () => {
        const wrapper = mount(Avatar, { props: { name: 'Jane Doe', src: '/avatars/jane.png' } });

        const img = wrapper.find('img');
        expect(img.exists()).toBe(true);
        expect(img.attributes('src')).toBe('/avatars/jane.png');
        expect(img.attributes('alt')).toBe('Jane Doe');
    });

    it('applies the size class for the sm size', () => {
        const wrapper = mount(Avatar, { props: { name: 'Jane Doe', size: 'sm' } });

        expect(wrapper.classes()).toContain('size-8');
    });

    it('applies a deterministic tone class based on the name', () => {
        const wrapper = mount(Avatar, { props: { name: 'Jane Doe' } });

        expect(wrapper.classes().some((tone) => tone.startsWith('bg-'))).toBe(true);
    });
});