import { expect, test } from '@playwright/test';
import { login, publishService, uniqueTitle } from './support/admin';

test.beforeEach(async ({ page }) => {
	await login(page);
});

test('Služba z administrace je na rozcestníku i v detailu se všemi částmi v pořadí', async ({ page }) => {
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
	await expect(page.getByRole('heading', { level: 1 })).toHaveText('Služby');
	await expect(page.locator('main h2')).toHaveText(['Pneuservis']);
	await page.getByRole('link', { name: title }).click();

	await expect(page).toHaveURL(/\/pneuservis\/e2e-prezuti-\d+\/$/);
	await expect(page.getByRole('heading', { level: 1 })).toHaveText(title);
	await expect(page.getByText('Sezónní přezutí včetně vyvážení.')).toBeVisible();
	await expect(page.locator('main h2')).toHaveText([
		'Co zahrnuje',
		'Jak to probíhá',
		'Cena',
		'Co si vzít s sebou',
		'Časté dotazy',
		'Související služby',
	]);
	await expect(page.getByRole('listitem').filter({ hasText: 'Demontáž a montáž kol' })).toBeVisible();
	await expect(page.getByText('zhruba 30 minut')).toBeVisible();
	await expect(page.getByText('od 600 Kč')).toBeVisible();
	await expect(page.getByText('osobní auto do 16"')).toBeVisible();
	await expect(page.getByText('Musím čekat na místě?')).toBeVisible();
	await expect(page.getByRole('main').getByRole('link', { name: 'Rezervovat' })).toHaveAttribute('href', /\/rezervace\//);
	await expect(page.getByRole('link', { name: /Zavolat/ })).toHaveAttribute('href', /^tel:\+420/);
	await page.getByRole('link', { name: related }).click();
	await expect(page.getByRole('heading', { level: 1 })).toHaveText(related);
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
	await expect(page.getByText('Cena dle vozu')).toBeVisible();
	await expect(page.getByRole('main').getByRole('link', { name: 'Rezervovat' })).toHaveCount(0);
	await expect(page.getByRole('link', { name: /Zavolat/ })).toBeVisible();
});

test('Stránka Služby ukáže obě Kategorie, přepínač bez JavaScriptu nechá jednu', async ({ page, browser }) => {
	const tyres = uniqueTitle('Uskladnění');
	await publishService(page, { title: tyres, category: 'Pneuservis', perex: 'Kola uschováme do další sezóny.', price: 800, duration: 30, bookable: true, icon: 'Sezónní uskladnění' });
	const trip = uniqueTitle('Prohlídka');
	await publishService(page, { title: trip, category: 'Autoservis', perex: 'Kontrola před cestou.', price: 900, duration: 60 });

	const context = await browser.newContext({ javaScriptEnabled: false });
	const visitor = await context.newPage();
	await visitor.goto('/sluzby/');
	const card = (title: string) => visitor.locator('.karta-sluzby').filter({ hasText: title });
	const filter = visitor.getByRole('navigation', { name: 'Kategorie Služeb' });

	await expect(visitor.getByRole('heading', { level: 1 })).toHaveText('Služby');
	await expect(visitor.locator('main h2')).toHaveText(['Pneuservis', 'Autoservis']);
	await expect(filter.getByRole('link', { name: 'Vše' })).toHaveAttribute('aria-current', 'page');
	await expect(visitor.getByRole('navigation', { name: 'Hlavní menu' }).getByRole('link', { name: 'Služby' })).toHaveAttribute('aria-current', 'page');
	await expect(card(tyres).locator('.karta-sluzby__ikona svg')).toBeVisible();
	await expect(card(tyres)).toContainText('800 Kč');
	await expect(card(tyres)).toContainText('Online i telefonem');
	await expect(card(trip).locator('.karta-sluzby__hlava')).toHaveCount(0);
	await expect(card(trip)).toContainText('Jen telefonicky');

	await filter.getByRole('link', { name: 'Autoservis' }).click();
	await expect(visitor).toHaveURL(/\/autoservis\/$/);
	await expect(visitor.locator('main h2')).toHaveText(['Autoservis']);
	await expect(filter.getByRole('link', { name: 'Autoservis' })).toHaveAttribute('aria-current', 'page');
	await expect(card(tyres)).toHaveCount(0);
	await expect(visitor.getByRole('navigation', { name: 'Hlavní menu' }).getByRole('link', { name: 'Služby' })).toHaveAttribute('aria-current', 'true');

	await card(trip).click();
	await expect(visitor.getByRole('heading', { level: 1 })).toHaveText(trip);
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
