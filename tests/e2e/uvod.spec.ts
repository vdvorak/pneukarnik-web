import { expect, test } from '@playwright/test';
import { dayFromToday, login, publishPromotion, publishService, uniqueTitle } from './support/admin';
import { getJsonOption, getOption, setJsonOption, setOptions } from './support/wp';

// Testy Úvodu a Kontaktu mění volby celého webu (adresa, Pohotovost, IČ/DIČ, rok založení, „proč k nám“, recenze), jeden po druhém a nakonec je vrátí.
test.describe.configure({ mode: 'serial' });

const touched = ['pneukarnik_address', 'pneukarnik_maps_embed_url', 'pneukarnik_why_us', 'pneukarnik_founded_year', 'pneukarnik_ico', 'pneukarnik_dic', 'pneukarnik_emergency_enabled', 'pneukarnik_emergency_phone', 'pneukarnik_emergency_text', 'pneukarnik_reviews_enabled', 'pneukarnik_reviews_api_key', 'pneukarnik_reviews_place_id'];
let original: Record<string, string> = {};
let originalReviews: unknown = null;

test.beforeAll(() => {
	original = Object.fromEntries(touched.map((name) => [name, getOption(name)]));
	originalReviews = getJsonOption('pneukarnik_reviews_cache');
	// Mapa se bez vlastní adresy z Google Maps složí z adresy.
	setOptions({ pneukarnik_address: 'Dobšická 10, 669 02 Znojmo', pneukarnik_maps_embed_url: '' });
});

test.afterAll(() => {
	setOptions(original);
	setJsonOption('pneukarnik_reviews_cache', originalReviews);
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
	setOptions({ pneukarnik_why_us: 'Ve Znojmě od roku 1991\nPartner sítě BestDrive | Věrnostní karta BestDrive platí i u nás.' });

	await page.goto('/');

	await expect(page.locator('main h1')).toHaveText('Pneuservis a autoservis Jan Kárník');
	await expect(page.locator('.uvod__dnes')).toContainText('Dnes:');
	await expect(page.locator('.uvod__dnes')).toContainText('Zítra:');
	await expect(page.locator('main h2')).toHaveText(['Co pro vás uděláme', 'Nejžádanější služby', 'Aktuální akce', 'Proč k nám', 'Otevírací doba', 'Kde nás najdete']);
	await expect(page.locator('.uvod__kategorie').getByRole('link')).toHaveText(['Pneuservis', 'Autoservis']);
	await expect(page.locator('.uvod__nejzadanejsi').getByRole('link', { name: service })).toBeVisible();
	await expect(page.locator('.uvod__akce').getByRole('link', { name: promotion })).toBeVisible();
	await expect(page.locator('.uvod__akce').getByText('499 Kč')).toBeVisible();
	const reasons = page.locator('.uvod__proc li');
	await expect(reasons.locator('.duvod__nadpis')).toHaveText(['Ve Znojmě od roku 1991', 'Partner sítě BestDrive']);
	await expect(reasons.first().locator('.duvod__text')).toHaveCount(0);
	await expect(reasons.nth(1).locator('.duvod__text')).toHaveText('Věrnostní karta BestDrive platí i u nás.');
	const days = page.locator('.oteviraci-doba tr');
	await expect(days).toHaveCount(7);
	await expect(days.first().locator('th')).toContainText('Dnes');
	await expect(days.nth(1).locator('th')).toContainText('Zítra');
});

test('Telefon a Rezervovat jsou na mobilu vidět bez scrollování', async ({ page }) => {
	await page.setViewportSize({ width: 360, height: 640 });

	for (const path of ['/', '/pneuservis/']) {
		await page.goto(path);
		const call = page.locator('.site-header').getByRole('link', { name: 'Zavolat +420 775 565 326' });
		await expect(call).toHaveAttribute('href', 'tel:+420775565326');
		await expect(call).toBeInViewport();
		await expect(page.locator('.mobilni-lista').getByRole('link', { name: 'Rezervovat' })).toBeInViewport();
	}
	await page.goto('/');
	await expect(page.locator('.uvod__cta').getByRole('link', { name: 'Rezervovat termín' })).toBeInViewport();
	await expect(page.locator('.uvod__cta').getByRole('link', { name: '+420 775 565 326' })).toHaveAttribute('href', 'tel:+420775565326');
	await expect(page.locator('.uvod__cta').getByRole('link', { name: '+420 775 565 326' })).toBeInViewport();
});

test('Hero ukazuje rok založení jen zadaný a fotka je na mobilu malá', async ({ browser, request }) => {
	const context = await browser.newContext({ viewport: { width: 360, height: 640 }, deviceScaleFactor: 3 });
	const page = await context.newPage();
	setOptions({ pneukarnik_founded_year: '1991' });

	await page.goto('/');

	await expect(page.locator('.uvod__hero .eyebrow')).toHaveText('Znojmo · od roku 1991');
	const photo = page.locator('.uvod__foto');
	await expect(photo).toHaveJSProperty('complete', true);
	const src = await photo.evaluate((img: HTMLImageElement) => img.currentSrc);
	expect(src).toMatch(/\.webp$/);
	expect((await (await request.get(src)).body()).length).toBeLessThan(200_000);

	setOptions({ pneukarnik_founded_year: '' });
	await page.reload();

	await expect(page.locator('.uvod__hero .eyebrow')).toHaveText('Znojmo');
	await context.close();
});

