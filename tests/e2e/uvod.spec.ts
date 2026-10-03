import { expect, test } from '@playwright/test';
import { dayFromToday, login, publishPromotion, publishService, uniqueTitle } from './support/admin';
import { getOption, setOptions } from './support/wp';

// Testy mění volby celého webu (adresa, Pohotovost, IČ/DIČ, „proč k nám“), jeden po druhém a nakonec je vrátí.
test.describe.configure({ mode: 'serial' });

const touched = ['pneukarnik_address', 'pneukarnik_maps_embed_url', 'pneukarnik_why_us', 'pneukarnik_ico', 'pneukarnik_dic', 'pneukarnik_emergency_enabled', 'pneukarnik_emergency_phone', 'pneukarnik_emergency_text'];
let original: Record<string, string> = {};

test.beforeAll(() => {
	original = Object.fromEntries(touched.map((name) => [name, getOption(name)]));
	// Mapa se bez vlastní adresy z Google Maps složí z adresy.
	setOptions({ pneukarnik_address: 'Dobšická 10, 669 02 Znojmo', pneukarnik_maps_embed_url: '' });
});

test.afterAll(() => {
	setOptions(original);
});

test('Úvod vykreslí vlastní šablona', async ({ page }) => {
	const response = await page.goto('/');

	expect(response?.status()).toBe(200);
	await expect(page.locator('html')).toHaveAttribute('lang', /^cs/);
	await expect(page.locator('link[rel="stylesheet"][href*="/wp-content/themes/pneukarnik/style.css"]')).toHaveCount(1);
	await expect(page.locator('main#obsah')).toBeAttached();
	await expect(page.getByRole('link', { name: 'Pneuservis Kárník' })).toBeVisible();
});

test('Úvod má sekce v pořadí ze zadání a otevírací dobu na 7 dní', async ({ page }) => {
	test.slow(); // Služba a Akce přes administraci, při souběhu všech testů trvá déle.
	await login(page);
	const service = uniqueTitle('Přezutí úvod');
	await publishService(page, { title: service, category: 'Pneuservis', perex: 'Nejžádanější přezutí.', price: 600, duration: 60, featured: true });
	const promotion = uniqueTitle('Akce úvod');
	await publishPromotion(page, { title: promotion, service, price: 499, from: dayFromToday(-1), to: dayFromToday(1) });
	setOptions({ pneukarnik_why_us: 'Ve Znojmě od roku 1991\nPartner sítě BestDrive' });

	await page.goto('/');

	await expect(page.locator('main h1')).toHaveText('Pneuservis a autoservis Jan Kárník');
	await expect(page.locator('main h2')).toHaveText(['Co pro vás uděláme', 'Nejžádanější služby', 'Aktuální akce', 'Proč k nám', 'Otevírací doba', 'Kde nás najdete']);
	await expect(page.locator('.uvod__kategorie').getByRole('link')).toHaveText(['Pneuservis', 'Autoservis']);
	await expect(page.locator('.uvod__nejzadanejsi').getByRole('link', { name: service })).toBeVisible();
	await expect(page.locator('.uvod__akce').getByRole('link', { name: promotion })).toBeVisible();
	await expect(page.locator('.uvod__akce').getByText('499 Kč')).toBeVisible();
	await expect(page.locator('.uvod__proc li')).toHaveText(['Ve Znojmě od roku 1991', 'Partner sítě BestDrive']);
	const days = page.locator('.oteviraci-doba tr');
	await expect(days).toHaveCount(7);
	await expect(days.first().locator('th')).toContainText('Dnes');
	await expect(days.nth(1).locator('th')).toContainText('Zítra');
});

test('Telefon a Rezervovat jsou na mobilu vidět bez scrollování', async ({ page }) => {
	await page.setViewportSize({ width: 360, height: 640 });

	for (const path of ['/', '/pneuservis/']) {
		await page.goto(path);
		const header = page.locator('.site-header__kontakt');
		await expect(header.getByRole('link', { name: '+420 775 565 326' })).toHaveAttribute('href', 'tel:+420775565326');
		await expect(header.getByRole('link', { name: '+420 775 565 326' })).toBeInViewport();
		await expect(header.getByRole('link', { name: 'Rezervovat' })).toBeInViewport();
	}
	await page.goto('/');
	await expect(page.locator('.uvod__cta').getByRole('link', { name: 'Rezervovat' })).toBeInViewport();
	await expect(page.locator('.uvod__cta').getByRole('link', { name: '+420 775 565 326' })).toBeInViewport();
});

test('Mapa nic nenačte od Googlu, dokud na ni Zákazník neklikne', async ({ page }) => {
	const google: string[] = [];
	page.on('request', (request) => {
		if (/google|gstatic/.test(new URL(request.url()).hostname)) google.push(request.url());
	});

	await page.goto('/');
	await page.waitForLoadState('networkidle');
	expect(google).toEqual([]);
	await expect(page.locator('.uvod__mapa iframe')).toHaveCount(0);

	await page.getByRole('button', { name: 'Zobrazit mapu' }).click();

	await expect(page.locator('.uvod__mapa iframe')).toHaveAttribute('src', /^https:\/\/www\.google\.com\/maps/);
});

test('Kontakty z Nastavení jsou v patičce a Pohotovost jen po zapnutí', async ({ page }) => {
	setOptions({ pneukarnik_ico: '12345678', pneukarnik_dic: 'CZ12345678', pneukarnik_emergency_enabled: '0', pneukarnik_emergency_phone: '+420 600 700 800', pneukarnik_emergency_text: 'Defekt na cestě nonstop' });

	await page.goto('/pneuservis/');
	const footer = page.locator('.site-footer');
	await expect(footer).toContainText('Pneuservis a autoservis Jan Kárník');
	await expect(footer).toContainText('IČ: 12345678');
	await expect(footer).toContainText('DIČ: CZ12345678');
	await expect(footer.getByRole('link', { name: '+420 775 565 326' })).toBeVisible();
	await expect(page.getByText('Pohotovost')).toHaveCount(0);
	await expect(page.getByText('+420 600 700 800')).toHaveCount(0);

	setOptions({ pneukarnik_emergency_enabled: '1' });
	await page.reload();

	const emergency = page.locator('.site-header .pohotovost');
	await expect(emergency).toContainText('Pohotovost');
	await expect(emergency.getByRole('link', { name: '+420 600 700 800' })).toHaveAttribute('href', 'tel:+420600700800');
	await expect(emergency).toContainText('Defekt na cestě nonstop');
});
