import { expect, test, type APIRequestContext } from '@playwright/test';
import { addClosedDay, E2E_PREFIX, login, pickDay, pickTime, publishService, saveAutumnSeason, saveBookingSettings, uniqueTitle, upcomingWeekday } from './support/admin';
import { waitForMail } from './support/mailpit';
import { createGuide, createService, deletePosts, setPostStatus } from './support/wp';

// Dílna je jedna, testy si tedy nesmí brát Termíny navzájem.
test.describe.configure({ mode: 'serial' });

const serviceTitle = uniqueTitle('Přezutí rezervace');
const extraTitle = uniqueTitle('Vyvážení rezervace');
let serviceId = 0;
let extraId = 0;

const customer = {
	name: 'E2E Zákazník',
	phone: '603 123 456',
	email: 'e2e-zakaznik@example.test',
	plate: '1AB 2345',
};

async function bookViaApi(request: APIRequestContext, date: string, time: string) {
	return request.post('/wp-json/pneukarnik/v1/bookings', {
		data: { service_ids: [serviceId], date, time, ...customer, name: 'E2E Konkurent', consent_gdpr: true },
	});
}

test.beforeAll(async ({ browser, request }) => {
	const page = await browser.newPage();
	await login(page);
	await saveBookingSettings(page);
	await publishService(page, {
		title: serviceTitle,
		category: 'Pneuservis',
		perex: 'Přezutí pro test rezervace.',
		price: 600,
		duration: 60,
		bookable: true,
	});
	await publishService(page, {
		title: extraTitle,
		category: 'Pneuservis',
		perex: 'Vyvážení pro test rezervace.',
		price: 200,
		duration: 30,
		bookable: true,
		askStoredWheels: true,
	});
	await page.close();

	const services: { id: number; name: string }[] = await (await request.get('/wp-json/pneukarnik/v1/services')).json();
	serviceId = services.find((s) => s.name === serviceTitle)?.id ?? 0;
	extraId = services.find((s) => s.name === extraTitle)?.id ?? 0;
	expect(serviceId).toBeGreaterThan(0);
	expect(extraId).toBeGreaterThan(0);
});

test('Zákazník si z detailu Služby zarezervuje Termín a uvidí potvrzení', async ({ page }) => {
	const date = upcomingWeekday(0);

	await page.goto('/pneuservis/');
	await page.getByRole('link', { name: serviceTitle }).click();
	await page.getByRole('main').getByRole('link', { name: 'Rezervovat' }).click();

	await expect(page.getByLabel('Služba')).toHaveValue(String(serviceId));
	await pickDay(page, date);
	await pickTime(page, '8:00–9:00');
	await page.getByLabel('Jméno nebo firma').fill(customer.name);
	await page.getByLabel('Telefon').fill(customer.phone);
	await page.getByLabel('E‑mail').fill(customer.email);
	await page.getByLabel('SPZ').fill(customer.plate);
	await page.getByLabel('Značka a model').fill('Škoda Fabia');
	await page.getByLabel(/Souhlasím se zpracováním/).check();
	await page.getByLabel(/Připomeňte mi před každou sezónou/).check();
	const [sent] = await Promise.all([page.waitForRequest(/\/pneukarnik\/v1\/bookings$/), page.getByRole('button', { name: 'Rezervovat' }).click()]);

	expect(sent.postDataJSON()).toMatchObject({ consent_gdpr: true, consent_reminder: true });
	await expect(page).toHaveURL(/\/rezervace\/potvrzeni\/\?r=[0-9a-f]{64}$/);
	await expect(page.getByRole('heading', { level: 1 })).toHaveText('Rezervace přijata');
	await expect(page.locator('dd').filter({ hasText: serviceTitle })).toBeVisible();
	await expect(page.locator('dd').filter({ hasText: '1AB2345' })).toBeVisible();
	await expect(page.locator('dd').filter({ hasText: /v 8:00$/ })).toBeVisible();
	for (const secret of ['E2E', 'example.test', '603']) {
		expect(page.url()).not.toContain(secret);
	}
	// Celá rezervace proběhne bez cookies, web proto nepotřebuje cookie lištu.
	expect(await page.context().cookies()).toEqual([]);
});

