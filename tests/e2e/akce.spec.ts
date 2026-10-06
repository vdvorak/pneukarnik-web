import { expect, test, type Page } from '@playwright/test';
import { dayFromToday, login, publishPromotion, publishService, uniqueTitle } from './support/admin';

test.beforeEach(async ({ page }) => {
	await login(page);
});

/** Karta Služby v rozcestníku. */
const card = (page: Page, title: string) => page.locator('.karta-sluzby').filter({ hasText: title });

test('Platná Akce má štítek na kartě, blok s cenou a platností v detailu a akční cenu v rezervaci', async ({ page }) => {
	test.slow(); // Služba a Akce přes administraci, při souběhu všech testů trvá déle.
	const service = uniqueTitle('Dekarbonizace');
	await publishService(page, { title: service, category: 'Autoservis', perex: 'Čištění motoru.', price: 1500, duration: 60, bookable: true });
	const title = uniqueTitle('Zaváděcí cena');
	const to = dayFromToday(1);
	await publishPromotion(page, {
		title,
		service,
		price: 990,
		description: 'Jen pro osobní auta.',
		from: dayFromToday(-1),
		to,
	});

	await page.goto('/autoservis/');
	const [y, m, d] = to.split('-').map(Number);
	await expect(card(page, service).getByText(/^Akce do /)).toHaveText(`Akce do ${d}. ${m}.${y === new Date().getFullYear() ? '' : ` ${y}`}`);
	await expect(card(page, service).locator('.karta-sluzby__cena-akce')).toHaveText('990\u00a0Kč');
	await expect(card(page, service).locator('s')).toHaveText('1\u00a0500\u00a0Kč');

	await card(page, service).getByRole('link', { name: service, exact: true }).click();
	const block = page.locator('.sluzba__akce');
	await expect(block.getByRole('heading', { level: 2 })).toHaveText(title);
	await expect(block.getByText('990 Kč')).toBeVisible();
	await expect(block.getByText('Jen pro osobní auta.')).toBeVisible();
	await expect(block.getByText(`Akce platí do ${d}. ${m}. ${y}.`)).toBeVisible();
	await expect(page.getByText('1 500 Kč')).toBeVisible();

	// Ve výběru Služby v rezervaci je akční cena a za ní běžná.
	await page.goto('/rezervace/');
	await expect(page.locator('#rez-sluzba-1 option').filter({ hasText: service })).toHaveText(`${service} (akce 990\u00a0Kč, běžně 1\u00a0500\u00a0Kč)`);
});

test('Akce bez ceny ukáže na kartě, v detailu i v rezervaci svůj název a běžnou cenu Služby', async ({ page }) => {
	test.slow(); // Služba a Akce přes administraci, při souběhu všech testů trvá déle.
	const service = uniqueTitle('Geometrie');
	await publishService(page, { title: service, category: 'Pneuservis', perex: 'Seřízení geometrie.', price: 1200, duration: 60, bookable: true });
	const title = uniqueTitle('Kontrola brzd zdarma');
	await publishPromotion(page, { title, service, from: dayFromToday(-1), to: dayFromToday(1) });

	await page.goto('/pneuservis/');
	await expect(card(page, service).getByText(/^Akce do /)).toBeVisible();
	await expect(card(page, service).getByText(title)).toBeVisible();
	await expect(card(page, service).locator('.karta-sluzby__cena')).toHaveText('1\u00a0200\u00a0Kč');
	await expect(card(page, service).locator('s')).toHaveCount(0);

	await card(page, service).getByRole('link', { name: service, exact: true }).click();
	const block = page.locator('.sluzba__akce');
	await expect(block.getByRole('heading', { level: 2 })).toHaveText(title);
	await expect(block.locator('.sluzba__akce-cena')).toHaveCount(0);

	await page.goto('/rezervace/');
	await expect(page.locator('#rez-sluzba-1 option').filter({ hasText: service })).toHaveText(`${service} (1\u00a0200\u00a0Kč, akce: ${title})`);
});

test('Služba s platnou Akcí je ve své Kategorii napřed', async ({ page }) => {
	test.slow(); // Služby a Akce přes administraci, při souběhu všech testů trvá déle.
	// Záporné pořadí: obě Služby jsou před Službami z jiných testů, bez Akce by byla první ta bez Akce.
	const plain = uniqueTitle('Výměna oleje');
	await publishService(page, { title: plain, category: 'Autoservis', perex: 'Olej a filtr.', price: 900, duration: 60, order: -3 });
	const promoted = uniqueTitle('Klimatizace');
	await publishService(page, { title: promoted, category: 'Autoservis', perex: 'Doplnění chladiva.', price: 1200, duration: 60, order: -2 });
	await publishPromotion(page, { title: uniqueTitle('Jarní klimatizace'), service: promoted, price: 990, from: dayFromToday(-1), to: dayFromToday(1) });

	for (const path of ['/sluzby/', '/autoservis/']) {
		await page.goto(path);
		const titles = await page.locator('.karta-sluzby__nazev').allTextContents();
		expect(titles.indexOf(promoted), path).toBeGreaterThanOrEqual(0);
		expect(titles.indexOf(promoted), path).toBeLessThan(titles.indexOf(plain));
	}
});

test('Akce mimo platnost se nezobrazí ani na kartě, ani v detailu', async ({ page }) => {
	test.slow(); // Služba a Akce přes administraci, při souběhu všech testů trvá déle.
	const service = uniqueTitle('Geometrie');
	await publishService(page, { title: service, category: 'Pneuservis', perex: 'Seřízení geometrie.', price: 800, duration: 60 });
	await publishPromotion(page, { title: uniqueTitle('Skončila'), service, price: 500, from: dayFromToday(-10), to: dayFromToday(-1) });
	await publishPromotion(page, { title: uniqueTitle('Teprve bude'), service, price: 600, from: dayFromToday(1), to: dayFromToday(10) });

	await page.goto('/pneuservis/');
	await expect(card(page, service)).toBeVisible();
	await expect(card(page, service).getByText(/^Akce do /)).toHaveCount(0);
	await expect(card(page, service).locator('s')).toHaveCount(0);

	await card(page, service).getByRole('link', { name: service, exact: true }).click();
	await expect(page.getByRole('heading', { level: 1 })).toHaveText(service);
	await expect(page.locator('.sluzba__akce')).toHaveCount(0);
});
