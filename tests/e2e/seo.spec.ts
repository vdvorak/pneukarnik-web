import { expect, test, type Page } from '@playwright/test';
import { login, publishService, uniqueTitle } from './support/admin';
import { getOption, setOptions } from './support/wp';

const COMPANY = 'Pneuservis a autoservis Jan Kárník';
const PHONE = '+420 775 565 326';

// Test s Matomem mění volby celého webu a test bez Matoma by ho viděl, proto jeden po druhém.
test.describe.configure({ mode: 'serial' });

const title = uniqueTitle('Geometrie SEO');
let serviceUrl = '';
let editUrl = '';

test.beforeAll(async ({ browser }) => {
	const page = await browser.newPage();
	await login(page);
	await publishService(page, {
		title,
		category: 'Pneuservis',
		perex: 'Seřízení geometrie náprav na laserové stěně.',
		price: 900,
		duration: 60,
		bookable: true,
	});
	editUrl = page.url();
	await page.goto('/pneuservis/');
	serviceUrl = (await page.getByRole('link', { name: title }).getAttribute('href')) ?? '';
	await page.close();
	expect(serviceUrl).toMatch(/\/pneuservis\/e2e-geometrie-seo-\d+\/$/);
});

/** Hodnota meta značky podle name nebo property. */
const meta = (page: Page, key: string) => page.locator(`meta[name="${key}"], meta[property="${key}"]`).getAttribute('content');

/** Strukturovaná data (JSON-LD) stránky. */
async function schemas(page: Page): Promise<Record<string, any>[]> {
	return (await page.locator('script[type="application/ld+json"]').allTextContents()).map((json) => JSON.parse(json));
}

test('Úvod má title, description, Open Graph a LocalBusiness z Nastavení', async ({ page }) => {
	await page.goto('/');

	await expect(page).toHaveTitle(new RegExp(`^${COMPANY}`));
	const description = await meta(page, 'description');
	expect(description).toContain(`zavolejte ${PHONE}`);
	await expect(page.locator('link[rel="canonical"]')).toHaveAttribute('href', /:\d+\/$/);
	expect(await meta(page, 'og:title')).toBe(await page.title());
	expect(await meta(page, 'og:description')).toBe(description);
	expect(await meta(page, 'og:url')).toBe(new URL('/', page.url()).href);
	expect(await meta(page, 'og:type')).toBe('website');
	expect(await meta(page, 'og:site_name')).toBe(COMPANY);

	const [business, ...rest] = await schemas(page);
	expect(rest).toEqual([]);
	expect(business['@context']).toBe('https://schema.org');
	expect(business['@type']).toBe('AutoRepair'); // Podtyp LocalBusiness.
	expect(business.name).toBe(COMPANY);
	expect(business.url).toBe(new URL('/', page.url()).href);
	expect(business.telephone).toBe(PHONE);
});

test('Detail Služby má title a description z polí, Open Graph a Service, Provozovatel je může přepsat', async ({ page, browser }) => {
	test.slow(); // Úprava Služby v administraci, při souběhu všech testů trvá déle.
	const visitor = await browser.newPage();
	await visitor.goto(serviceUrl);

	await expect(visitor).toHaveTitle(`${title} – ${COMPANY}`);
	expect(await meta(visitor, 'description')).toBe('Seřízení geometrie náprav na laserové stěně.');
	await expect(visitor.locator('link[rel="canonical"]')).toHaveAttribute('href', serviceUrl);
	expect(await meta(visitor, 'og:title')).toBe(`${title} – ${COMPANY}`);
	expect(await meta(visitor, 'og:description')).toBe('Seřízení geometrie náprav na laserové stěně.');
	expect(await meta(visitor, 'og:url')).toBe(serviceUrl);
	const [service, ...rest] = await schemas(visitor);
	expect(rest).toEqual([]);
	expect(service).toMatchObject({
		'@context': 'https://schema.org',
		'@type': 'Service',
		name: title,
		description: 'Seřízení geometrie náprav na laserové stěně.',
		url: serviceUrl,
		serviceType: 'Pneuservis',
		offers: { '@type': 'Offer', price: 900, priceCurrency: 'CZK' },
		provider: { '@type': 'AutoRepair', name: COMPANY, telephone: PHONE },
	});

	await login(page);
	await page.goto(editUrl);
	await page.getByLabel('Titulek pro vyhledávače').fill('Geometrie Znojmo – laserové seřízení');
	await page.getByLabel('Popis pro vyhledávače').fill('Seřídíme geometrii do hodiny.');
	await Promise.all([page.waitForURL(/message=1/), page.click('#publish')]);

	await visitor.reload();

	await expect(visitor).toHaveTitle('Geometrie Znojmo – laserové seřízení');
	expect(await meta(visitor, 'description')).toBe('Seřídíme geometrii do hodiny.');
	expect(await meta(visitor, 'og:title')).toBe('Geometrie Znojmo – laserové seřízení');
});

