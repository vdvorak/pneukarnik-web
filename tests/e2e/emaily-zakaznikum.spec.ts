import { expect, test } from '@playwright/test';
import { dayFromToday, login, publishPromotion, publishService, uniqueTitle } from './support/admin';
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
		await expect(page.getByRole('row', { name: /^Akce \(Rozesílky\) \d/ })).toBeVisible();
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

test('Rozesílka se složí z Akce na stránce E‑maily Zákazníkům, zkušební odejde Provozovateli a jde naplánovat a zrušit', async ({ page, request }) => {
	test.slow(); // Služba a Akce přes administraci, při souběhu všech testů trvá déle.
	const contact = getOption('pneukarnik_email');
	const provozovatel = `e2e-provozovatel-${Date.now()}@example.test`;
	const service = uniqueTitle('Geometrie');
	const promotion = uniqueTitle('Kontrola brzd zdarma');
	const intro = uniqueTitle('Úvodní věta Rozesílky');
	setOptions({ pneukarnik_email: provozovatel });
	try {
		await login(page);
		await publishService(page, { title: service, category: 'Pneuservis', perex: 'Seřízení geometrie.', price: 1200, duration: 60, bookable: true });
		await publishPromotion(page, { title: promotion, service, from: dayFromToday(-1), to: dayFromToday(10) });

		await page.goto('/wp-admin/admin.php?page=pneukarnik-customer-emails');
		await page.getByRole('link', { name: 'Nová Rozesílka' }).click();
		await expect(page.getByRole('heading', { level: 1 })).toHaveText('Nová Rozesílka');
		await expect(page.getByText(/Počet příjemců: \d+/)).toBeVisible();
		await page.getByLabel(`${service}: ${promotion}`).check();
		await page.getByLabel('Úvodní věta').fill(intro);
		await expect(page.getByRole('button', { name: /^Odeslat hned/ })).toBeDisabled();
		await page.getByRole('button', { name: `Uložit a poslat zkušební e‑mail na ${provozovatel}` }).click();

		await expect(page.getByText(`Zkušební Rozesílka odeslaná na ${provozovatel}.`)).toBeVisible();
		await expect(page.getByLabel(`${service}: ${promotion}`)).toBeChecked();
		await expect(page.getByLabel('Úvodní věta')).toHaveValue(intro);
		await expect(page.getByRole('button', { name: /^Odeslat hned/ })).toBeEnabled();
		await expect(page.frameLocator('iframe[title="Náhled e‑mailu"]').getByText(promotion)).toBeVisible();
		const mail = await waitForMail(request, provozovatel, new RegExp(`^\\[Zkouška\\] Akce: ${service}`));
		expect(mail.text).toContain(intro);
		expect(mail.text).toContain(`Akce: ${promotion}`);
		expect(mail.text).toMatch(/Rezervovat: \S+\/rezervace\/\?sluzba=/);

		await page.goto('/wp-admin/admin.php?page=pneukarnik-customer-emails');
		const row = page.getByRole('row', { name: new RegExp(promotion) });
		await expect(row).toContainText('rozepsaná');
		await row.getByRole('link').click();

		const pneuservis = await page.getByLabel('Příjemci').locator('option[value="pneuservis"]').getAttribute('data-count');
		await page.getByLabel('Příjemci').selectOption('pneuservis');
		await expect(page.getByText(`Počet příjemců: ${pneuservis}`)).toBeVisible();
		await page.getByLabel('Naplánovat na').fill(`${dayFromToday(1)}T10:00`);
		await page.getByRole('button', { name: 'Naplánovat', exact: true }).click();
		await expect(page.getByText('Rozesílka je naplánovaná.')).toBeVisible();
		const [year, month, day] = dayFromToday(1).split('-').map(Number);
		await expect(row).toContainText('Pneuservis');
		await expect(row).toContainText(`naplánovaná na ${day}. ${month}. ${year} 10:00`);

		await row.getByRole('link').click();
		await expect(page.getByLabel('Naplánovat na')).toHaveValue(`${dayFromToday(1)}T10:00`);
		page.once('dialog', (dialog) => dialog.accept());
		await page.getByRole('button', { name: 'Zrušit Rozesílku' }).click();
		await expect(page.getByText('Naplánovaná Rozesílka je zrušená, neodejde.')).toBeVisible();
		await expect(row).toContainText('zrušená');
	} finally {
		setOptions({ pneukarnik_email: contact });
	}
});
