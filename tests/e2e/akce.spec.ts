import { expect, test, type Page } from '@playwright/test';
import { dayFromToday, login, publishPromotion, publishService, uniqueTitle } from './support/admin';

test.beforeEach(async ({ page }) => {
	await login(page);
});

/** Karta Služby v rozcestníku. */
const card = (page: Page, title: string) => page.locator('.karta-sluzby').filter({ hasText: title });

test('Platná Akce má štítek na kartě a blok s cenou a platností v detailu', async ({ page }) => {
	const service = uniqueTitle('Dekarbonizace');
	await publishService(page, { title: service, category: 'Autoservis', perex: 'Čištění motoru.', price: 1500, duration: 60 });
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
	await expect(card(page, service).getByText('Akce', { exact: true })).toBeVisible();

	await card(page, service).getByRole('link', { name: service }).click();
	const block = page.locator('.sluzba__akce');
	await expect(block.getByRole('heading', { level: 2 })).toHaveText(title);
	await expect(block.getByText('990 Kč')).toBeVisible();
	await expect(block.getByText('Jen pro osobní auta.')).toBeVisible();
	const [y, m, d] = to.split('-').map(Number);
	await expect(block.getByText(`Akce platí do ${d}. ${m}. ${y}.`)).toBeVisible();
	await expect(page.getByText('1 500 Kč')).toBeVisible();
});

test('Akce mimo platnost se nezobrazí ani na kartě, ani v detailu', async ({ page }) => {
	const service = uniqueTitle('Geometrie');
	await publishService(page, { title: service, category: 'Pneuservis', perex: 'Seřízení geometrie.', price: 800, duration: 60 });
	await publishPromotion(page, { title: uniqueTitle('Skončila'), service, price: 500, from: dayFromToday(-10), to: dayFromToday(-1) });
	await publishPromotion(page, { title: uniqueTitle('Teprve bude'), service, price: 600, from: dayFromToday(1), to: dayFromToday(10) });

	await page.goto('/pneuservis/');
	await expect(card(page, service)).toBeVisible();
	await expect(card(page, service).getByText('Akce', { exact: true })).toHaveCount(0);

	await card(page, service).getByRole('link', { name: service }).click();
	await expect(page.getByRole('heading', { level: 1 })).toHaveText(service);
	await expect(page.locator('.sluzba__akce')).toHaveCount(0);
});
