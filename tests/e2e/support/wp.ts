import { execFileSync } from 'node:child_process';

/**
 * Nastaví volby WordPressu přes WP‑CLI. Formulář Nastavení se v testech nepoužívá tam, kde by
 * souběžně uložil i Pracovní dobu a Sezóny jiných testů.
 */
export function setOptions(options: Record<string, string>): void {
	const script = Object.entries(options)
		.map(([name, value]) => `wp option update ${name} ${shellQuote(value)} --quiet`)
		.join('; ');
	execFileSync('docker', ['compose', 'run', '--rm', '-T', 'cli', 'sh', '-c', script], { stdio: 'ignore' });
}

/** Aktuální hodnota volby (prázdná, když neexistuje). */
export function getOption(name: string): string {
	try {
		return execFileSync('docker', ['compose', 'run', '--rm', '-T', 'cli', 'wp', 'option', 'get', name], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] }).trim();
	} catch {
		return '';
	}
}

const shellQuote = (value: string) => `'${value.replace(/'/g, `'\\''`)}'`;
