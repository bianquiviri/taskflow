import { router } from '@inertiajs/vue3';
import { applyTheme } from './useTheme';

/**
 * Applies a theme right away and persists it on the account through the shared
 * `PATCH /theme` endpoint, so every control that changes the theme behaves the
 * same way.
 *
 * @param {string} theme
 */
export function persistTheme(theme) {
    const resolved = applyTheme(theme);

    router.patch('/theme', { theme: resolved }, { preserveScroll: true, preserveState: true });

    return resolved;
}
