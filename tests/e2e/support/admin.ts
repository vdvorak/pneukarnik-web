import { expect, type Page } from '@playwright/test';

/** Prefix názvů obsahu z testů, global-teardown je podle něj smaže. */
export const E2E_PREFIX = 'E2E ';

export function uniqueTitle(name: string): string {
	return `${E2E_PREFIX}${name} ${Date.now()}${Math.floor(Math.random() * 1000)}`;
}

export async function login(page: Page): Promise<void> {
	await page.goto('/wp-login.php');
	await page.fill('#user_login', 'admin');
	await page.fill('#user_pass', 'admin');
	await page.click('#wp-submit');
	await page.waitForURL(/\/wp-admin\//);
}

export type ServiceFields = {
	title: string;
	category?: 'Pneuservis' | 'Autoservis';
	perex?: string;
	includes?: string[];
	process?: string;
	durationText?: string;
	price?: number;
	priceFrom?: boolean;
	priceByVehicle?: boolean;
	priceNote?: string;
	bring?: string[];
	faq?: { question: string; answer: string }[];
	related?: string[];
	duration?: number;
	bookable?: boolean;
	order?: number;
};

/** Vyplní formulář Služby v administraci a klikne na Publikovat. */
export async function publishService(page: Page, s: ServiceFields): Promise<void> {
	await page.goto('/wp-admin/post-new.php?post_type=pneukarnik_service');
	await page.fill('#title', s.title);
	if (s.category) await page.getByLabel('Kategorie *').selectOption({ label: s.category });
	if (s.perex) await page.getByLabel('Perex *').fill(s.perex);
	if (s.includes) await page.getByLabel('Co zahrnuje').fill(s.includes.join('\n'));
	if (s.process) await page.getByLabel('Jak to probíhá').fill(s.process);
	if (s.durationText) await page.getByLabel('Jak dlouho to trvá').fill(s.durationText);
	if (s.price !== undefined) await page.getByLabel('Cena (Kč) *').fill(String(s.price));
	if (s.priceFrom) await page.getByLabel('zobrazit jako „od“').check();
	if (s.priceByVehicle) await page.getByLabel('cena dle vozu (místo ceny)').check();
	if (s.priceNote) await page.getByLabel('Co cena zahrnuje').fill(s.priceNote);
	if (s.bring) await page.getByLabel('Co si vzít s sebou').fill(s.bring.join('\n'));
	for (const [i, item] of (s.faq ?? []).entries()) {
		await page.locator(`input[name="_service_faq[${i}][question]"]`).fill(item.question);
		await page.locator(`textarea[name="_service_faq[${i}][answer]"]`).fill(item.answer);
	}
	for (const title of s.related ?? []) await page.getByLabel(title).check();
	if (s.duration !== undefined) await page.getByLabel('Délka (minuty) *').fill(String(s.duration));
	if (s.bookable) await page.getByLabel(/Rezervovatelná online/).check();
	if (s.order !== undefined) await page.locator('#menu_order').fill(String(s.order));
	// Po opuštění názvu WordPress automaticky uloží koncept a mezitím zablokuje Publikovat.
	await expect(page.locator('#edit-slug-box')).not.toBeEmpty();
	await expect(page.locator('#publish')).not.toHaveClass(/disabled/);
	await Promise.all([page.waitForURL(/\/post\.php\?post=\d+&action=edit/), page.click('#publish')]);
}
