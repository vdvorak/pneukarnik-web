import { expect, test } from '@playwright/test';
import { login } from './support/admin';
import { waitForMail } from './support/mailpit';
import { getOption, setJsonOption, setOptions } from './support/wp';

// Oba testy mění e‑mail Provozovatele a ukládají celý formulář stránky, proto po sobě.
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
		await expect(page.getByRole('row', { name: /Žádost o hodnocení/ })).toBeVisible();
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
