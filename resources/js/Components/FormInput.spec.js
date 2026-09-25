import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import FormInput from './FormInput.vue';

describe('FormInput.vue', () => {
    it('emits updated values for v-model', async () => {
        const wrapper = mount(FormInput, {
            props: { modelValue: 'Initial title' },
        });

        await wrapper.get('input').setValue('Updated title');

        expect(wrapper.emitted('update:modelValue').at(-1)).toEqual(['Updated title']);
    });

    it('associates its label with the input', () => {
        const wrapper = mount(FormInput, {
            props: { id: 'task-title', label: 'Task title' },
        });

        const input = wrapper.get('input');
        const label = wrapper.get('label');

        expect(input.attributes('id')).toBe('task-title');
        expect(label.attributes('for')).toBe('task-title');
    });

    it('displays an accessible error', () => {
        const wrapper = mount(FormInput, {
            props: { id: 'task-title', error: 'A title is required.' },
        });

        const input = wrapper.get('input');
        const error = wrapper.get('[role="alert"]');

        expect(error.text()).toBe('A title is required.');
        expect(input.attributes('aria-invalid')).toBe('true');
        expect(input.attributes('aria-describedby')).toBe(error.attributes('id'));
    });

    it('disables the input', () => {
        const wrapper = mount(FormInput, {
            props: { disabled: true },
        });

        expect(wrapper.get('input').attributes('disabled')).toBeDefined();
    });

    it('forwards native input attributes', () => {
        const wrapper = mount(FormInput, {
            props: { type: 'email' },
            attrs: { name: 'email', placeholder: 'name@example.com', autocomplete: 'email' },
        });

        expect(wrapper.get('input').attributes()).toMatchObject({
            type: 'email',
            name: 'email',
            placeholder: 'name@example.com',
            autocomplete: 'email',
        });
    });
});
