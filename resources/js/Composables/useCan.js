import { computed, unref } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Reduces a permission input to the names that are granted.
 *
 * Accepts a list of names, a map of `name => allowed` flags — as a page sends
 * them for a single entity — or nothing at all.
 *
 * @param {string[]|Record<string, boolean>|import('vue').Ref<string[]|Record<string, boolean>>|null|undefined} permissions
 * @returns {string[]}
 */
export function grant(permissions) {
    const value = unref(permissions);

    if (Array.isArray(value)) {
        return value.filter((permission) => typeof permission === 'string' && permission !== '');
    }

    if (value && typeof value === 'object') {
        return Object.entries(value)
            .filter(([, allowed]) => Boolean(allowed))
            .map(([permission]) => permission);
    }

    return [];
}

/**
 * Guards the UI with the `can` prop shared by the server, merged with the
 * permissions a page resolved for its own entities.
 *
 * @param {string[]|Record<string, boolean>|import('vue').Ref<string[]|Record<string, boolean>>} [local]
 */
export function useCan(local) {
    const page = usePage();

    const permissions = computed(() => [
        ...new Set([...grant(page.props.can), ...grant(local)]),
    ]);

    return {
        permissions,
        can: (permission) => permissions.value.includes(permission),
        canAny: (list) => grant(list).some((permission) => permissions.value.includes(permission)),
        canAll: (list) => grant(list).every((permission) => permissions.value.includes(permission)),
    };
}
