import { computed, toValue } from 'vue';

/**
 * Whether the task filters of a listing hold anything worth clearing.
 *
 * Every empty listing has to answer the same question — "is the user looking at
 * nothing, or at everything through a filter?" — to word its empty state and
 * decide whether a call to action belongs there, so the reading of the filter
 * set lives here once.
 *
 * @param {import('vue').Ref<Record<string, unknown>|null>|Record<string, unknown>|null} filters
 * @returns {import('vue').ComputedRef<boolean>}
 */
export function useActiveFilters(filters) {
    return computed(() => Object.values(toValue(filters) ?? {})
        .some((value) => value !== null && value !== undefined && value !== ''));
}