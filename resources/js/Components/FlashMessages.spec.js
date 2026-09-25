import { describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import { mount } from '@vue/test-utils';
import FlashMessages from './FlashMessages.vue';

const global = {
    stubs: {
        Icon: { props: ['name'], template: '<span data-test="icon" />' },
    },
};

describe('FlashMessages.vue', () => {
    it('renders a success message', () => {
        const wrapper = mount(FlashMessages, {
            props: { messages: { success: 'Project created.' } },
            global,
        });

        expect(wrapper.text()).toContain('Project created.');
        expect(wrapper.text()).toContain('Success');
    });

    it('renders messages from an array value', () => {
        const wrapper = mount(FlashMessages, {
            props: { messages: { error: ['First error.', 'Second error.'] } },
            global,
        });

        expect(wrapper.text()).toContain('First error.');
        expect(wrapper.text()).toContain('Second error.');
    });

    it('renders several tones at once', () => {
        const wrapper = mount(FlashMessages, {
            props: {
                messages: { success: 'Done.', warning: 'Heads up.', info: 'FYI.' },
            },
            global,
        });

        expect(wrapper.text()).toContain('Done.');
        expect(wrapper.text()).toContain('Heads up.');
        expect(wrapper.text()).toContain('FYI.');
    });

    it('ignores unknown tone keys', () => {
        const wrapper = mount(FlashMessages, {
            props: { messages: { bogus: 'Nope.' } },
            global,
        });

        expect(wrapper.text()).not.toContain('Nope.');
    });

    it('does not render when there are no messages', () => {
        const wrapper = mount(FlashMessages, {
            props: { messages: {} },
            global,
        });

        expect(wrapper.find('[aria-live]').exists()).toBe(false);
    });

    it('skips null values for a known tone', () => {
        const wrapper = mount(FlashMessages, {
            props: { messages: { success: null, error: undefined } },
            global,
        });

        expect(wrapper.find('[aria-live]').exists()).toBe(false);
    });

    it('dismisses a message when its close button is clicked', async () => {
        const wrapper = mount(FlashMessages, {
            props: { messages: { success: 'Dismiss me.' } },
            global,
        });

        await wrapper.find('button').trigger('click');
        expect(wrapper.text()).not.toContain('Dismiss me.');
    });

    it('auto-dismisses messages after the timeout', async () => {
        vi.useFakeTimers();
        const wrapper = mount(FlashMessages, {
            props: { messages: { success: 'Temporary.' } },
            global,
        });

        expect(wrapper.text()).toContain('Temporary.');

        vi.advanceTimersByTime(5000);
        await nextTick();

        expect(wrapper.text()).not.toContain('Temporary.');
        vi.useRealTimers();
    });

    it('clears pending timers on unmount', () => {
        vi.useFakeTimers();
        const wrapper = mount(FlashMessages, {
            props: { messages: { success: 'Temporary.' } },
            global,
        });

        wrapper.unmount();
        const pending = vi.getTimerCount();

        expect(pending).toBe(0);
        vi.useRealTimers();
    });
});