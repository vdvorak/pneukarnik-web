import { expect, test } from '@playwright/test';

test('Úvod vykreslí vlastní šablona', async ({ page }) => {
	const response = await page.goto('/');

	expect(response?.status()).toBe(200);
	await expect(page.locator('html')).toHaveAttribute('lang', /^cs/);
	await expect(page.locator('link[rel="stylesheet"][href*="/wp-content/themes/pneukarnik/style.css"]')).toHaveCount(1);
	await expect(page.locator('main#obsah')).toBeAttached();
	await expect(page.getByRole('link', { name: 'Pneuservis Kárník' })).toBeVisible();
});
