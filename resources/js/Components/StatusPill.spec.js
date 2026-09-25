import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import StatusPill from './StatusPill.vue';

describe('StatusPill.vue', () => {
    it.each([
        ['todo', 'Todo', 'bg-gray-100'],
        ['in_progress', 'In progress', 'bg-sky-100'],
        ['in_review', 'In review', 'bg-amber-100'],
        ['done', 'Done', 'bg-green-100'],
        ['cancelled', 'Cancelled', 'bg-red-100'],
        ['low', 'Low', 'bg-gray-100'],
        ['medium', 'Medium', 'bg-indigo-100'],
        ['high', 'High', 'bg-amber-100'],
        ['urgent', 'Urgent', 'bg-red-100'],
    ])('maps %s to its label and tone', (value, label, toneClass) => {
        const wrapper = mount(StatusPill, {
            props: { value },
        });

        expect(wrapper.text()).toBe(label);
        expect(wrapper.classes()).toContain(toneClass);
    });

    it('normalizes the received string', () => {
        const wrapper = mount(StatusPill, {
            props: { value: ' IN_PROGRESS ' },
        });

        expect(wrapper.text()).toBe('In progress');
    });

    it('humanizes unknown values with a neutral tone', () => {
        const wrapper = mount(StatusPill, {
            props: { value: 'blocked' },
        });

        expect(wrapper.text()).toBe('Blocked');
        expect(wrapper.classes()).toContain('bg-gray-100');
    });
});
