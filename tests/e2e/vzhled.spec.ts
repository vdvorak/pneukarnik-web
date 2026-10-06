import { expect, test } from '@playwright/test';

const pages = ['/', '/sluzby/', '/pneuservis/', '/kontakt/', '/o-nas/', '/ochrana-osobnich-udaju/', '/rezervace/', '/rezervace/zruseni/?r=' + '0'.repeat(64), '/odhlaseni/?t=1.' + '0'.repeat(64), '/neexistujici-stranka/'];

test('Web nenačítá nic z cizích domén', async ({ page, baseURL }) => {
	const own = new URL(baseURL ?? 'http://localhost:8080').host;
	const foreign: string[] = [];
	page.on('request', (request) => {
		const url = new URL(request.url());
		if (url.protocol.startsWith('http') && url.host !== own) foreign.push(request.url());
	});

	for (const path of pages) {
		await page.goto(path);
		await page.waitForLoadState('networkidle');
	}

	expect(foreign).toEqual([]);
});

test('Na 360 px se stránky nescrollují do strany', async ({ page }) => {
	await page.setViewportSize({ width: 360, height: 640 });

	for (const path of pages) {
		await page.goto(path);
		expect(await page.evaluate(() => document.documentElement.scrollWidth), path).toBeLessThanOrEqual(360);
	}
});

test.describe('Hlavička na mobilu', () => {
	test.use({ viewport: { width: 375, height: 700 } });

	test('má telefon a Menu, které rozbalí odkazy a Rezervovat', async ({ page }) => {
		await page.goto('/kontakt/');
		const header = page.locator('.site-header');

		await expect(header.getByRole('link', { name: 'Zavolat +420 775 565 326' })).toHaveAttribute('href', 'tel:+420775565326');
		await expect(page.getByRole('navigation', { name: 'Hlavní menu' })).toBeHidden();
		const menu = page.getByRole('navigation', { name: 'Menu', exact: true });
		await expect(menu).toBeHidden();

		await header.getByText('Menu', { exact: true }).click();

		await expect(menu.getByRole('link', { name: 'Kontakt' })).toHaveAttribute('aria-current', 'page');
		await expect(menu.getByRole('link', { name: 'Rezervovat' })).toBeVisible();
		await menu.getByRole('link', { name: 'O nás' }).click();
		await expect(page).toHaveURL(/\/o-nas\/$/);
	});

	test('Menu jde otevřít i bez JavaScriptu', async ({ browser }) => {
		const context = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 375, height: 700 } });
		const page = await context.newPage();
		await page.goto('/');

		await page.locator('.site-header').getByText('Menu', { exact: true }).click();

		await expect(page.getByRole('navigation', { name: 'Menu', exact: true }).getByRole('link', { name: 'Rezervace' })).toBeVisible();
		await context.close();
	});

	test('dole je lišta Zavolat a Rezervovat', async ({ page }) => {
		await page.goto('/');
		const bar = page.getByRole('navigation', { name: 'Rychlý kontakt' });

		await expect(bar.getByRole('link', { name: 'Zavolat' })).toBeInViewport();
		await expect(bar.getByRole('link', { name: 'Rezervovat' })).toHaveAttribute('href', /\/rezervace\/$/);

		await page.goto('/rezervace/');
		await expect(page.getByRole('navigation', { name: 'Rychlý kontakt' })).toHaveCount(0);
	});
});

test('Stránka 404 vede zpět na Úvod, ke Službám, k rezervaci a na telefon', async ({ page }) => {
	const response = await page.goto('/tahle-stranka-neni/');

	expect(response?.status()).toBe(404);
	await expect(page.getByText('Chyba 404')).toBeVisible();
	await expect(page.getByRole('heading', { level: 1 })).toHaveText('Stránka nenalezena');
	const main = page.getByRole('main');
	await expect(main.getByRole('link', { name: 'Služby' })).toHaveAttribute('href', /\/sluzby\/$/);
	await expect(main.getByRole('link', { name: 'Rezervace' })).toHaveAttribute('href', /\/rezervace\/$/);
	await expect(main.getByRole('link', { name: '+420 775 565 326' })).toHaveAttribute('href', 'tel:+420775565326');

	await main.getByRole('link', { name: 'Zpět na úvod' }).click();
	await expect(page).toHaveURL(/:\d+\/$/);
});
