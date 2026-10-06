import { expect, test } from '@playwright/test';

test('Starý odkaz z e‑mailů starého webu přesměruje na odhlášení a neplatný odkaz to řekne', async ({ page }) => {
	await page.goto('/cancel-subscription?email=e2e-stary-odber%40example.test');

	await expect(page).toHaveURL(/\/odhlaseni\/\?email=e2e-stary-odber%40example\.test$/);
	await expect(page.getByRole('heading', { level: 1 })).toHaveText('Odhlášeno');
	await expect(page.getByRole('status')).toHaveText('Hotovo, informace o slevách vám už posílat nebudeme.');
	await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content', /noindex/);

	await page.goto('/odhlaseni/?t=1.' + '0'.repeat(64));

	await expect(page.getByRole('heading', { level: 1 })).toHaveText('Odhlášení se nepovedlo');
	await expect(page.getByRole('alert')).toHaveText('Odkaz pro odhlášení je neplatný nebo už vypršel.');
	await expect(page.getByRole('main').locator('a[href^="tel:"]')).toBeVisible();
	await expect(page.getByRole('main').getByRole('link', { name: 'Zpět na úvod' })).toBeVisible();
});
