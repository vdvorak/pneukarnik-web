import { execFileSync } from 'node:child_process';

/**
 * Nastaví volby WordPressu přes WP‑CLI. Formulář Nastavení se v testech nepoužívá tam, kde by
 * souběžně uložil i Pracovní dobu a Sezóny jiných testů.
 */
export function setOptions(options: Record<string, string>): void {
	const script = Object.entries(options)
		.map(([name, value]) => `wp option update ${name} ${shellQuote(value)} --quiet`)
		.join('; ');
	wpCli(['sh', '-c', script]);
}

/** Aktuální hodnota volby (prázdná, když neexistuje). */
export function getOption(name: string): string {
	try {
		return wpCli(['wp', 'option', 'get', name]).trim();
	} catch {
		return '';
	}
}

/** Volba se strukturovanou hodnotou (pole PHP) jako JSON, null = volbu smaže. */
export function setJsonOption(name: string, value: unknown): void {
	const script = value === null ? `wp option delete ${name} --quiet || true` : `wp option update ${name} ${shellQuote(JSON.stringify(value))} --format=json --quiet`;
	wpCli(['sh', '-c', script]);
}

/** Strukturovaná hodnota volby, null když neexistuje. */
export function getJsonOption(name: string): unknown {
	try {
		return JSON.parse(wpCli(['wp', 'option', 'get', name, '--format=json']));
	} catch {
		return null;
	}
}

/** Příkaz v kontejneru `cli` (WP‑CLI) nad lokálním webem, vrací jeho výstup. */
function wpCli(command: string[]): string {
	return execFileSync('docker', ['compose', 'run', '--rm', '-T', 'cli', ...command], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] });
}

const shellQuote = (value: string) => `'${value.replace(/'/g, `'\\''`)}'`;
