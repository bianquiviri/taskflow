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

    it('renders an empty live region when there are no messages', () => {
        const wrapper = mount(FlashMessages, {
            props: { messages: {} },
            global,
        });

        expect(wrapper.get('[data-test="toast-region"]').exists()).toBe(true);
        expect(wrapper.find('[role="status"]').exists()).toBe(false);
        expect(wrapper.find('[role="alert"]').exists()).toBe(false);
    });

    it('skips null values for a known tone', () => {
        const wrapper = mount(FlashMessages, {
            props: { messages: { success: null, error: undefined } },
            global,
        });

        expect(wrapper.find('[role="status"]').exists()).toBe(false);
        expect(wrapper.find('[role="alert"]').exists()).toBe(false);
    });

    describe('announcements', () => {
        it('keeps the same region node when a message arrives', async () => {
            const wrapper = mount(FlashMessages, {
                props: { messages: {} },
                global,
                attachTo: document.body,
            });

            const region = wrapper.get('[data-test="toast-region"]').element;

            await wrapper.setProps({ messages: { success: 'Arrived.' } });

            expect(wrapper.get('[data-test="toast-region"]').element).toBe(region);
            expect(region.textContent).toContain('Arrived.');

            wrapper.unmount();
            document.body.innerHTML = '';
        });

        it('announces errors assertively and everything else politely', () => {
            const wrapper = mount(FlashMessages, {
                props: { messages: { error: 'Nope.', success: 'Done.', info: 'FYI.' } },
                global,
            });

            const alerts = wrapper.findAll('[role="alert"]');
            const statuses = wrapper.findAll('[role="status"]');

            expect(alerts.map((alert) => alert.text())).toEqual([expect.stringContaining('Nope.')]);
            expect(statuses).toHaveLength(2);
        });

        it('does not nest the messages in a second live region', () => {
            const wrapper = mount(FlashMessages, {
                props: { messages: { success: 'Done.' } },
                global,
            });

            expect(wrapper.find('[aria-live]').exists()).toBe(false);
        });

        it('keeps the dismiss button reachable and named', () => {
            const wrapper = mount(FlashMessages, {
                props: { messages: { error: 'Nope.' } },
                global,
            });

            expect(wrapper.get('button').attributes('aria-label')).toBe('Dismiss Error message');
        });
    });

    describe('dismissal timers', () => {
        it('holds the message while it is being pointed at', async () => {
            vi.useFakeTimers();
            const wrapper = mount(FlashMessages, {
                props: { messages: { success: 'Hover me.' } },
                global,
            });

            await wrapper.get('[data-test="toast-region"]').trigger('mouseenter');
            vi.advanceTimersByTime(20000);
            await nextTick();

            expect(wrapper.text()).toContain('Hover me.');

            await wrapper.get('[data-test="toast-region"]').trigger('mouseleave');
            vi.advanceTimersByTime(5000);
            await nextTick();

            expect(wrapper.text()).not.toContain('Hover me.');
            vi.useRealTimers();
        });

        it('holds the message while it holds the keyboard focus', async () => {
            vi.useFakeTimers();
            const wrapper = mount(FlashMessages, {
                props: { messages: { success: 'Focus me.' } },
                global,
                attachTo: document.body,
            });

            await wrapper.get('button').trigger('focusin');
            vi.advanceTimersByTime(20000);
            await nextTick();

            expect(wrapper.text()).toContain('Focus me.');

            await wrapper.get('[data-test="toast-region"]').trigger('focusout', {
                relatedTarget: document.body,
            });
            vi.advanceTimersByTime(5000);
            await nextTick();

            expect(wrapper.text()).not.toContain('Focus me.');

            wrapper.unmount();
            document.body.innerHTML = '';
            vi.useRealTimers();
        });

        it('keeps holding the message while focus moves inside the region', async () => {
            vi.useFakeTimers();
            const wrapper = mount(FlashMessages, {
                props: { messages: { success: 'One.', warning: 'Two.' } },
                global,
                attachTo: document.body,
            });

            await wrapper.get('button').trigger('focusin');
            await wrapper.get('[data-test="toast-region"]').trigger('focusout', {
                relatedTarget: wrapper.get('button').element,
            });
            vi.advanceTimersByTime(20000);
            await nextTick();

            expect(wrapper.text()).toContain('One.');

            wrapper.unmount();
            document.body.innerHTML = '';
            vi.useRealTimers();
        });
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