test('Zákazník přidá další Službu a Termín trvá součet Délek', async ({ page }) => {
	await page.goto('/rezervace/');
	await page.getByLabel('Služba', { exact: true }).selectOption(String(serviceId));
	await page.getByRole('button', { name: '+ přidat další službu' }).click();
	const extra = page.getByLabel('Další služba');
	await expect(extra.locator(`option[value="${serviceId}"]`)).toBeDisabled();
	await extra.selectOption(String(extraId));
	await pickDay(page, upcomingWeekday(4));
	await pickTime(page, '8:00–9:30');
	await page.getByLabel('Jméno nebo firma').fill(customer.name);
	await page.getByLabel('Telefon').fill(customer.phone);
	await page.getByLabel('E‑mail').fill(customer.email);
	await page.getByLabel('SPZ').fill(customer.plate);
	await page.getByLabel(/Souhlasím se zpracováním/).check();
	await page.getByRole('button', { name: 'Rezervovat' }).click();

	await expect(page).toHaveURL(/\/rezervace\/potvrzeni\/\?r=[0-9a-f]{64}$/);
	await expect(page.locator('dt').filter({ hasText: 'Služby' })).toBeVisible();
	await expect(page.locator('dd').filter({ hasText: serviceTitle })).toBeVisible();
	await expect(page.locator('dd').filter({ hasText: extraTitle })).toBeVisible();
});

test('Formulář bez údajů ukáže chyby u polí', async ({ page }) => {
	await page.goto(`/rezervace/`);
	await page.getByLabel('Služba').selectOption(String(serviceId));
	await pickDay(page, upcomingWeekday(1));
	await pickTime(page, '9:00–10:00');
	await page.getByRole('button', { name: 'Rezervovat' }).click();

	await expect(page.getByText('Vyplňte jméno nebo firmu.')).toBeVisible();
	await expect(page.getByText('Bez souhlasu nemůžeme rezervaci přijmout.')).toBeVisible();
	await expect(page).toHaveURL(/\/rezervace\/$/);
});

test('Den a Termín jdou vybrat klávesnicí a souhrn ukáže Služby s Délkou a Termín', async ({ page }) => {
	const date = upcomingWeekday(13);
	await page.goto('/rezervace/');
	const summary = page.locator('.souhrn--bok');
	await expect(summary).toContainText('Zatím není vybraná žádná služba.');
	await expect(page.getByText('Objednáme vás i telefonicky, Po–Pá 8:00–12:00, 13:00–17:00.')).toBeVisible();

	await page.getByLabel('Služba', { exact: true }).selectOption(String(serviceId));
	await page.getByRole('button', { name: '+ přidat další službu' }).click();
	await page.getByLabel('Další služba').selectOption(String(extraId));
	await expect(summary.getByText(serviceTitle)).toBeVisible();
	await expect(summary.locator('[data-souhrn-delka]')).toHaveText('90 min');

	const day = page.locator(`[data-date="${date}"]`);
	while (!(await day.isVisible())) {
		await page.getByRole('button', { name: 'Další měsíc' }).press('Enter');
	}
	await expect(day).toBeEnabled();
	await day.focus();
	await page.keyboard.press('Enter');
	await expect(day).toHaveAttribute('aria-pressed', 'true');
	const slot = page.getByRole('button', { name: '8:00–9:30', exact: true });
	await slot.focus();
	await page.keyboard.press('Space');
	await expect(slot).toHaveAttribute('aria-pressed', 'true');
	await expect(summary.locator('[data-souhrn-termin]')).toHaveText(new RegExp(`^\\S+ ${Number(date.slice(8))}\\. ${Number(date.slice(5, 7))}\\. ${date.slice(0, 4)} v 8:00$`));
});

test('Na mobilu se kalendář vejde bez vodorovného posouvání a souhrn je nad tlačítkem', async ({ page }) => {
	await page.setViewportSize({ width: 375, height: 700 });
	await page.goto('/rezervace/');
	await page.getByLabel('Služba', { exact: true }).selectOption(String(serviceId));
	await expect(page.locator(`[data-date]:enabled`).first()).toBeVisible();

	expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(375);
	await expect(page.locator('.souhrn--bok')).toBeHidden();
	const summary = page.locator('.souhrn--mobil');
	await expect(summary.getByText(serviceTitle)).toBeVisible();
	const summaryBox = await summary.boundingBox();
	const buttonBox = await page.getByRole('button', { name: 'Rezervovat' }).boundingBox();
	expect(summaryBox!.y).toBeLessThan(buttonBox!.y);
});

