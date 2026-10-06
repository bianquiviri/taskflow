import { test, expect } from '@playwright/test';

/**
 * Keyboard, landmark and labelling checks that need no database: the CI web
 * server runs on a fresh in-memory SQLite database, so only guest pages are
 * reachable. Nothing here submits a form.
 */
test.describe('accessibility', () => {
    test('exposes a main region and a single first-level heading', async ({ page }) => {
        for (const path of ['/', '/login', '/register']) {
            await page.goto(path);

            await expect(page.getByRole('main')).toBeVisible();
            await expect(page.getByRole('heading', { level: 1 })).toHaveCount(1);
        }
    });

    test('reaches the sign-in fields and the remember checkbox with the keyboard alone', async ({ page }) => {
        await page.goto('/login');

        const email = page.getByLabel('Email', { exact: true });
        const password = page.getByLabel('Password', { exact: true });

        await email.focus();
        await expect(email).toBeFocused();

        await page.keyboard.press('Tab');
        await expect(password).toBeFocused();

        await page.keyboard.press('Tab');
        await expect(page.getByRole('button', { name: 'Show password' })).toBeFocused();

        await page.keyboard.press('Tab');
        const remember = page.getByRole('checkbox');
        await expect(remember).toBeFocused();

        await page.keyboard.press('Space');
        await expect(remember).toBeChecked();
    });

    test('reveals the password from the keyboard', async ({ page }) => {
        await page.goto('/login');

        const password = page.getByLabel('Password', { exact: true });
        const reveal = page.getByRole('button', { name: 'Show password' });

        await expect(password).toHaveAttribute('type', 'password');

        await reveal.focus();
        await page.keyboard.press('Enter');

        await expect(password).toHaveAttribute('type', 'text');
        await expect(page.getByRole('button', { name: 'Hide password' })).toBeFocused();
    });

    test('describes the password field with its rule', async ({ page }) => {
        await page.goto('/register');

        const password = page.getByLabel('Password', { exact: true });
        const describedBy = await password.getAttribute('aria-describedby');

        expect(describedBy).toBeTruthy();

        const description = await page.locator(`#${describedBy.split(' ').join(', #')}`).textContent();

        expect(description).toContain('characters');
    });

    test('hides the decorative iconography from assistive technology', async ({ page }) => {
        await page.goto('/login');

        const unlabelled = await page.locator('svg:not([aria-hidden="true"])').count();

        expect(unlabelled).toBe(0);
    });
});
