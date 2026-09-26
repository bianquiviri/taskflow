import { computed, watchEffect } from 'vue';

export const DARK_CLASS = 'dark';
export const DEFAULT_THEME = 'light';
export const DARK_THEME = 'dark';

const THEMES = [DEFAULT_THEME, DARK_THEME];

export function resolveTheme(theme) {
    return THEMES.includes(theme) ? theme : DEFAULT_THEME;
}

export function oppositeTheme(theme) {
    return resolveTheme(theme) === DARK_THEME ? DEFAULT_THEME : DARK_THEME;
}

export function applyTheme(theme) {
    const resolved = resolveTheme(theme);

    document.documentElement.classList.toggle(DARK_CLASS, resolved === DARK_THEME);

    return resolved;
}

/**
 * Keeps the `dark` class on <html> in sync with a reactive theme source.
 *
 * @param {() => string} source
 */
export function useTheme(source) {
    const theme = computed(() => resolveTheme(source()));

    watchEffect(() => applyTheme(theme.value));

    return {
        theme,
        isDark: computed(() => theme.value === DARK_THEME),
        toggle: () => oppositeTheme(theme.value),
    };
}
