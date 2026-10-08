import { expect, test } from '@playwright/test';
import { login } from './support/admin';
import { waitForMail } from './support/mailpit';
import { getOption, setJsonOption, setOptions } from './support/wp';

// Testy mění e‑mail Provozovatele a ukládají celý formulář stránky, proto po sobě.
test.describe.configure({ mode: 'default' });

test('Připomínka přezutí se nastavuje na stránce E‑maily Zákazníkům a zkušební odejde Provozovateli', async ({ page, request }) => {
	const days = getOption('pneukarnik_reminder_days');
	const intro = getOption('pneukarnik_email_reminder');
	const contact = getOption('pneukarnik_email');
	const provozovatel = `e2e-provozovatel-${Date.now()}@example.test`;
	const newIntro = `E2E úvod Připomínky ${Date.now()}`;
	setOptions({ pneukarnik_email: provozovatel });
	try {
		await login(page);
		await page.goto('/wp-admin/admin.php?page=pneukarnik-settings');
		await expect(page.getByLabel('Kolik dní před Sezónou')).toHaveCount(0);
		await page.getByRole('link', { name: 'E‑maily Zákazníkům' }).first().click();

		await expect(page.getByRole('heading', { level: 1 })).toHaveText('E‑maily Zákazníkům');
		await expect(page.getByRole('row', { name: /^Žádost o hodnocení \d/ })).toBeVisible();
		await page.getByLabel('Kolik dní před Sezónou').fill('21');
		await page.getByLabel('Úvod e‑mailu').fill(newIntro);
		await page.getByLabel(/Po uložení poslat zkušební Připomínku přezutí/).check();
		await page.getByRole('button', { name: 'Uložit' }).click();

		await expect(page.getByText(`Zkušební Připomínka přezutí odeslaná na ${provozovatel}.`)).toBeVisible();
		await expect(page.getByLabel('Kolik dní před Sezónou')).toHaveValue('21');
		await expect(page.getByLabel('Úvod e‑mailu')).toHaveValue(newIntro);
		const mail = await waitForMail(request, provozovatel, /^\[Zkouška\] Je čas přezout/);
		expect(mail.text).toContain(newIntro);
	} finally {
		if (days) setOptions({ pneukarnik_reminder_days: days });
		else setJsonOption('pneukarnik_reminder_days', null);
		if (intro) setOptions({ pneukarnik_email_reminder: intro });
		else setJsonOption('pneukarnik_email_reminder', null);
		setOptions({ pneukarnik_email: contact });
	}
});

test('Připomínka Termínu se zapíná a nastavuje na stránce E‑maily Zákazníkům a zkušební odejde Provozovateli', async ({ page, request }) => {
	const enabled = getOption('pneukarnik_termin_reminder_enabled');
	const hour = getOption('pneukarnik_termin_reminder_hour');
	const contact = getOption('pneukarnik_email');
	const provozovatel = `e2e-provozovatel-${Date.now()}@example.test`;
	setOptions({ pneukarnik_email: provozovatel });
	try {
		await login(page);
		await page.goto('/wp-admin/admin.php?page=pneukarnik-customer-emails');

		await expect(page.getByLabel('Posílat Připomínku Termínu')).toBeChecked();
		await page.getByLabel('Posílat Připomínku Termínu').uncheck();
		await page.getByLabel('Kdy den před Termínem').selectOption('18');
		await page.getByLabel(/Po uložení poslat zkušební Připomínku Termínu/).check();
		await page.getByRole('button', { name: 'Uložit' }).click();

		await expect(page.getByText(`Zkušební Připomínka Termínu odeslaná na ${provozovatel}.`)).toBeVisible();
		await expect(page.getByLabel('Posílat Připomínku Termínu')).not.toBeChecked();
		await expect(page.getByLabel('Kdy den před Termínem')).toHaveValue('18');
		const mail = await waitForMail(request, provozovatel, /^\[Zkouška\] Připomínka: zítra v 9:00/);
		expect(mail.text).toContain('Toto je zkušební Připomínka Termínu');
	} finally {
		if (enabled) setOptions({ pneukarnik_termin_reminder_enabled: enabled });
		else setJsonOption('pneukarnik_termin_reminder_enabled', null);
		if (hour) setOptions({ pneukarnik_termin_reminder_hour: hour });
		else setJsonOption('pneukarnik_termin_reminder_hour', null);
		setOptions({ pneukarnik_email: contact });
	}
});

test('Žádost o hodnocení se zapíná a nastavuje na stránce E‑maily Zákazníkům a zkušební odejde Provozovateli', async ({ page, request }) => {
	const enabled = getOption('pneukarnik_review_request_enabled');
	const intro = getOption('pneukarnik_review_request_intro');
	const placeId = getOption('pneukarnik_reviews_place_id');
	const contact = getOption('pneukarnik_email');
	const provozovatel = `e2e-provozovatel-${Date.now()}@example.test`;
	const newIntro = `E2E úvod Žádosti ${Date.now()}`;
	setOptions({ pneukarnik_email: provozovatel, pneukarnik_reviews_place_id: 'ChIJ-e2e' });
	try {
		await login(page);
		await page.goto('/wp-admin/admin.php?page=pneukarnik-customer-emails');

		await expect(page.getByLabel('Posílat Žádost o hodnocení')).toBeChecked();
		await page.getByLabel('Posílat Žádost o hodnocení').uncheck();
		await page.getByLabel('Úvod Žádosti o hodnocení').fill(newIntro);
		await page.getByLabel(/Po uložení poslat zkušební Žádost o hodnocení/).check();
		await page.getByRole('button', { name: 'Uložit' }).click();

		await expect(page.getByText(`Zkušební Žádost o hodnocení odeslaná na ${provozovatel}.`)).toBeVisible();
		await expect(page.getByLabel('Posílat Žádost o hodnocení')).not.toBeChecked();
		await expect(page.getByLabel('Úvod Žádosti o hodnocení')).toHaveValue(newIntro);
		const mail = await waitForMail(request, provozovatel, /^\[Zkouška\] Jak jste u nás byli spokojeni\?/);
		expect(mail.text).toContain(newIntro);
		expect(mail.html).toContain('https://search.google.com/local/writereview?placeid=ChIJ-e2e');
	} finally {
		if (enabled) setOptions({ pneukarnik_review_request_enabled: enabled });
		else setJsonOption('pneukarnik_review_request_enabled', null);
		if (intro) setOptions({ pneukarnik_review_request_intro: intro });
		else setJsonOption('pneukarnik_review_request_intro', null);
		setOptions({ pneukarnik_email: contact, pneukarnik_reviews_place_id: placeId });
	}
});
