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
	seasonal?: boolean;
	askStoredWheels?: boolean;
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
	if (s.seasonal) await page.getByLabel(/Sezónní \(v Sezóně/).check();
	if (s.askStoredWheels) await page.getByLabel(/Ptát se na uskladněná kola/).check();
	if (s.order !== undefined) await page.locator('#menu_order').fill(String(s.order));
	// Po opuštění názvu WordPress automaticky uloží koncept a mezitím zablokuje Publikovat.
	await expect(page.locator('#edit-slug-box')).not.toBeEmpty();
	await expect(page.locator('#publish')).not.toHaveClass(/disabled/);
	await Promise.all([page.waitForURL(/\/post\.php\?post=\d+&action=edit/), page.click('#publish')]);
}

export type PromotionFields = {
	title: string;
	service: string;
	price: number;
	description?: string;
	/** YYYY-MM-DD */
	from: string;
	/** YYYY-MM-DD */
	to: string;
};

/** Vyplní formulář Akce v administraci a klikne na Publikovat. */
export async function publishPromotion(page: Page, a: PromotionFields): Promise<void> {
	await page.goto('/wp-admin/post-new.php?post_type=pneukarnik_promotion');
	await page.getByLabel('Služba *').selectOption({ label: a.service });
	await page.getByLabel('Akční cena (Kč) *').fill(String(a.price));
	if (a.description) await page.getByLabel('Popis').fill(a.description);
	await page.getByLabel('Platí od *').fill(a.from);
	await page.getByLabel('Platí do *').fill(a.to);
	// Akce nemá adresu, takže WordPress po opuštění názvu spustí automatické uložení a zablokuje Publikovat.
	// Když název opustí kliknutím na Publikovat, automatické uložení zruší, proto název až nakonec.
	await page.fill('#title', a.title);
	await Promise.all([page.waitForURL(/\/post\.php\?post=\d+&action=edit/), page.click('#publish')]);
	await expect(page.getByText('Akce není zveřejněná')).toHaveCount(0);
}

/** Den posunutý o `days` od dneška jako YYYY-MM-DD v místním čase. */
export function dayFromToday(days: number): string {
	const day = new Date();
	day.setDate(day.getDate() + days);
	return `${day.getFullYear()}-${pad(day.getMonth() + 1)}-${pad(day.getDate())}`;
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
	await page.getByLabel('Limit Rezervací z jedné IP za hodinu').fill('100');
	await page.getByLabel('Limit pokusů o Zrušení z jedné IP za hodinu').fill('100');
	await page.getByRole('button', { name: 'Uložit nastavení' }).click();
	await expect(page.getByText('Nastavení uložena.')).toBeVisible();
}

/** YYYY-MM-DD → „15. 3.“, jak se Sezóny zadávají v Nastavení. */
const dayMonth = (date: string) => {
	const [, month, day] = date.split('-').map(Number);
	return `${day}. ${month}.`;
};

/** Podzimní Sezóna v Nastavení (od, do, leasing od jako YYYY-MM-DD), null = žádná Sezóna. */
export async function saveAutumnSeason(page: Page, season: { from: string; to: string; leasingFrom: string } | null): Promise<void> {
	await page.goto('/wp-admin/admin.php?page=pneukarnik-settings');
	for (const name of ['spring', 'autumn']) {
		for (const field of ['from', 'to', 'leasing_from']) await page.locator(`input[name="season[${name}][${field}]"]`).fill('');
	}
	if (season) {
		await page.getByLabel('Podzimní od').fill(dayMonth(season.from));
		await page.getByLabel('Podzimní do').fill(dayMonth(season.to));
		await page.getByLabel('Podzimní leasing od').fill(dayMonth(season.leasingFrom));
	}
	await page.getByRole('button', { name: 'Uložit nastavení' }).click();
	await expect(page.getByText('Nastavení uložena.')).toBeVisible();
}

const pad = (value: number) => String(value).padStart(2, '0');

/** Svátky ČR v roce jako MM-DD (Velký pátek a Velikonoční pondělí podle Velikonoc). */
function holidays(year: number): Set<string> {
	const a = year % 19;
	const b = Math.floor(year / 100);
	const c = year % 100;
	const h = (19 * a + b - Math.floor(b / 4) - Math.floor((b - Math.floor((b + 8) / 25) + 1) / 3) + 15) % 30;
	const l = (32 + 2 * (b % 4) + 2 * Math.floor(c / 4) - h - (c % 4)) % 7;
	const m = Math.floor((a + 11 * h + 22 * l) / 451);
	const easter = new Date(year, Math.floor((h + l - 7 * m + 114) / 31) - 1, ((h + l - 7 * m + 114) % 31) + 1);
	const shifted = (days: number) => {
		const day = new Date(easter);
		day.setDate(day.getDate() + days);
		return `${pad(day.getMonth() + 1)}-${pad(day.getDate())}`;
	};
	return new Set(['01-01', shifted(-2), shifted(1), '05-01', '05-08', '07-05', '07-06', '09-28', '10-28', '11-17', '12-24', '12-25', '12-26']);
}

/** n-tý pracovní den (Po–Pá mimo svátky) počínaje pozítřkem, jako YYYY-MM-DD v místním čase. */
export function upcomingWeekday(n: number): string {
	const day = new Date();
	day.setDate(day.getDate() + 2);
	let found = -1;
	for (;;) {
		const workday = day.getDay() !== 0 && day.getDay() !== 6 && !holidays(day.getFullYear()).has(`${pad(day.getMonth() + 1)}-${pad(day.getDate())}`);
		if (workday && ++found === n) break;
		day.setDate(day.getDate() + 1);
	}
	return `${day.getFullYear()}-${pad(day.getMonth() + 1)}-${pad(day.getDate())}`;
}

/** Vybere den v kalendáři rezervačního formuláře, případně přejde na jeho měsíc. */
export async function pickDay(page: Page, date: string): Promise<void> {
	const day = page.locator(`[data-date="${date}"]`);
	while (!(await day.isVisible())) {
		await page.getByRole('button', { name: 'Další měsíc' }).click();
	}
	await day.click();
}

/** Přidá v administraci celodenní Výjimku „zavřeno“ na jeden den. */
export async function addClosedDay(page: Page, date: string, note: string): Promise<void> {
	await page.goto('/wp-admin/admin.php?page=pneukarnik-day-exceptions');
	await page.getByLabel('Od', { exact: true }).fill(date);
	await page.getByLabel('Poznámka').fill(note);
	await page.getByRole('button', { name: 'Přidat Výjimku' }).click();
	await expect(page.getByText('Výjimka uložena.')).toBeVisible();
}
