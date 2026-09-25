import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import FormSelect from './FormSelect.vue';

const slots = {
    default: `
        <option value="low">Low</option>
        <option value="medium">Medium</option>
        <option value="high">High</option>
    `,
};

describe('FormSelect.vue', () => {
    it('renders the current value and emits v-model updates', async () => {
        const wrapper = mount(FormSelect, {
            props: { modelValue: 'medium' },
            slots,
        });

        const select = wrapper.get('select');
        expect(select.element.value).toBe('medium');

        await select.setValue('high');

        expect(wrapper.emitted('update:modelValue').at(-1)).toEqual(['high']);
    });

    it('associates its label with the select', () => {
        const wrapper = mount(FormSelect, {
            props: { id: 'task-priority', label: 'Priority' },
            slots,
        });

        expect(wrapper.get('label').attributes('for')).toBe('task-priority');
        expect(wrapper.get('select').attributes('id')).toBe('task-priority');
    });

    it('displays an accessible error', () => {
        const wrapper = mount(FormSelect, {
            props: { id: 'task-priority', error: 'Choose a priority.' },
            slots,
        });

        const select = wrapper.get('select');
        const error = wrapper.get('[role="alert"]');

        expect(error.text()).toBe('Choose a priority.');
        expect(select.attributes('aria-invalid')).toBe('true');
        expect(select.attributes('aria-describedby')).toBe(error.attributes('id'));
    });

    it('disables the select', () => {
        const wrapper = mount(FormSelect, {
            props: { disabled: true },
            slots,
        });

        expect(wrapper.get('select').attributes('disabled')).toBeDefined();
    });

    it('forwards native select attributes', () => {
        const wrapper = mount(FormSelect, {
            attrs: { name: 'priority', required: true },
            slots,
        });

        expect(wrapper.get('select').attributes()).toMatchObject({
            name: 'priority',
            required: '',
        });
    });
});
