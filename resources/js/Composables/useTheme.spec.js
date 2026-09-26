import { beforeEach, describe, expect, it } from 'vitest';
import { nextTick, ref } from 'vue';
import {
    DEFAULT_THEME,
    applyTheme,
    oppositeTheme,
    resolveTheme,
    useTheme,
} from './useTheme';

describe('useTheme', () => {
    beforeEach(() => {
        document.documentElement.classList.remove('dark');
    });

    it('resolves known themes and falls back to the default', () => {
        expect(resolveTheme('dark')).toBe('dark');
        expect(resolveTheme('light')).toBe('light');
        expect(resolveTheme('neon')).toBe(DEFAULT_THEME);
        expect(resolveTheme(undefined)).toBe(DEFAULT_THEME);
    });

    it('returns the opposite theme of a resolved value', () => {
        expect(oppositeTheme('light')).toBe('dark');
        expect(oppositeTheme('dark')).toBe('light');
        expect(oppositeTheme('neon')).toBe('dark');
    });

    it('toggles the dark class on the document element', () => {
        expect(applyTheme('dark')).toBe('dark');
        expect(document.documentElement.classList.contains('dark')).toBe(true);

        expect(applyTheme('light')).toBe('light');
        expect(document.documentElement.classList.contains('dark')).toBe(false);
    });

    it('keeps unrelated document classes untouched', () => {
        document.documentElement.classList.add('h-full');

        applyTheme('dark');
        applyTheme('light');

        expect(document.documentElement.classList.contains('h-full')).toBe(true);
    });

    it('exposes the resolved theme and the next one from a reactive source', () => {
        const source = ref('light');
        const { theme, isDark, toggle } = useTheme(() => source.value);

        expect(theme.value).toBe('light');
        expect(isDark.value).toBe(false);
        expect(toggle()).toBe('dark');
    });

    it('re-applies the document class when the source changes', async () => {
        const source = ref('dark');
        const { theme } = useTheme(() => source.value);

        expect(document.documentElement.classList.contains('dark')).toBe(true);

        source.value = 'light';
        await nextTick();

        expect(theme.value).toBe('light');
        expect(document.documentElement.classList.contains('dark')).toBe(false);
    });
});
