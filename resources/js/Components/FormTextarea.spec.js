import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import FormTextarea from './FormTextarea.vue';

describe('FormTextarea.vue', () => {
    it('emits updated values for v-model', async () => {
        const wrapper = mount(FormTextarea, {
            props: { modelValue: 'Initial description' },
        });

        await wrapper.get('textarea').setValue('Updated description');

        expect(wrapper.emitted('update:modelValue').at(-1)).toEqual(['Updated description']);
    });

    it('associates its label with the textarea', () => {
        const wrapper = mount(FormTextarea, {
            props: { id: 'task-description', label: 'Description' },
        });

        expect(wrapper.get('label').attributes('for')).toBe('task-description');
        expect(wrapper.get('textarea').attributes('id')).toBe('task-description');
    });

    it('displays an accessible error', () => {
        const wrapper = mount(FormTextarea, {
            props: { id: 'task-description', error: 'Description is too long.' },
        });

        const textarea = wrapper.get('textarea');
        const error = wrapper.get('[role="alert"]');

        expect(error.text()).toBe('Description is too long.');
        expect(textarea.attributes('aria-invalid')).toBe('true');
        expect(textarea.attributes('aria-describedby')).toBe(error.attributes('id'));
    });

    it('disables the textarea', () => {
        const wrapper = mount(FormTextarea, {
            props: { disabled: true },
        });

        expect(wrapper.get('textarea').attributes('disabled')).toBeDefined();
    });

    it('uses four rows by default and forwards native attributes', () => {
        const wrapper = mount(FormTextarea, {
            attrs: { name: 'description', placeholder: 'Add details' },
        });

        expect(wrapper.get('textarea').attributes()).toMatchObject({
            rows: '4',
            name: 'description',
            placeholder: 'Add details',
        });
    });

    describe('hints', () => {
        it('describes the textarea with the hint text', () => {
            const wrapper = mount(FormTextarea, {
                props: { id: 'task-description', label: 'Label', hint: 'Some guidance.' },
            });

            const hint = wrapper.get('[data-test="hint"]');

            expect(hint.text()).toBe('Some guidance.');
            expect(wrapper.get('textarea').attributes('aria-describedby')).toBe(hint.attributes('id'));
        });

        it('describes the textarea with both the hint and the error', () => {
            const wrapper = mount(FormTextarea, {
                props: { id: 'task-description', hint: 'Some guidance.', error: 'Required.' },
                
            });

            const describedby = wrapper.get('textarea').attributes('aria-describedby').split(' ');

            expect(describedby).toEqual([
                wrapper.get('[data-test="hint"]').attributes('id'),
                wrapper.get('[role="alert"]').attributes('id'),
            ]);
        });

        it('omits the description when there is no hint and no error', () => {
            const wrapper = mount(FormTextarea, { props: { label: 'Label' },  });

            expect(wrapper.get('textarea').attributes('aria-describedby')).toBeUndefined();
            expect(wrapper.find('[data-test="hint"]').exists()).toBe(false);
        });
    });
});

