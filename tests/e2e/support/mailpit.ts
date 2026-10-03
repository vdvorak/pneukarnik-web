import { expect, type APIRequestContext } from '@playwright/test';

/** Mailpit lokálního webu (docker-compose.yml), sem chodí všechny e‑maily WordPressu. */
const MAILPIT_URL = process.env.MAILPIT_URL ?? `http://localhost:${process.env.MAILPIT_PORT ?? '8025'}`;

type Message = { ID: string; Subject: string };

/** Počká na e‑mail pro adresu s předmětem a vrátí jeho HTML a text. */
export async function waitForMail(request: APIRequestContext, to: string, subject: RegExp): Promise<{ html: string; text: string }> {
	let found: Message | undefined;
	await expect
		.poll(
			async () => {
				const response = await request.get(`${MAILPIT_URL}/api/v1/search`, { params: { query: `to:"${to}"` } });
				const { messages } = (await response.json()) as { messages: Message[] };
				found = messages.find((message) => subject.test(message.Subject));
				return found !== undefined;
			},
			{ message: `e‑mail „${subject}“ pro ${to} v Mailpitu (${MAILPIT_URL})` },
		)
		.toBe(true);
	const message = (await (await request.get(`${MAILPIT_URL}/api/v1/message/${found?.ID}`)).json()) as { HTML: string; Text: string };
	return { html: message.HTML, text: message.Text };
}