test('Bez JavaScriptu je místo formuláře výzva zavolat', async ({ browser }) => {
	const context = await browser.newContext({ javaScriptEnabled: false });
	const page = await context.newPage();
	await page.goto('/rezervace/');

	await expect(page.getByText(/^Rezervace potřebuje zapnutý JavaScript/)).toBeVisible();
	await expect(page.getByRole('main').getByRole('link', { name: /^Zavolat / })).toHaveAttribute('href', /^tel:/);
	await expect(page.locator('#rezervace-form')).toBeHidden();
	await context.close();
});

test('Když Termín mezitím někdo obsadí, formulář to řekne a nabídne zbylé Termíny', async ({ page, request }) => {
	const date = upcomingWeekday(2);
	await page.goto(`/rezervace/`);
	await page.getByLabel('Služba').selectOption(String(serviceId));
	await pickDay(page, date);
	await pickTime(page, '10:00–11:00');
	await page.getByLabel('Jméno nebo firma').fill(customer.name);
	await page.getByLabel('Telefon').fill(customer.phone);
	await page.getByLabel('E‑mail').fill(customer.email);
	await page.getByLabel('SPZ').fill(customer.plate);
	await page.getByLabel(/Souhlasím se zpracováním/).check();

	expect((await bookViaApi(request, date, '10:00')).status()).toBe(201);
	await page.getByRole('button', { name: 'Rezervovat' }).click();

	await expect(page.getByRole('alert')).toContainText('si mezitím rezervoval někdo jiný');
	await expect(page.getByRole('button', { name: '10:00–11:00', exact: true })).toHaveCount(0);
	await expect(page.getByRole('button', { name: '9:00–10:00', exact: true })).toBeVisible();
});

test('Ze souběžných požadavků o stejný Termín uspěje právě jeden', async ({ request }) => {
	const date = upcomingWeekday(3);

	const responses = await Promise.all(Array.from({ length: 8 }, () => bookViaApi(request, date, '13:00')));
	const statuses = responses.map((r) => r.status()).sort();

	expect(statuses).toEqual([201, 409, 409, 409, 409, 409, 409, 409]);
});

test('Den s Výjimkou „zavřeno“ je v kalendáři zašedlý', async ({ page }) => {
	const closed = upcomingWeekday(5);
	const open = upcomingWeekday(6);
	await login(page);
	await addClosedDay(page, closed, `${E2E_PREFIX}dovolená`);

	await page.goto('/rezervace/');
	await page.getByLabel('Služba', { exact: true }).selectOption(String(serviceId));
	while (!(await page.locator(`[data-date="${closed}"]`).isVisible())) {
		await page.getByRole('button', { name: 'Další měsíc' }).click();
	}

	await expect(page.locator(`[data-date="${closed}"]`)).toBeDisabled();
	if (open.slice(0, 7) === closed.slice(0, 7)) {
		await expect(page.locator(`[data-date="${open}"]`)).toBeEnabled();
	}
	await expect(page.getByText('Zašedlé dny nemají volný termín.')).toBeVisible();
});

