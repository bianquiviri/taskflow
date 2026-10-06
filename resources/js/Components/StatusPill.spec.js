import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import StatusPill from './StatusPill.vue';

describe('StatusPill.vue', () => {
    it.each([
        ['todo', 'Todo', 'bg-sunken'],
        ['in_progress', 'In progress', 'bg-info-soft'],
        ['in_review', 'In review', 'bg-warning-soft'],
        ['done', 'Done', 'bg-success-soft'],
        ['cancelled', 'Cancelled', 'bg-danger-soft'],
        ['low', 'Low', 'bg-sunken'],
        ['medium', 'Medium', 'bg-brand-soft'],
        ['high', 'High', 'bg-warning-soft'],
        ['urgent', 'Urgent', 'bg-danger-soft'],
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
        expect(wrapper.classes()).toContain('bg-sunken');
    });
});
