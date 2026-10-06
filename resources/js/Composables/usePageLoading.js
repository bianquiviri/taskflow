import { onMounted, onUnmounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';

/**
 * Tracks the visits worth painting a skeleton for.
 *
 * Inertia swaps the page component once the response lands, so the only moment
 * a skeleton is honest is a full page navigation: a mutation answers on the
 * current page through the progress bar, and a partial reload keeps the page
 * mounted, where hiding data that is already on screen would only flicker.
 *
 * @returns {{ loading: import('vue').Ref<boolean> }}
 */
export function usePageLoading() {
    const loading = ref(false);
    let stopStart = null;
    let stopFinish = null;

    onMounted(() => {
        stopStart = router.on('start', (visit) => {
            if (visit?.method === 'get' && !(visit.only?.length > 0)) {
                loading.value = true;
            }
        });

        stopFinish = router.on('finish', () => {
            loading.value = false;
        });
    });

    onUnmounted(() => {
        stopStart?.();
        stopFinish?.();
    });

    return { loading };
}