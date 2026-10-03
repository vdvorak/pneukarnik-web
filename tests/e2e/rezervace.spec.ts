import { expect, test, type APIRequestContext } from '@playwright/test';
import { addClosedDay, E2E_PREFIX, login, pickDay, publishService, saveAutumnSeason, saveBookingSettings, uniqueTitle, upcomingWeekday } from './support/admin';
import { waitForMail } from './support/mailpit';

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
	await page.getByRole('link', { name: 'Rezervovat' }).click();

	await expect(page.getByLabel('Služba')).toHaveValue(String(serviceId));
	await pickDay(page, date);
	await page.getByLabel('8:00–9:00').check();
	await page.getByLabel('Jméno nebo firma').fill(customer.name);
	await page.getByLabel('Telefon').fill(customer.phone);
	await page.getByLabel('E‑mail').fill(customer.email);
	await page.getByLabel('SPZ').fill(customer.plate);
	await page.getByLabel('Značka a model (nepovinné)').fill('Škoda Fabia');
	await page.getByLabel(/Souhlasím se zpracováním/).check();
	await page.getByRole('button', { name: 'Rezervovat' }).click();

	await expect(page).toHaveURL(/\/rezervace\/potvrzeni\/\?r=[0-9a-f]{64}$/);
	await expect(page.getByRole('heading', { level: 1 })).toHaveText('Rezervace přijata');
	await expect(page.locator('dd').filter({ hasText: serviceTitle })).toBeVisible();
	await expect(page.locator('dd').filter({ hasText: '1AB2345' })).toBeVisible();
	await expect(page.locator('dd').filter({ hasText: /v 8:00$/ })).toBeVisible();
	for (const secret of ['E2E', 'example.test', '603']) {
		expect(page.url()).not.toContain(secret);
	}
});

test('Zákazník přidá další Službu a Termín trvá součet Délek', async ({ page }) => {
	await page.goto('/rezervace/');
	await page.getByLabel('Služba', { exact: true }).selectOption(String(serviceId));
	await page.getByRole('button', { name: '+ přidat další službu' }).click();
	const extra = page.getByLabel('Další služba');
	await expect(extra.locator(`option[value="${serviceId}"]`)).toBeDisabled();
	await extra.selectOption(String(extraId));
	await pickDay(page, upcomingWeekday(4));
	await page.getByLabel('8:00–9:30').check();
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
	await page.getByLabel('9:00–10:00').check();
	await page.getByRole('button', { name: 'Rezervovat' }).click();

	await expect(page.getByText('Vyplňte jméno nebo firmu.')).toBeVisible();
	await expect(page.getByText('Bez souhlasu nemůžeme rezervaci přijmout.')).toBeVisible();
	await expect(page).toHaveURL(/\/rezervace\/$/);
});

test('Když Termín mezitím někdo obsadí, formulář to řekne a nabídne zbylé Termíny', async ({ page, request }) => {
	const date = upcomingWeekday(2);
	await page.goto(`/rezervace/`);
	await page.getByLabel('Služba').selectOption(String(serviceId));
	await pickDay(page, date);
	await page.getByLabel('10:00–11:00').check();
	await page.getByLabel('Jméno nebo firma').fill(customer.name);
	await page.getByLabel('Telefon').fill(customer.phone);
	await page.getByLabel('E‑mail').fill(customer.email);
	await page.getByLabel('SPZ').fill(customer.plate);
	await page.getByLabel(/Souhlasím se zpracováním/).check();

	expect((await bookViaApi(request, date, '10:00')).status()).toBe(201);
	await page.getByRole('button', { name: 'Rezervovat' }).click();

	await expect(page.getByRole('alert')).toContainText('si mezitím rezervoval někdo jiný');
	await expect(page.getByLabel('10:00–11:00')).toHaveCount(0);
	await expect(page.getByLabel('9:00–10:00')).toBeVisible();
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
		await page.getByLabel('Služba', { exact: true }).selectOption({ label: serviceTitle });
		await showMonth(first);
		await expect(page.locator(`[data-date="${first}"]`)).toBeDisabled();
		await expect(page.getByText(/je sezóna přezouvání a online jde objednat jen přezutí/)).toBeVisible();
		await expect(page.getByLabel('Kola mám uskladněná u vás')).toBeHidden();

		await page.getByLabel('Služba', { exact: true }).selectOption({ label: seasonalTitle });
		await expect(page.locator(`[data-date="${first}"]`)).toBeEnabled();
		await page.getByLabel('Vozidlo je na leasing').check();
		await expect(page.locator(`[data-date="${first}"]`)).toBeDisabled();
		await expect(page.getByText(/Vozidla na leasing objednáváme/)).toBeVisible();

		await pickDay(page, leasingFrom);
		await page.getByLabel('8:00–9:00').check();
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
	await page.getByRole('button', { name: 'Zrušit rezervaci' }).click();
	await expect(page.getByRole('alert')).toContainText('Rezervace je zrušená');

	const slots = await (await request.get('/wp-json/pneukarnik/v1/slots', { params: { 'service_ids[]': serviceId, date } })).json();
	expect(slots.slots.map((slot: { time_start: string }) => slot.time_start)).toContain('11:00');
	await waitForMail(request, email, /je zrušená$/);

	await page.goto(link);
	await expect(page.getByRole('alert')).toHaveText('Tato rezervace už je zrušená.');
});
