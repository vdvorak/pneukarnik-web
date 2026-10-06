import { expect, test } from '@playwright/test';
import { login, publishService, uniqueTitle } from './support/admin';
import { createGuide, createService, deletePosts } from './support/wp';

test.beforeEach(async ({ page }) => {
	await login(page);
});

test('Služba z administrace je na rozcestníku i v detailu se všemi částmi v pořadí', async ({ page, browser }) => {
	const related = uniqueTitle('Vyvážení kol');
	await publishService(page, {
		title: related,
		category: 'Pneuservis',
		perex: 'Vyvážení všech čtyř kol.',
		price: 300,
		duration: 30,
	});

	const title = uniqueTitle('Přezutí');
	await publishService(page, {
		title,
		category: 'Pneuservis',
		perex: 'Sezónní přezutí včetně vyvážení.',
		includes: ['Demontáž a montáž kol', 'Vyvážení'],
		process: 'Přijedete na Termín, auto zvedneme a kola vyměníme.',
		durationText: 'zhruba 30 minut',
		price: 600,
		priceFrom: true,
		priceNote: 'osobní auto do 16"',
		bring: ['Kola nebo pneumatiky', 'Pojistný šroub'],
		faq: [{ question: 'Musím čekat na místě?', answer: 'Můžete, máme čekárnu.' }],
		related: [related],
		duration: 60,
		bookable: true,
	});

	await page.goto('/pneuservis/');
	await expect(page.getByRole('heading', { level: 1 })).toHaveText('Pneuservis');
	await page.getByRole('link', { name: title }).click();

	await expect(page).toHaveURL(/\/pneuservis\/e2e-prezuti-\d+\/$/);
	const url = page.url();
	const slug = new URL(url).pathname.split('/').at(-2);
	await expect(page.getByRole('heading', { level: 1 })).toHaveText(title);
	await expect(page.getByRole('main').getByRole('link', { name: 'Pneuservis' })).toHaveAttribute('href', /\/pneuservis\/$/);
	await expect(page.getByText('Sezónní přezutí včetně vyvážení.')).toBeVisible();
	await expect(page.locator('main h2')).toHaveText([
		'Cena',
		'Co zahrnuje',
		'Jak to probíhá',
		'Co si vzít s sebou',
		'Časté dotazy',
		'Související služby',
	]);
	await expect(page.getByRole('listitem').filter({ hasText: 'Demontáž a montáž kol' })).toBeVisible();
	await expect(page.getByText('zhruba 30 minut')).toBeVisible();
	const price = page.getByRole('complementary', { name: 'Cena' });
	await expect(price.getByText('od 600 Kč')).toBeVisible();
	await expect(price.getByText('Cena zahrnuje: osobní auto do 16"')).toBeVisible();
	await expect(price.getByRole('link', { name: 'Rezervovat' })).toHaveAttribute('href', new RegExp(`/rezervace/\\?sluzba=${slug}$`));
	await expect(price.getByRole('link', { name: 'Zavolat +420 775 565 326' })).toHaveAttribute('href', 'tel:+420775565326');
	await expect(price.getByText('Tuto službu objednáváme jen telefonicky.')).toHaveCount(0);
	await expect(page.getByText('Můžete, máme čekárnu.')).toBeHidden();

	// Na mobilu je karta Cena pod hero jen s cenou, Rezervovat s touto Službou je v liště.
	await page.setViewportSize({ width: 375, height: 700 });
	await expect(price.getByText('od 600 Kč')).toBeInViewport();
	await expect(price.getByRole('link', { name: 'Rezervovat' })).toBeHidden();
	await expect(page.getByRole('navigation', { name: 'Rychlý kontakt' }).getByRole('link', { name: 'Rezervovat' })).toHaveAttribute('href', new RegExp(`\\?sluzba=${slug}$`));
	await page.setViewportSize({ width: 1280, height: 720 });

	await page.getByRole('link', { name: related }).click();
	await expect(page.getByRole('heading', { level: 1 })).toHaveText(related);

	// Časté dotazy se rozbalí i bez JavaScriptu.
	const context = await browser.newContext({ javaScriptEnabled: false });
	const visitor = await context.newPage();
	await visitor.goto(url);
	await visitor.getByText('Musím čekat na místě?').click();
	await expect(visitor.getByText('Můžete, máme čekárnu.')).toBeVisible();
	await context.close();
});

test('Služba jen na telefon s cenou dle vozu ukáže jen vyplněné části a Zavolat', async ({ page }) => {
	const title = uniqueTitle('Diagnostika');
	await publishService(page, {
		title,
		category: 'Autoservis',
		perex: 'Počítačová diagnostika.',
		priceByVehicle: true,
		duration: 45,
	});

	await page.goto('/autoservis/');
	await page.getByRole('link', { name: title }).click();

	await expect(page.locator('main h2')).toHaveText(['Cena']);
	const price = page.getByRole('complementary', { name: 'Cena' });
	await expect(price.getByText('Cena dle vozu')).toBeVisible();
	await expect(price.getByText('Tuto službu objednáváme jen telefonicky.')).toBeVisible();
	await expect(page.getByRole('main').getByRole('link', { name: 'Rezervovat' })).toHaveCount(0);
	await expect(price.getByRole('link', { name: /Zavolat/ })).toBeVisible();

	await page.setViewportSize({ width: 375, height: 700 });
	await expect(price.getByText('Tuto službu objednáváme jen telefonicky.')).toBeInViewport();
	await expect(page.getByRole('navigation', { name: 'Rychlý kontakt' }).getByRole('link', { name: 'Rezervovat' })).toHaveAttribute('href', /\/rezervace\/$/);
});