test('V Sezóně jde online jen sezónní Služba a leasing až od leasingového data', async ({ page }) => {
	const first = upcomingWeekday(8);
	const leasingFrom = upcomingWeekday(9);
	const seasonalTitle = uniqueTitle('Sezónní přezutí');
	/** Přejde v kalendáři na měsíc dne. */
	const showMonth = async (date: string) => {
		while (!(await page.locator(`[data-date="${date}"]`).isVisible())) {
			await page.getByRole('button', { name: 'Další měsíc' }).click();
		}
	};

	await login(page);
	await publishService(page, {
		title: seasonalTitle,
		category: 'Pneuservis',
		perex: 'Sezónní přezutí pro test.',
		price: 600,
		duration: 60,
		bookable: true,
		seasonal: true,
		askStoredWheels: true,
	});
	await saveAutumnSeason(page, { from: first, to: leasingFrom, leasingFrom });
	try {
		await page.goto('/rezervace/');
		await page.getByLabel('Služba', { exact: true }).selectOption(String(serviceId));
		await showMonth(first);
		await expect(page.locator(`[data-date="${first}"]`)).toBeDisabled();
		await expect(page.getByText(/je sezóna přezouvání a online jde objednat jen přezutí/)).toBeVisible();
		await expect(page.getByLabel('Kola mám uskladněná u vás')).toBeHidden();

		await page.getByLabel('Služba', { exact: true }).selectOption({ label: `${seasonalTitle} (600\u00a0Kč)` }); // Výběr ukazuje i cenu.
		await expect(page.locator(`[data-date="${first}"]`)).toBeEnabled();
		await page.getByLabel('Vozidlo je na leasing').check();
		await expect(page.locator(`[data-date="${first}"]`)).toBeDisabled();
		await expect(page.getByText(/Vozidla na leasing objednáváme/)).toBeVisible();

		await pickDay(page, leasingFrom);
		await pickTime(page, '8:00–9:00');
		await page.getByLabel('Kola mám uskladněná u vás').check();
		await page.getByLabel('Leasingová společnost').fill('E2E Leasing');
		await page.getByLabel('Jméno nebo firma').fill(customer.name);
		await page.getByLabel('Telefon').fill(customer.phone);
		await page.getByLabel('E‑mail').fill(customer.email);
		await page.getByLabel('SPZ').fill(customer.plate);
		await page.getByLabel(/Souhlasím se zpracováním/).check();
		await page.getByRole('button', { name: 'Rezervovat' }).click();

		await expect(page).toHaveURL(/\/rezervace\/potvrzeni\/\?r=[0-9a-f]{64}$/);
		await expect(page.locator('dd').filter({ hasText: 'E2E Leasing' })).toBeVisible();
		await expect(page.locator('dd').filter({ hasText: 'uskladněná u nás' })).toBeVisible();
	} finally {
		await saveAutumnSeason(page, null);
	}
});

test('Zákazník zruší Rezervaci odkazem z e‑mailu a Termín se uvolní', async ({ page, request }) => {
	const date = upcomingWeekday(10);
	const email = `e2e-zruseni-${Date.now()}@example.test`;
	const booking = await request.post('/wp-json/pneukarnik/v1/bookings', {
		data: { service_ids: [serviceId], date, time: '11:00', ...customer, name: 'E2E Rušitel', email, consent_gdpr: true },
	});
	expect(booking.status()).toBe(201);

	const mail = await waitForMail(request, email, /^Potvrzení rezervace/);
	const link = mail.html.match(/href="([^"]*\/rezervace\/zruseni\/\?r=[0-9a-f]{64})"/)?.[1] ?? '';
	expect(link).not.toBe('');
	expect(mail.text).toContain(link);

	await page.goto(link);
	await expect(page.getByRole('heading', { level: 1 })).toHaveText('Zrušení rezervace');
	await expect(page.locator('dd').filter({ hasText: serviceTitle })).toBeVisible();
	await expect(page.getByText(/^Rezervaci můžete zrušit nejpozději /)).toBeVisible();
	await page.getByRole('button', { name: 'Zrušit rezervaci' }).click();
	await expect(page.getByRole('status')).toContainText('Rezervace je zrušená');

	const slots = await (await request.get('/wp-json/pneukarnik/v1/slots', { params: { 'service_ids[]': serviceId, date } })).json();
	expect(slots.slots.map((slot: { time_start: string }) => slot.time_start)).toContain('11:00');
	await waitForMail(request, email, /je zrušená$/);

	await page.goto(link);
	await expect(page.getByRole('status')).toHaveText('Tato rezervace už je zrušená.');
	await expect(page.getByRole('button', { name: 'Zrušit rezervaci' })).toHaveCount(0);

	// Změna Termínu = Zrušení + nová Rezervace s předvyplněnými údaji.
	await page.getByRole('link', { name: 'Objednat znovu' }).click();
	await expect(page.getByLabel('Jméno nebo firma')).toHaveValue('E2E Rušitel');
	await expect(page.getByLabel('E‑mail')).toHaveValue(email);
});

