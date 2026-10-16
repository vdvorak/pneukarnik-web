import { execFileSync } from 'node:child_process';
import { request, type FullConfig } from '@playwright/test';
import { ADMIN_STATE } from './support/admin';

/**
 * Vynuluje limit rezervací a Zrušení (Pneukarnik_Rate_Limit), jinak by opakované běhy během hodiny narazily na „příliš mnoho rezervací“.
 * Přihlásí admina jednou pro všechny testy (login() v support/admin.ts): souběžná přihlášení stejného uživatele si v WordPressu
 * přepisují tokeny relací (user meta session_tokens) a část relací hned neplatí.
 */
export default async function globalSetup(config: FullConfig): Promise<void> {
	const script = `wp db query "DELETE FROM wp_options WHERE option_name LIKE '\\_transient\\_pnk\\_rl\\_%' OR option_name LIKE '\\_transient\\_timeout\\_pnk\\_rl\\_%'"`;
	execFileSync('docker', ['compose', 'run', '--rm', '-T', 'cli', 'sh', '-c', script], { stdio: 'ignore' });

	const admin = await request.newContext({ baseURL: config.projects[0].use.baseURL });
	await admin.get('/wp-login.php'); // Testovací cookie, bez ní WordPress přihlášení odmítne.
	const response = await admin.post('/wp-login.php', { form: { log: 'admin', pwd: 'admin', testcookie: '1' } });
	if (!response.url().includes('/wp-admin/')) {
		throw new Error(`Přihlášení admina se nepovedlo (${response.status()} ${response.url()}).`);
	}
	await admin.storageState({ path: ADMIN_STATE });
	await admin.dispose();
}
