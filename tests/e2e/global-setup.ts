import { execFileSync } from 'node:child_process';

/** Vynuluje limit rezervací a Zrušení (Pneukarnik_Rate_Limit), jinak by opakované běhy během hodiny narazily na „příliš mnoho rezervací“. */
export default function globalSetup(): void {
	const script = `wp db query "DELETE FROM wp_options WHERE option_name LIKE '\\_transient\\_pnk\\_rl\\_%' OR option_name LIKE '\\_transient\\_timeout\\_pnk\\_rl\\_%'"`;
	execFileSync('docker', ['compose', 'run', '--rm', '-T', 'cli', 'sh', '-c', script], { stdio: 'ignore' });
}