test('Neplatný odkaz pro zrušení nic neukáže a nabídne telefon a novou rezervaci', async ({ page }) => {
	await page.goto('/rezervace/zruseni/?r=' + '0'.repeat(64));

	await expect(page.getByRole('heading', { level: 1 })).toHaveText('Zrušení rezervace');
	await expect(page.getByRole('alert')).toHaveText('Odkaz pro zrušení je neplatný nebo už vypršel.');
	await expect(page.locator('main dl')).toHaveCount(0);
	await expect(page.getByRole('main').getByRole('link', { name: /^Zavolat/ })).toHaveAttribute('href', /^tel:/);
	await expect(page.getByRole('main').getByRole('link', { name: 'Objednat znovu' })).toBeVisible();
	await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content', /noindex/);
});

test('Údaje se zapamatují jen se zaškrtnutím a jdou smazat', async ({ page }) => {
	const date = upcomingWeekday(11);
	const book = async (time: string, remember: boolean) => {
		await page.goto('/rezervace/');
		await page.getByLabel('Služba', { exact: true }).selectOption(String(serviceId));
		await pickDay(page, date);
		await pickTime(page, time);
		await page.getByLabel('Jméno nebo firma').fill(customer.name);
		await page.getByLabel('Telefon').fill(customer.phone);
		await page.getByLabel('E‑mail').fill(customer.email);
		await page.getByLabel('SPZ').fill(customer.plate);
		await page.getByLabel('Značka a model').fill('Škoda Fabia');
		await page.getByLabel('Zapamatovat údaje na tomto zařízení').setChecked(remember);
		await page.getByLabel(/Souhlasím se zpracováním/).check();
		await page.getByRole('button', { name: 'Rezervovat' }).click();
		await expect(page).toHaveURL(/\/rezervace\/potvrzeni\//);
	};

	await book('8:00–9:00', false);
	await page.goto('/rezervace/');
	await expect(page.getByLabel('Jméno nebo firma')).toHaveValue('');
	expect(await page.evaluate(() => window.localStorage.length)).toBe(0);

	await book('9:00–10:00', true);
	await page.goto('/rezervace/');
	await expect(page.getByLabel('Jméno nebo firma')).toHaveValue(customer.name);
	await expect(page.getByLabel('E‑mail')).toHaveValue(customer.email);
	await expect(page.getByLabel('Značka a model')).toHaveValue('Škoda Fabia');
	await expect(page.getByLabel('Zapamatovat údaje na tomto zařízení')).toBeChecked();

	await page.getByRole('button', { name: 'Smazat uložené údaje' }).click();
	await expect(page.getByLabel('Jméno nebo firma')).toHaveValue('');
	await page.reload();
	await expect(page.getByLabel('Jméno nebo firma')).toHaveValue('');
	await expect(page.getByLabel('Zapamatovat údaje na tomto zařízení')).not.toBeChecked();
});

/** Rezervace přes API a odkaz „Objednat znovu“ z jejího potvrzovacího e‑mailu. */
async function reorderLink(request: APIRequestContext, data: Record<string, unknown>): Promise<string> {
	const email = `e2e-znovu-${Date.now()}@example.test`;
	const booking = await request.post('/wp-json/pneukarnik/v1/bookings', { data: { ...customer, email, consent_gdpr: true, ...data } });
	expect(booking.status()).toBe(201);
	const mail = await waitForMail(request, email, /^Potvrzení rezervace/);
	const link = mail.html.match(/href="([^"]*\/rezervace\/\?znovu=[^"]+)"/)?.[1] ?? '';
	expect(link).not.toBe('');
	return link.replaceAll('&amp;', '&');
}

test('Odkaz „Objednat znovu“ z e‑mailu předvyplní kontaktní údaje, Služby a uskladněná kola', async ({ page, request }) => {
	const link = await reorderLink(request, {
		service_ids: [extraId, serviceId],
		date: upcomingWeekday(12),
		time: '08:00',
		name: 'E2E Stálý zákazník',
		vehicle: 'Škoda Octavia',
		note: 'Tajná poznámka',
		stored_wheels: true,
	});

	await page.goto(link);
	await expect(page.getByText('Služby a údaje jsme předvyplnili podle vaší předchozí rezervace. Vyberte prosím nový termín.')).toBeVisible();
	await expect(page.getByLabel('Služba', { exact: true })).toHaveValue(String(extraId));
	await expect(page.getByLabel('Další služba')).toHaveValue(String(serviceId));
	await expect(page.getByLabel('Kola mám uskladněná u vás')).toBeChecked();
	await expect(page.getByLabel('Jméno nebo firma')).toHaveValue('E2E Stálý zákazník');
	await expect(page.getByLabel('E‑mail')).toHaveValue(/^e2e-znovu-/);
	await expect(page.getByLabel('SPZ')).toHaveValue('1AB2345');
	await expect(page.getByLabel('Značka a model')).toHaveValue('Škoda Octavia');
	await expect(page.getByLabel('Poznámka')).toHaveValue('');
	await expect(page.locator('[data-souhrn-delka]').first()).toHaveText('90 min');
	await expect(page.locator('[data-souhrn-termin]').first()).toHaveText('—');
	await expect(page.locator('#kalendar-dny [aria-pressed="true"]')).toHaveCount(0);
	expect(page.url()).not.toContain('znovu');
});

test('Služba, kterou už nejde objednat online, se z „Objednat znovu“ nepředvyplní', async ({ page, request }) => {
	const date = upcomingWeekday(14);
	const retired = createService(uniqueTitle('Geometrie znovu'), `e2e-geometrie-znovu-${Date.now()}`, 'pneuservis', { _service_bookable: '1' });
	try {
		const link = await reorderLink(request, { service_ids: [retired, serviceId], date, time: '08:00' });
		setPostStatus(retired, 'draft');

		await page.goto(link);
		await expect(page.getByText(/^Služby a údaje jsme předvyplnili/)).toBeVisible();
		await expect(page.getByLabel('Služba', { exact: true })).toHaveValue(String(serviceId));
		await expect(page.getByLabel('Další služba')).toHaveCount(0);
		await pickDay(page, upcomingWeekday(7)); // Formulář dál funguje: zbylá Služba má volné Termíny.
		await expect(page.locator('#terminy button').first()).toBeVisible();
	} finally {
		deletePosts([retired]);
	}
});

test('Pod Službou s Průvodcem je odkaz na něj, otevře se v novém panelu a vyplněné údaje zůstanou', async ({ page, context }) => {
	const guided = createService(uniqueTitle('Přezutí s průvodcem'), `e2e-prezuti-s-pruvodcem-${Date.now()}`, 'pneuservis', { _service_bookable: '1' });
	const guide = uniqueTitle('Kdy přezout');
	const guideId = createGuide(guide, guided);
	try {
		await page.goto('/rezervace/');
		const rows = page.locator('.rezervace__sluzba');
		const guideLink = (row: number) => rows.nth(row).getByRole('link', { name: `Přečtěte si: ${guide} (otevře se v novém panelu)` });
		await expect(page.getByRole('link', { name: /^Přečtěte si/ })).toHaveCount(0);

		await page.getByLabel('Služba', { exact: true }).selectOption(String(guided));
		await expect(guideLink(0)).toBeVisible();
		await expect(guideLink(0)).toHaveAttribute('target', '_blank');
		await page.getByLabel('Jméno nebo firma').fill(customer.name);
		const [tab] = await Promise.all([context.waitForEvent('page'), guideLink(0).click()]);
		await expect(tab).toHaveURL(/\/pruvodce\/e2e-kdy-prezout-\d+\/$/);
		await expect(tab.getByRole('heading', { level: 1 })).toHaveText(guide);
		await tab.close();
		await expect(page.getByLabel('Jméno nebo firma')).toHaveValue(customer.name);
		await expect(page.getByLabel('Služba', { exact: true })).toHaveValue(String(guided));

		await page.getByLabel('Služba', { exact: true }).selectOption(String(serviceId));
		await expect(page.getByRole('link', { name: /^Přečtěte si/ })).toHaveCount(0);

		// I v přidané řádce.
		await page.getByRole('button', { name: '+ přidat další službu' }).click();
		await page.getByLabel('Další služba').selectOption(String(guided));
		await expect(guideLink(1)).toBeVisible();
		await page.getByLabel('Další služba').selectOption('');
		await expect(page.getByRole('link', { name: /^Přečtěte si/ })).toHaveCount(0);
	} finally {
		deletePosts([guideId, guided]);
	}
});
