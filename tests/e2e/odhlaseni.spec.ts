import { expect, test } from '@playwright/test';
import { emailSettingsLink, offerLink } from './support/wp';

test('Nastavení e‑mailů: zvlášť Připomínka přezutí a Akce, nebo Neposílat nic', async ({ page }) => {
	const email = `e2e-nastaveni-${Date.now()}@example.test`;
	await page.goto(emailSettingsLink(email));

	const reminder = page.getByLabel('Připomínku přezutí před každou sezónou');
	const promotions = page.getByLabel('Naše akce');
	await expect(page.getByRole('heading', { level: 1 })).toHaveText('E‑maily od nás');
	await expect(page.getByRole('main')).toContainText(email);
	await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content', /noindex/);
	await expect(reminder).toBeChecked();
	await expect(promotions).toBeChecked();

	await reminder.uncheck();
	await page.getByRole('button', { name: 'Uložit' }).click();

	await expect(page.getByRole('status')).toHaveText('Uloženo.');
	await expect(reminder).not.toBeChecked();
	await expect(promotions).toBeChecked();

	await reminder.check();
	await page.getByRole('button', { name: 'Uložit' }).click();

	await expect(reminder).toBeChecked();

	await page.getByRole('button', { name: 'Neposílat nic' }).click();

	await expect(page.getByRole('status')).toHaveText('Hotovo, nebudeme vám posílat nic.');
	await expect(reminder).not.toBeChecked();
	await expect(promotions).not.toBeChecked();
});

test('Odkaz z potvrzení Rezervace zadané Provozovatelem zapne jen zaškrtnuté Nabídky a připomínky', async ({ page }) => {
	const email = `e2e-ano-${Date.now()}@example.test`;
	await page.goto(offerLink(email));

	await expect(page.getByRole('heading', { level: 1 })).toHaveText('E‑maily od nás');
	await expect(page.getByRole('main')).toContainText(email);
	await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content', /noindex/);
	await expect(page.getByLabel('Připomínku přezutí před každou sezónou')).not.toBeChecked();
	await expect(page.getByLabel('Naše akce')).not.toBeChecked();

	await page.getByLabel('Připomínku přezutí před každou sezónou').check();
	await page.getByRole('button', { name: 'Uložit' }).click();

	await expect(page).toHaveURL(/\/odhlaseni\/\?k=.+&ulozeno=1$/);
	await expect(page.getByRole('status')).toHaveText('Uloženo.');
	await expect(page.getByLabel('Připomínku přezutí před každou sezónou')).toBeChecked();
	await expect(page.getByLabel('Naše akce')).not.toBeChecked();
});

test('Starý odkaz z e‑mailů starého webu přesměruje na odhlášení a neplatný odkaz to řekne', async ({ page }) => {
	await page.goto('/cancel-subscription?email=e2e-stary-odber%40example.test');

	await expect(page).toHaveURL(/\/odhlaseni\/\?email=e2e-stary-odber%40example\.test$/);
	await expect(page.getByRole('heading', { level: 1 })).toHaveText('Odhlášeno');
	await expect(page.getByRole('status')).toHaveText('Hotovo, informace o slevách vám už posílat nebudeme.');
	await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content', /noindex/);

	for (const link of ['/odhlaseni/?t=1.' + '0'.repeat(64), '/odhlaseni/?k=1.' + '0'.repeat(64), '/odhlaseni/?s=1.' + '0'.repeat(64)]) {
		await page.goto(link);

		await expect(page.getByRole('heading', { level: 1 })).toHaveText('Odkaz nefunguje');
		await expect(page.getByRole('alert')).toHaveText('Odkaz na nastavení e‑mailů je neplatný nebo už vypršel.');
		await expect(page.getByRole('main').locator('a[href^="tel:"]')).toBeVisible();
		await expect(page.getByRole('main').getByRole('link', { name: 'Zpět na úvod' })).toBeVisible();
	}
});
