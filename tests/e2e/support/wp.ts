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

/**
 * Zveřejněná Služba se všemi povinnými poli a daným slugem (formulář administrace slug nenastavuje),
 * další meta (např. `_service_bookable`) jde doplnit. Název musí začínat E2E_PREFIX, aby ji smazal
 * global-teardown. Vrací ID.
 */
export function createService(title: string, slug: string, category: 'pneuservis' | 'autoservis', extraMeta: Record<string, string> = {}): number {
	const meta = { _service_category: category, _service_perex: 'Popis.', _service_price: '500', _service_duration: '60', ...extraMeta };
	return Number(
		wpCli([
			'wp', 'post', 'create', '--post_type=pneukarnik_service', '--post_status=publish', `--post_title=${title}`, `--post_name=${slug}`, `--meta_input=${JSON.stringify(meta)}`, '--porcelain',
		]).trim(),
	);
}

/**
 * Průvodce se všemi povinnými částmi, který odkazuje na danou Službu, ve stavu `status` (výchozí zveřejněný).
 * Název musí začínat E2E_PREFIX, aby ho smazal global-teardown. Vrací ID.
 */
export function createGuide(title: string, serviceId: number, status = 'publish'): number {
	const meta = { _guide_perex: 'Perex.', _guide_service_id: String(serviceId) };
	return Number(
		wpCli([
			'wp', 'post', 'create', '--post_type=pneukarnik_guide', `--post_status=${status}`, `--post_title=${title}`, '--post_content=<p>Text Průvodce.</p>', `--meta_input=${JSON.stringify(meta)}`, '--porcelain',
		]).trim(),
	);
}

/** Změní stav příspěvku (i Služby), např. na `draft`. */
export function setPostStatus(id: number, status: string): void {
	wpCli(['wp', 'post', 'update', String(id), `--post_status=${status}`, '--quiet']);
}

/** Smaže příspěvky (i Služby) natrvalo. */
export function deletePosts(ids: number[]): void {
	if (ids.length) wpCli(['wp', 'post', 'delete', ...ids.map(String), '--force', '--quiet']);
}
