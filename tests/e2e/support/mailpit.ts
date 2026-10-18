import { expect, type APIRequestContext } from '@playwright/test';

/** Mailpit lokálního webu (docker-compose.yml), sem chodí všechny e‑maily WordPressu. */
const MAILPIT_URL = process.env.MAILPIT_URL ?? `http://localhost:${process.env.MAILPIT_PORT ?? '8025'}`;

type Message = { ID: string; Subject: string };
type Mail = { html: string; text: string; replyTo: string[] };

/** Počká na e‑mail pro adresu s předmětem a vrátí jeho HTML, text a Reply-To. */
export async function waitForMail(request: APIRequestContext, to: string, subject: RegExp): Promise<Mail> {
	return waitForSearch(request, `to:"${to}"`, subject);
}

/**
 * Počká na e‑mail s předmětem, ve kterém je text (např. jedinečné jméno z testu), ať šel komukoli.
 * Pro e‑maily Provozovateli, jehož adresu souběžně mění jiné testy.
 */
export async function waitForMailAbout(request: APIRequestContext, text: string, subject: RegExp): Promise<Mail> {
	return waitForSearch(request, `subject:"${text}"`, subject);
}

async function waitForSearch(request: APIRequestContext, query: string, subject: RegExp): Promise<Mail> {
	let found: Message | undefined;
	await expect
		.poll(
			async () => {
				const response = await request.get(`${MAILPIT_URL}/api/v1/search`, { params: { query } });
				const { messages } = (await response.json()) as { messages: Message[] };
				found = messages.find((message) => subject.test(message.Subject));
				return found !== undefined;
			},
			{ message: `e‑mail „${subject}“ (${query}) v Mailpitu (${MAILPIT_URL})` },
		)
		.toBe(true);
	const message = (await (await request.get(`${MAILPIT_URL}/api/v1/message/${found?.ID}`)).json()) as { HTML: string; Text: string; ReplyTo: { Address: string }[] | null };
	return { html: message.HTML, text: message.Text, replyTo: (message.ReplyTo ?? []).map((address) => address.Address) };
}
