import { test, expect } from '@playwright/test';

/**
 * Guest-only: the CI web server runs on a fresh in-memory SQLite database
 * without migrations, so the landing page must render without one. What happens
 * after the visitor submits the sign-in form is covered by the Pest feature
 * tests (see tests/Feature/WelcomeTest.php).
 */
test.describe('TaskFlow landing page', () => {
    test('explains what the product is to a visitor', async ({ page }) => {
        await page.goto('/');

        await expect(page).toHaveTitle(/task management/i);
        await expect(page.getByRole('heading', { level: 1, name: /plan the work/i })).toBeVisible();
        await expect(page.getByRole('heading', { name: 'What is TaskFlow?' })).toBeVisible();
        await expect(page.getByRole('heading', { name: 'How it works' })).toBeVisible();
    });

    test('offers registration and an inline sign-in form', async ({ page }) => {
        await page.goto('/');

        await expect(page.getByRole('link', { name: 'Create a free account' })).toBeVisible();

        const signIn = page.locator('#signin');

        await expect(signIn.getByLabel('Email', { exact: true })).toBeVisible();
        await expect(signIn.getByLabel('Password', { exact: true })).toBeVisible();
        await expect(signIn.getByRole('button', { name: 'Sign in' })).toBeEnabled();
        await expect(signIn.getByRole('link', { name: 'Create an account' })).toBeVisible();
        await expect(page.getByRole('contentinfo').getByRole('link', { name: 'Create an account' })).toBeVisible();
    });

    test('jumps the visitor to the sign-in form from the navigation', async ({ page }) => {
        await page.goto('/');

        await page.getByRole('navigation', { name: 'Primary' }).getByRole('link', { name: 'Sign in' }).click();

        await expect(page).toHaveURL(/#signin$/);
        await expect(page.locator('#signin').getByRole('heading', { name: 'Sign in to TaskFlow' }))
            .toBeVisible();
    });

    test('credits the author and links the project license', async ({ page }) => {
        await page.goto('/');

        const footer = page.getByRole('contentinfo');

        await expect(footer.getByRole('link', { name: 'Jose Antonio Bianco' }))
            .toHaveAttribute('href', 'https://github.com/bianquiviri');
        await expect(footer.getByRole('link', { name: 'CC BY-NC-ND 4.0' }))
            .toHaveAttribute('href', 'https://creativecommons.org/licenses/by-nc-nd/4.0/');
    });
});
