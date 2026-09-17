import { test, expect } from '@playwright/test';

test.describe('TaskFlow home page', () => {
    test('loads the welcome page', async ({ page }) => {
        await page.goto('/');
        await expect(page).toHaveTitle(/TaskFlow/);
        await expect(page.getByRole('heading', { name: 'TaskFlow' })).toBeVisible();
    });
});