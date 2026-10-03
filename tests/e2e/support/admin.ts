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

const WEEKDAYS = ['mon', 'tue', 'wed', 'thu', 'fri'] as const;

/** Pracovní doba Po–Pá 8–12 a 13–17, víkend zavřeno, a pravidla Termínů přes Nastavení pluginu. */
export async function saveBookingSettings(page: Page): Promise<void> {
	await page.goto('/wp-admin/admin.php?page=pneukarnik-settings');
	for (const day of ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun']) {
		const open = page.locator(`input[name="day_open[${day}]"]`);
		if (!(WEEKDAYS as readonly string[]).includes(day)) {
			await open.uncheck();
			continue;
		}
		await open.check();
		await page.locator(`input[name="day_from1[${day}]"]`).fill('08:00');
		await page.locator(`input[name="day_to1[${day}]"]`).fill('12:00');
		await page.locator(`input[name="day_from2[${day}]"]`).fill('13:00');
		await page.locator(`input[name="day_to2[${day}]"]`).fill('17:00');
	}
	await page.getByLabel('Krok mřížky Termínů (min)').fill('30');
	await page.getByLabel('Předstih pro dnešek (min)').fill('60');
	await page.getByLabel('Horizont (dny dopředu)').fill('60');
	await page.locator('input[name="rate_limit"]').fill('100');
	await page.getByRole('button', { name: 'Uložit nastavení' }).click();
	await expect(page.getByText('Nastavení uložena.')).toBeVisible();
}

/** n-tý pracovní den (Po–Pá) počínaje pozítřkem, jako YYYY-MM-DD v místním čase. */
export function upcomingWeekday(n: number): string {
	const day = new Date();
	day.setDate(day.getDate() + 2);
	let found = -1;
	for (;;) {
		if (day.getDay() !== 0 && day.getDay() !== 6 && ++found === n) break;
		day.setDate(day.getDate() + 1);
	}
	const pad = (value: number) => String(value).padStart(2, '0');
	return `${day.getFullYear()}-${pad(day.getMonth() + 1)}-${pad(day.getDate())}`;
}
