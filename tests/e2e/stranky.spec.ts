import { expect, test } from '@playwright/test';
import { login, publishGuide, publishService, uniqueTitle } from './support/admin';
import { getOption, setOptions } from './support/wp';

test('O nás, Kontakt a Ochrana osobních údajů jsou v navigaci a patičce', async ({ page }) => {
	const pages = [
		{ menu: '.site-nav', name: 'O nás', path: /\/o-nas\/$/ },
		{ menu: '.site-nav', name: 'Kontakt', path: /\/kontakt\/$/ },
		{ menu: '.site-footer', name: 'Ochrana osobních údajů', path: /\/ochrana-osobnich-udaju\/$/ },
	];
	for (const { menu, name, path } of pages) {
		await page.goto('/');
		await page.locator(menu).getByRole('link', { name, exact: true }).click();

		await expect(page).toHaveURL(path);
		await expect(page.getByRole('heading', { level: 1 })).toHaveText(name);
	}
});

test('O nás má BestDrive s věrnostní kartou a galerii', async ({ page }) => {
	const response = await page.goto('/o-nas/');

	expect(response?.status()).toBe(200);
	await expect(page.getByRole('heading', { name: 'BestDrive a Barum' })).toBeVisible();
	await expect(page.getByText('Věrnostní karta BestDrive')).toBeVisible();
	// Kotva pro přesměrování #galerie ze staré jednostránky.
	await expect(page.locator('main #galerie')).toHaveText('Galerie');
});

test('Sociální sítě z Nastavení jsou v patičce', async ({ page }) => {
	const touched = ['pneukarnik_social_facebook', 'pneukarnik_social_instagram', 'pneukarnik_social_google'];
	const original = Object.fromEntries(touched.map((name) => [name, getOption(name)]));
	setOptions({ pneukarnik_social_facebook: 'https://www.facebook.com/pneukarnik', pneukarnik_social_instagram: '', pneukarnik_social_google: '' });
	try {
		await page.goto('/kontakt/');

		const social = page.getByRole('list', { name: 'Sociální sítě' });
		await expect(social.getByRole('link')).toHaveText(['Facebook']);
		await expect(social.getByRole('link', { name: 'Facebook' })).toHaveAttribute('href', 'https://www.facebook.com/pneukarnik');

		setOptions({ pneukarnik_social_facebook: '' });
		await page.reload();
		await expect(page.getByRole('list', { name: 'Sociální sítě' })).toHaveCount(0);
		await expect(page.getByText('Sledujte nás')).toHaveCount(0);
	} finally {
		setOptions(original);
	}
});

test('Průvodce je v patičce a jeho odkaz otevře rezervaci s předvybranou Službou', async ({ page }) => {
	test.slow(); // Služba a Průvodce přes administraci.
	await login(page);
	const service = uniqueTitle('Přezutí průvodce');
	await publishService(page, { title: service, category: 'Pneuservis', perex: 'Sezónní přezutí.', price: 600, duration: 60, bookable: true });
	const guide = uniqueTitle('Kdy přezout');
	await publishGuide(page, { title: guide, perex: 'Kdy je ten správný čas.', text: '<h2>Zimní pneumatiky</h2><p>Pod 7 °C.</p>', service });

	await page.goto('/');
	await page.locator('.site-footer').getByRole('link', { name: guide }).click();

	await expect(page).toHaveURL(/\/pruvodce\/e2e-kdy-prezout-\d+\/$/);
	await expect(page.getByRole('heading', { level: 1 })).toHaveText(guide);
	await expect(page.getByText('Kdy je ten správný čas.')).toBeVisible();
	await expect(page.getByRole('heading', { name: 'Zimní pneumatiky' })).toBeVisible();
	await expect(page.getByRole('heading', { name: `Objednejte se: ${service}` })).toBeVisible();

	await page.getByRole('main').getByRole('link', { name: 'Rezervovat' }).click();

	await expect(page).toHaveURL(/\/rezervace\/\?sluzba=e2e-prezuti-pruvodce-\d+$/);
	await expect(page.locator('#rez-sluzba-1 option:checked')).toHaveText(`${service} (600\u00a0Kč)`);
});
