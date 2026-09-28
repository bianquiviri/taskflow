import { test, expect } from '@playwright/test';

/**
 * Guest journeys only: the CI web server runs on a fresh in-memory SQLite
 * database without migrations, so anything that writes (registration, login,
 * verification) is covered by the Pest feature tests instead.
 */
test.describe('authentication pages', () => {
    test('renders the sign-in form', async ({ page }) => {
        await page.goto('/login');

        await expect(page).toHaveTitle(/Sign in/);
        await expect(page.getByRole('heading', { name: 'Welcome back' })).toBeVisible();
        await expect(page.getByLabel('Email', { exact: true })).toBeVisible();
        await expect(page.getByLabel('Password', { exact: true })).toBeVisible();
        await expect(page.getByRole('button', { name: 'Sign in' })).toBeEnabled();
        await expect(page.getByRole('link', { name: 'Forgot password?' })).toBeVisible();
        await expect(page.getByRole('link', { name: 'Create an account' })).toBeVisible();
    });

    test('reveals the password on demand', async ({ page }) => {
        await page.goto('/login');

        const password = page.getByLabel('Password', { exact: true });

        await expect(password).toHaveAttribute('type', 'password');

        await page.getByRole('button', { name: 'Show password' }).click();

        await expect(password).toHaveAttribute('type', 'text');
    });

    test('renders the registration form with every field', async ({ page }) => {
        await page.goto('/register');

        await expect(page).toHaveTitle(/Create account/);
        await expect(page.getByLabel('Full name')).toBeVisible();
        await expect(page.getByLabel('Email address')).toBeVisible();
        await expect(page.getByLabel('Password', { exact: true })).toBeVisible();
        await expect(page.getByLabel('Confirm password')).toBeVisible();
        await expect(page.getByRole('button', { name: 'Create account' })).toBeEnabled();
    });

    test('holds the sign-in request back while the required fields are empty', async ({ page }) => {
        await page.goto('/login');

        await page.getByRole('button', { name: 'Sign in' }).click();

        await expect(page).toHaveURL(/\/login$/);
        await expect(page.getByLabel('Email', { exact: true })).toHaveJSProperty('validity.valueMissing', true);
    });

    test('renders the password reset forms', async ({ page }) => {
        await page.goto('/forgot-password');

        await expect(page).toHaveTitle(/Forgot password/);
        await expect(page.getByRole('heading', { name: 'Reset your password' })).toBeVisible();
        await expect(page.getByRole('button', { name: 'Email password reset link' })).toBeEnabled();
        await expect(page.getByRole('link', { name: 'Return to sign in' })).toBeVisible();

        await page.goto('/reset-password/e2e-token?email=ada%40example.com');

        await expect(page).toHaveTitle(/Reset password/);
        await expect(page.getByText('ada@example.com', { exact: true })).toBeVisible();
        await expect(page.getByLabel('New password', { exact: true })).toBeVisible();
        await expect(page.getByLabel('Confirm new password', { exact: true })).toBeVisible();
    });
});
