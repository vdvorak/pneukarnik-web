import { expect, test } from '@playwright/test';
import { dayFromToday, login, publishNotice, uniqueTitle } from './support/admin';

// Nahoře je vždy jen nejnovější Oznámení, testy si ho tedy nesmí přebíjet navzájem.
test.describe.configure({ mode: 'serial' });

test.beforeEach(async ({ page }) => {
	await login(page);
});

test('Nahoře je nejnovější platné Oznámení, starší a neplatná se nezobrazí', async ({ page }) => {
	const older = uniqueTitle('Sezóna');
	const newest = uniqueTitle('Dovolená');
	const expired = uniqueTitle('Skončilo');
	const future = uniqueTitle('Teprve bude');
	await publishNotice(page, { title: older, text: 'Starší platné.', from: dayFromToday(-3), to: dayFromToday(3) });
	await publishNotice(page, { title: newest, text: 'Od zítřka máme zavřeno.', from: dayFromToday(0), to: dayFromToday(3) });
	await publishNotice(page, { title: expired, text: 'Už neplatí.', from: dayFromToday(-5), to: dayFromToday(-1) });
	await publishNotice(page, { title: future, text: 'Ještě neplatí.', from: dayFromToday(1), to: dayFromToday(5) });

	for (const path of ['/', '/pneuservis/']) {
		await page.goto(path);
		const top = page.locator('#oznameni');
		await expect(top.getByText(newest)).toBeVisible();
		await expect(top.getByText('Od zítřka máme zavřeno.')).toBeVisible();
		for (const hidden of [older, expired, future]) await expect(page.getByText(hidden)).toHaveCount(0);
	}
});

test('U rezervace jsou všechna platná Oznámení s volbou „u rezervace“', async ({ page }) => {
	const leasing = uniqueTitle('Leasing');
	const storage = uniqueTitle('Uskladnění');
	const expired = uniqueTitle('Leasing loni');
	const notAtBooking = uniqueTitle('Jen nahoře');
	await publishNotice(page, { title: leasing, text: 'Leasingové vozy až od 1. 4.', from: dayFromToday(-2), to: dayFromToday(2), atBooking: true });
	await publishNotice(page, { title: storage, text: 'Kola si vyzvedněte předem.', from: dayFromToday(-1), to: dayFromToday(2), atBooking: true });
	await publishNotice(page, { title: expired, text: 'Neplatí.', from: dayFromToday(-5), to: dayFromToday(-1), atBooking: true });
	await publishNotice(page, { title: notAtBooking, text: 'Jen nahoře.', from: dayFromToday(-1), to: dayFromToday(2) });

	await page.goto('/rezervace/');

	const list = page.locator('.rezervace__oznameni');
	await expect(list.locator('.oznameni__nadpis')).toHaveText([storage, leasing]);
	await expect(list.getByText('Leasingové vozy až od 1. 4.')).toBeVisible();
	await expect(list.getByText(expired)).toHaveCount(0);
	await expect(list.getByText(notAtBooking)).toHaveCount(0);
});

test('Zavřené horní Oznámení se do konce relace znovu nezobrazí', async ({ page, browser }) => {
	const title = uniqueTitle('Zavřít');
	await publishNotice(page, { title, text: 'Tohle jde zavřít.', from: dayFromToday(0), to: dayFromToday(1) });

	await page.goto('/');
	await expect(page.locator('#oznameni').getByText(title)).toBeVisible();
	await page.getByRole('button', { name: 'Zavřít oznámení' }).click();
	await expect(page.locator('#oznameni')).toHaveCount(0);

	await page.reload();
	await expect(page.locator('#oznameni')).toHaveCount(0);
	await page.goto('/pneuservis/');
	await expect(page.locator('#oznameni')).toHaveCount(0);

	// Nová relace (jiný prohlížeč) Oznámení zase vidí.
	const fresh = await browser.newPage();
	await fresh.goto('/');
	await expect(fresh.locator('#oznameni').getByText(title)).toBeVisible();
	await fresh.close();
});
