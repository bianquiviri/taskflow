import { describe, expect, it, vi } from 'vitest';
import { defineComponent, nextTick } from 'vue';
import { mount } from '@vue/test-utils';

const { events, router, stop } = vi.hoisted(() => {
    const events = {};
    const stop = vi.fn();

    return {
        events,
        stop,
        router: {
            on: vi.fn((name, handler) => {
                events[name] = handler;

                return stop;
            }),
        },
    };
});

vi.mock('@inertiajs/vue3', () => ({ router }));

import { usePageLoading } from './usePageLoading';

const Harness = defineComponent({
    setup() {
        const { loading } = usePageLoading();

        return { loading };
    },
    template: '<p data-test="loading">{{ loading }}</p>',
});

const visit = (overrides = {}) => ({ method: 'get', only: [], url: '/projects', ...overrides });

const mountHarness = () => mount(Harness);

describe('usePageLoading', () => {
    it('starts idle', () => {
        expect(mountHarness().get('[data-test="loading"]').text()).toBe('false');
    });

    it('flips while a full page visit is on its way', async () => {
        const wrapper = mountHarness();

        events.start(visit());
        await nextTick();

        expect(wrapper.get('[data-test="loading"]').text()).toBe('true');

        events.finish(visit());
        await nextTick();

        expect(wrapper.get('[data-test="loading"]').text()).toBe('false');
    });

    it('stays idle for mutations answering through the current page', async () => {
        const wrapper = mountHarness();

        events.start(visit({ method: 'patch' }));
        events.start(visit({ method: 'post' }));
        await nextTick();

        expect(wrapper.get('[data-test="loading"]').text()).toBe('false');
    });

    it('stays idle for partial reloads keeping the page mounted', async () => {
        const wrapper = mountHarness();

        events.start(visit({ only: ['projects'] }));
        await nextTick();

        expect(wrapper.get('[data-test="loading"]').text()).toBe('false');
    });

    it('tolerates a visit reported without a payload', async () => {
        const wrapper = mountHarness();

        events.start(undefined);
        await nextTick();

        expect(wrapper.get('[data-test="loading"]').text()).toBe('false');
    });

    it('leaves the router again when the page is unmounted', () => {
        const wrapper = mountHarness();

        expect(router.on).toHaveBeenCalledWith('start', expect.any(Function));
        expect(router.on).toHaveBeenCalledWith('finish', expect.any(Function));

        const [stopStart, stopFinish] = router.on.mock.results.map((result) => result.value);
        wrapper.unmount();

        expect(stopStart).toHaveBeenCalled();
        expect(stopFinish).toHaveBeenCalled();
        expect(stop).toHaveBeenCalledTimes(2);
    });
});