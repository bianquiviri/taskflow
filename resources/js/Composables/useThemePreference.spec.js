import { beforeEach, describe, expect, it, vi } from 'vitest';
import { persistTheme } from './useThemePreference';

const { patch } = vi.hoisted(() => ({ patch: vi.fn() }));

vi.mock('@inertiajs/vue3', () => ({
    router: { patch },
}));

describe('useThemePreference.js', () => {
    beforeEach(() => {
        patch.mockClear();
        document.documentElement.classList.remove('dark');
    });

    it('applies the theme before it travels to the account', () => {
        expect(persistTheme('dark')).toBe('dark');
        expect(document.documentElement.classList.contains('dark')).toBe(true);
        expect(patch).toHaveBeenCalledWith(
            '/theme',
            { theme: 'dark' },
            { preserveScroll: true, preserveState: true },
        );
    });

    it('normalises an unsupported theme instead of persisting it', () => {
        expect(persistTheme('neon')).toBe('light');
        expect(patch).toHaveBeenCalledWith(
            '/theme',
            { theme: 'light' },
            { preserveScroll: true, preserveState: true },
        );
        expect(document.documentElement.classList.contains('dark')).toBe(false);
    });
});