test('Stránka Služby ukáže obě Kategorie bez přepínače, rozcestník z patičky jednu a cestu na všechny', async ({ page, browser }) => {
	const tyres = uniqueTitle('Uskladnění');
	await publishService(page, { title: tyres, category: 'Pneuservis', perex: 'Kola uschováme do další sezóny.', price: 800, duration: 30, bookable: true, icon: 'Sezónní uskladnění' });
	const trip = uniqueTitle('Prohlídka');
	await publishService(page, { title: trip, category: 'Autoservis', perex: 'Kontrola před cestou.', price: 900, duration: 60 });

	const context = await browser.newContext({ javaScriptEnabled: false });
	const visitor = await context.newPage();
	await visitor.goto('/sluzby/');
	const card = (title: string) => visitor.locator('.karta-sluzby').filter({ hasText: title });
	const menu = visitor.getByRole('navigation', { name: 'Hlavní menu' });

	await expect(visitor.getByRole('heading', { level: 1 })).toHaveText('Služby');
	await expect(visitor.locator('main h2')).toHaveText(['Pneuservis', 'Autoservis']);
	await expect(visitor.getByRole('navigation', { name: 'Kategorie Služeb' })).toHaveCount(0);
	await expect(menu.getByRole('link', { name: 'Služby' })).toHaveAttribute('aria-current', 'page');
	await expect(card(tyres).locator('.karta-sluzby__ikona svg')).toBeVisible();
	await expect(card(tyres)).toContainText('800 Kč');
	await expect(card(tyres)).toContainText('Online i telefonem');
	await expect(card(trip).locator('.karta-sluzby__hlava')).toHaveCount(0);
	await expect(card(trip)).toContainText('Jen telefonicky');

	await visitor.locator('.site-footer').getByRole('link', { name: /^Autoservis/ }).click();
	await expect(visitor).toHaveURL(/\/autoservis\/$/);
	await expect(visitor.getByRole('heading', { level: 1 })).toHaveText('Autoservis');
	await expect(visitor.locator('main h2')).toHaveCount(0);
	await expect(card(tyres)).toHaveCount(0);
	await expect(menu.getByRole('link', { name: 'Služby' })).toHaveAttribute('aria-current', 'true');

	await card(trip).click();
	await expect(visitor.getByRole('heading', { level: 1 })).toHaveText(trip);
	await visitor.goBack();
	await visitor.getByRole('main').getByRole('link', { name: 'Všechny služby' }).click();
	await expect(visitor).toHaveURL(/\/sluzby\/$/);
	await context.close();
});

test('Službu bez perexu nejde zveřejnit', async ({ page }) => {
	const title = uniqueTitle('Bez perexu');
	await publishService(page, { title, category: 'Pneuservis', price: 100, duration: 30 });

	await expect(page.getByText('Služba není zveřejněná, chybí: perex.')).toBeVisible();
	await page.goto('/pneuservis/');
	await expect(page.getByRole('link', { name: title })).toHaveCount(0);
});

test('Neexistující Služba vrací 404', async ({ page }) => {
	const response = await page.goto('/autoservis/tahle-sluzba-neexistuje/');
	expect(response?.status()).toBe(404);
});

test('Karta Služby se zveřejněným Průvodcem má „i“, které vede na Průvodce, zbytek karty na Službu', async ({ page }) => {
	const stamp = Date.now();
	const guided = uniqueTitle('Přezutí s průvodcem');
	const drafted = uniqueTitle('Geometrie s konceptem');
	const guidedId = createService(guided, `e2e-prezuti-s-pruvodcem-${stamp}`, 'pneuservis');
	const draftedId = createService(drafted, `e2e-geometrie-s-konceptem-${stamp}`, 'pneuservis');
	const guide = uniqueTitle('Kdy přezout');
	const guides = [createGuide(guide, guidedId), createGuide(uniqueTitle('Koncept průvodce'), draftedId, 'draft')];
	try {
		await page.goto('/sluzby/');
		const card = (title: string) => page.locator('.karta-sluzby').filter({ hasText: title });
		const info = card(guided).getByRole('link', { name: `Průvodce: ${guide}` });

		await expect(info).toHaveAttribute('title', `Průvodce: ${guide}`);
		await expect(info.locator('svg')).toBeVisible();
		await expect(card(drafted).locator('.karta-sluzby__hlava')).toHaveCount(0);
		await expect(card(drafted).getByRole('link')).toHaveCount(1);

		// Z klávesnice: „i“ je před názvem karty a má viditelný focus.
		await card(guided).getByRole('link', { name: guided }).focus();
		await page.keyboard.press('Shift+Tab');
		await expect(info).toBeFocused();
		await expect(info).toHaveCSS('outline-style', 'solid');

		await info.click();
		await expect(page).toHaveURL(/\/pruvodce\/e2e-kdy-prezout-\d+\/$/);
		await expect(page.getByRole('heading', { level: 1 })).toHaveText(guide);

		await page.goBack();
		await card(guided).click();
		await expect(page).toHaveURL(new RegExp(`/pneuservis/e2e-prezuti-s-pruvodcem-${stamp}/$`));
	} finally {
		deletePosts([...guides, guidedId, draftedId]);
	}
});
