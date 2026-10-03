import { expect, test } from '@playwright/test';
import { E2E_PREFIX, login, publishService, saveBookingSettings, uniqueTitle, upcomingWeekday } from './support/admin';

const serviceTitle = uniqueTitle('Přezutí kalendář');
// Daleko za online horizontem, aby se nepletl s testy veřejné rezervace.
const day = upcomingWeekday(150);
const customer = `${E2E_PREFIX}Telefonická ${Date.now()}`;

test.beforeAll(async ({ browser }) => {
	const page = await browser.newPage();
	await login(page);
	await saveBookingSettings(page);
	await publishService(page, {
		title: serviceTitle,
		category: 'Pneuservis',
		perex: 'Přezutí pro test kalendáře.',
		price: 600,
		duration: 60,
	});
	await page.close();
});

test('Provozovatel klikne do volného místa, zadá telefonickou objednávku, upraví ji a zruší', async ({ page }) => {
	await login(page);
	await page.goto(`/wp-admin/admin.php?page=pneukarnik-booking&view=day&date=${day}`);

	// Den 8–17 h: 9:00 je hodinu pod horním okrajem (1,2 px na minutu).
	const body = page.locator(`.pnk-cal__body[data-date="${day}"]`);
	await body.click({ position: { x: 40, y: 60 * 1.2 + 10 } });

	const dialog = page.getByRole('dialog');
	await expect(dialog.getByRole('heading', { name: 'Nová rezervace' })).toBeVisible();
	await expect(dialog.locator('input[name="date"]')).toHaveValue(day);
	await expect(dialog.locator('input[name="time"]')).toHaveValue('09:00');
	await dialog.getByLabel(serviceTitle).check();
	await expect(dialog.getByText('do 10:00 (60 min)')).toBeVisible();
	await dialog.getByLabel('Jméno *').fill(customer);
	await dialog.getByLabel('Telefon *').fill('603 123 456');
	await dialog.getByRole('button', { name: 'Zadat rezervaci' }).click();

	await expect(page.getByRole('status')).toHaveText('Rezervace zadána.');
	const booking = page.locator('.pnk-cal__booking', { hasText: customer });
	await expect(booking).toContainText('9:00–10:00');

	await booking.click();
	await expect(dialog.getByText('Provozovatel')).toBeVisible();
	await dialog.getByRole('button', { name: 'Upravit' }).click();
	await dialog.locator('input[name="time"]').fill('10:00');
	await dialog.getByRole('button', { name: 'Uložit změny' }).click();
	await expect(page.getByRole('status')).toHaveText('Rezervace upravena.');
	await expect(booking).toContainText('10:00–11:00');

	await booking.click();
	await dialog.getByRole('button', { name: 'Zrušit rezervaci' }).click();
	await dialog.getByLabel(/Důvod/).fill('Zákazník volal, nepřijede');
	await dialog.getByRole('button', { name: 'Zrušit rezervaci' }).click();
	await expect(page.getByRole('status')).toHaveText('Rezervace zrušena.');
	await expect(booking).toHaveCount(0);

	await page.goto(`/wp-admin/admin.php?page=pneukarnik-bookings-list&filtr=1&status=CANCELLED&search=${encodeURIComponent(customer)}`);
	await expect(page.getByRole('row', { name: new RegExp(customer) })).toContainText('Zákazník volal, nepřijede');
});

test('Zadání mimo Pracovní dobu se musí vědomě potvrdit', async ({ page }) => {
	await login(page);
	await page.goto(`/wp-admin/admin.php?page=pneukarnik-booking&view=day&date=${day}`);
	await page.getByRole('button', { name: '+ Nová rezervace' }).click();

	const dialog = page.getByRole('dialog');
	await dialog.getByLabel(serviceTitle).check();
	await dialog.locator('input[name="time"]').fill('18:00');
	await dialog.getByLabel('Jméno *').fill(`${customer} večer`);
	await dialog.getByLabel('Telefon *').fill('603 123 456');

	page.once('dialog', (confirm) => {
		expect(confirm.message()).toContain('mimo Pracovní dobu');
		void confirm.accept();
	});
	await dialog.getByRole('button', { name: 'Zadat rezervaci' }).click();

	await expect(page.getByRole('status')).toHaveText('Rezervace zadána.');
	await expect(page.locator('.pnk-cal__booking', { hasText: `${customer} večer` })).toContainText('18:00–19:00');
});