test('Prázdné sekce Úvodu se nevykreslí', async ({ page }) => {
	setOptions({ pneukarnik_why_us: '', pneukarnik_reviews_enabled: '0' });

	await page.goto('/');

	await expect(page.locator('.uvod__proc, .uvod__recenze')).toHaveCount(0);
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

	const emergency = page.locator('.pohotovost');
	await expect(emergency).toContainText('Pohotovost');
	await expect(emergency.getByRole('link', { name: '+420 600 700 800' })).toHaveAttribute('href', 'tel:+420600700800');
	await expect(emergency).toContainText('Defekt na cestě nonstop');
});

test('Kontakt má adresu, otevírací dobu, příjezd, mapu po kliknutí a fakturační údaje', async ({ page }) => {
	setOptions({ pneukarnik_ico: '12345678', pneukarnik_dic: 'CZ12345678' });

	await page.goto('/kontakt/');

	await expect(page.locator('main h2')).toHaveText(['Adresa a spojení', 'Otevírací doba', 'Jak k nám', 'Fakturační údaje', 'Mapa']);
	const contact = page.locator('.kontakt__spojeni');
	await expect(contact).toContainText('Dobšická 10');
	await expect(contact).toContainText('669 02 Znojmo');
	await expect(contact.getByRole('link', { name: '+420 775 565 326' })).toHaveAttribute('href', 'tel:+420775565326');
	await expect(contact.getByRole('link', { name: 'Rezervovat' })).toHaveAttribute('href', /\/rezervace\/$/);
	await expect(page.locator('.kontakt__doba .oteviraci-doba tr')).toHaveCount(7);
	await expect(page.locator('.kontakt__fakturace')).toContainText('IČ: 12345678');
	await expect(page.locator('.kontakt__fakturace')).toContainText('DIČ: CZ12345678');
	await expect(page.locator('.kontakt__mapa iframe')).toHaveCount(0);

	await page.getByRole('button', { name: 'Zobrazit mapu' }).click();

	await expect(page.locator('.kontakt__mapa iframe')).toHaveAttribute('src', /^https:\/\/www\.google\.com\/maps/);
});

test('Kontakt nevykreslí prázdné kontaktní údaje', async ({ page }) => {
	setOptions({ pneukarnik_address: '', pneukarnik_ico: '', pneukarnik_dic: '' });
	try {
		await page.goto('/kontakt/');

		await expect(page.locator('main h2')).toHaveText(['Adresa a spojení', 'Otevírací doba', 'Jak k nám']);
		await expect(page.locator('.kontakt__adresa br')).toHaveCount(0);
		await expect(page.getByRole('link', { name: 'Otevřít v Google Maps' })).toHaveCount(0);
	} finally {
		setOptions({ pneukarnik_address: 'Dobšická 10, 669 02 Znojmo' });
	}
});

test('Google recenze z cache serveru jsou na Úvodu bez klíče API v HTML a bez požadavků na Google', async ({ page, request }) => {
	const key = 'AIza-E2E-tajny-klic';
	setOptions({ pneukarnik_why_us: 'Partner sítě BestDrive', pneukarnik_reviews_enabled: '1', pneukarnik_reviews_api_key: key, pneukarnik_reviews_place_id: 'ChIJ-e2e' });
	setJsonOption('pneukarnik_reviews_cache', {
		updated_at: '2026-10-01 04:00',
		data: {
			rating: 4.8,
			count: 123,
			url: 'https://www.google.com/maps/place/?q=place_id:ChIJ-e2e',
			reviews: [{ author: 'Eva Nováková', author_url: 'https://www.google.com/maps/contrib/2', rating: 5, text: 'Rychlé přezutí, milý personál.', date: '2026-09-20' }],
		},
	});
	const google: string[] = [];
	page.on('request', (r) => {
		if (/google|gstatic|googleusercontent/.test(new URL(r.url()).hostname)) google.push(r.url());
	});

	await page.goto('/');

	await expect(page.locator('main h2')).toContainText(['Proč k nám', 'Hodnocení na Google', 'Otevírací doba']);
	const reviews = page.locator('.uvod__recenze');
	await expect(reviews.locator('.recenze__prumer')).toHaveText('4,8');
	await expect(reviews.locator('.recenze__souhrn')).toContainText('z 5 (123 hodnocení)');
	await expect(reviews).toContainText('Rychlé přezutí, milý personál.');
	await expect(reviews.getByRole('link', { name: 'Všechna hodnocení na Google' })).toHaveAttribute('href', 'https://www.google.com/maps/place/?q=place_id:ChIJ-e2e');
	await page.waitForLoadState('networkidle');
	expect(google).toEqual([]);
	expect(await (await request.get('/')).text()).not.toContain(key);
});
