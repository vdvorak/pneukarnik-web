import { expect, test } from '@playwright/test';
import { uniqueTitle } from './support/admin';
import { waitForMailAbout } from './support/mailpit';
import { createService, deletePosts, setMenuOrder } from './support/wp';

let serviceId = 0;
let title = '';

test.beforeAll(() => {
	title = uniqueTitle('Prodej pneumatik');
	serviceId = createService(title, `e2e-prodej-pneumatik-${Date.now()}`, 'pneuservis', { _service_inquiry: '1', _service_price: '', _service_price_by_vehicle: '1' });
	// První Služba s Poptávkou v pořadí, na ni odkáže Úvod.
	setMenuOrder(serviceId, -100);
});

test.afterAll(() => {
	deletePosts([serviceId]);
});

test('Z Úvodu se Zákazník dostane k formuláři Poptávky a odeslaná Poptávka dojde Provozovateli', async ({ page, request }) => {
	await page.goto('/');
	await page.getByRole('main').getByRole('link', { name: 'Poptat pneumatiky' }).click();

	await expect(page).toHaveURL(/\/pneuservis\/e2e-prodej-pneumatik-\d+\/#poptavka$/);
	await expect(page.getByRole('heading', { level: 1 })).toHaveText(title);
	const price = page.getByRole('complementary', { name: 'Cena' });
	await expect(price.getByText('Cena na dotaz')).toBeVisible();
	await expect(price.getByRole('link', { name: 'Poptat pneumatiky' })).toHaveAttribute('href', '#poptavka');
	const form = page.getByRole('region', { name: 'Poptat pneumatiky' });

	await form.getByRole('button', { name: 'Odeslat poptávku' }).click();
	await expect(form.getByText('Poptávku jsme neodeslali. Zkontrolujte prosím zvýrazněná pole.')).toBeVisible();
	await expect(form.getByText('Vyplňte rozměr pneumatik.')).toBeVisible();
	await expect(form.getByText('Vyberte letní, nebo zimní.')).toBeVisible();
	await expect(form.getByText('Vyplňte telefon, nebo e‑mail.')).toHaveCount(2);
	await expect(form.getByLabel('Rozměr pneumatik')).toBeFocused();

	const name = `E2E Poptávající ${Date.now()}`;
	const email = `e2e-poptavka-${Date.now()}@example.test`;
	await form.getByLabel('Rozměr pneumatik').fill('205/55 R16');
	await form.getByLabel('Zimní').check();
	await form.getByLabel('Počet kusů').fill('2');
	await form.getByLabel('Jméno nebo firma').fill(name);
	await form.getByLabel('E‑mail').fill(email);
	await form.getByLabel(/Poznámka/).fill('Nejlépe Barum.');
	await form.getByRole('button', { name: 'Odeslat poptávku' }).click();

	await expect(form.getByText('Děkujeme, poptávku jsme přijali.')).toBeVisible();
	await expect(form.getByRole('button', { name: 'Odeslat poptávku' })).toBeHidden();
	const mail = await waitForMailAbout(request, name, /^Poptávka pneumatik: 205\/55 R16, zimní, 2 ks/);
	expect(mail.replyTo).toEqual([email]);
	expect(mail.text).toContain('Nejlépe Barum.');
});

test('Služba bez Poptávky formulář nemá', async ({ page }) => {
	const slug = `e2e-geometrie-bez-poptavky-${Date.now()}`;
	const other = createService(uniqueTitle('Geometrie bez poptávky'), slug, 'pneuservis');
	try {
		await page.goto(`/pneuservis/${slug}/`);
		await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
		await expect(page.getByRole('region', { name: 'Poptat pneumatiky' })).toHaveCount(0);
		await expect(page.getByRole('link', { name: 'Poptat pneumatiky' })).toHaveCount(0);
	} finally {
		deletePosts([other]);
	}
});
