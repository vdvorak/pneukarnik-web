import { readFileSync } from 'node:fs';
import { expect, test } from '@playwright/test';
import { E2E_PREFIX } from './support/admin';
import { createService, deletePosts } from './support/wp';

/** Řádky tabulek z docs/stare-url.md: [stará adresa, cíl]. */
function table(heading: string): [string, string][] {
	const section = readFileSync('docs/stare-url.md', 'utf8').split(/^## /m).find((s) => s.startsWith(heading)) ?? '';
	return [...section.matchAll(/^\| `([^`]+)` \| `([^`]+)` \|/gm)].map((m) => [m[1], m[2]]);
}

const addresses = table('Adresy');
const anchors = table('Kotvy');

test.describe('Staré URL z docs/stare-url.md', () => {
	test.describe.configure({ mode: 'serial' });

	let services: number[] = [];

	test.beforeAll(() => {
		// Cíle Služeb v seznamu počítají se zveřejněnými Službami s těmito slugy.
		services = [createService(`${E2E_PREFIX}Přezutí pneu`, 'prezuti-pneu', 'pneuservis'), createService(`${E2E_PREFIX}Dekarbonizace`, 'dekarbonizace', 'autoservis')];
	});

	test.afterAll(() => deletePosts(services));

	test('každá stará adresa vrátí 301 na svůj cíl a cíl existuje', async ({ request, baseURL }) => {
		expect(addresses.length).toBeGreaterThan(40);
		for (const [from, to] of addresses) {
			const response = await request.get(from, { maxRedirects: 0 });
			expect(response.status(), from).toBe(301);
			const location = new URL(response.headers().location, baseURL);
			expect(location.pathname + location.search + location.hash, from).toBe(to);

			const target = await request.get(location.href);
			expect(target.status(), `${from} → ${to}`).toBe(200);
		}
	});

	test('kotvy staré jednostránky vedou na nové stránky', async ({ page, baseURL }) => {
		expect(anchors.length).toBeGreaterThan(10);
		for (const [from, to] of anchors) {
			await page.goto('about:blank'); // Ze starého odkazu se přichází odjinud, ne změnou kotvy na Úvodu.
			await page.goto(from);
			await expect(page, from).toHaveURL(new URL(to, baseURL).href);
		}
		await expect(page.locator('#sluzby')).toBeVisible();
	});
});