test('Web nenastavuje cookies a Matomo měří bez nich a bez tajných tokenů z adresy', async ({ browser }) => {
	const touched = ['pneukarnik_matomo_url', 'pneukarnik_matomo_site_id'];
	const original = Object.fromEntries(touched.map((name) => [name, getOption(name)]));
	setOptions({ pneukarnik_matomo_url: 'https://matomo.invalid/', pneukarnik_matomo_site_id: '7' });
	try {
		const context = await browser.newContext();
		const page = await context.newPage();
		const matomo: string[] = [];
		// Místo skutečného Matoma jen zachytí frontu příkazů, se kterou by začal.
		await page.route('https://matomo.invalid/**', (route) => {
			matomo.push(route.request().url());
			return route.fulfill({ contentType: 'text/javascript', body: 'window.__matomoQueue = JSON.parse(JSON.stringify(window._paq));' });
		});
		const queue = async () => {
			await page.waitForFunction(() => 'undefined' !== typeof (window as unknown as { __matomoQueue?: unknown }).__matomoQueue);
			return page.evaluate(() => (window as unknown as { __matomoQueue: unknown[][] }).__matomoQueue);
		};

		for (const path of ['/', serviceUrl, '/rezervace/?znovu=1.abcdef']) {
			await page.goto(path);
			const commands = await queue();

			expect(commands[0]).toEqual(['disableCookies']);
			expect(commands).toContainEqual(['setSiteId', '7']);
			expect(commands).toContainEqual(['setTrackerUrl', 'https://matomo.invalid/matomo.php']);
			expect(commands).toContainEqual(['setCustomUrl', new URL(path.replace(/\?znovu=.*$/, ''), page.url()).href]);
			expect(JSON.stringify(commands)).not.toContain('znovu');
			await page.waitForLoadState('load');
			expect(await context.cookies()).toEqual([]);
			expect(await page.evaluate(() => document.cookie)).toBe('');
		}
		expect(matomo).toEqual(Array(3).fill('https://matomo.invalid/matomo.js'));
		await context.close();
	} finally {
		setOptions(original);
	}
});

test('Bez nastaveného Matoma se nic neměří', async ({ page }) => {
	const requests: string[] = [];
	page.on('request', (request) => requests.push(request.url()));

	await page.goto('/');
	await page.waitForLoadState('networkidle');

	expect(requests.filter((url) => /matomo/.test(url))).toEqual([]);
});

test('Sitemapa má stránky, Služby, rozcestníky a rezervaci, ale ne uživatele ani rubriky', async ({ request }) => {
	const index = await (await request.get('/wp-sitemap.xml')).text();
	const sitemaps = [...index.matchAll(/<loc>([^<]+)<\/loc>/g)].map((m) => new URL(m[1]).pathname);

	expect(sitemaps).toContain('/wp-sitemap-posts-page-1.xml');
	expect(sitemaps).toContain('/wp-sitemap-posts-pneukarnik_service-1.xml');
	expect(sitemaps).toContain('/wp-sitemap-pneukarnik-1.xml');
	expect(sitemaps.filter((path) => /users|taxonomies|posts-post-/.test(path))).toEqual([]);

	const services = await (await request.get('/wp-sitemap-posts-pneukarnik_service-1.xml')).text();
	expect(services).toContain(`<loc>${serviceUrl}</loc>`);
	const pages = await (await request.get('/wp-sitemap-pneukarnik-1.xml')).text();
	for (const path of ['/sluzby/', '/pneuservis/', '/autoservis/', '/rezervace/']) {
		expect(pages).toContain(`${path}</loc>`);
	}
});
