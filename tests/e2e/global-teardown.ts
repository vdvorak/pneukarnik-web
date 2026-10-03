import { execSync } from 'node:child_process';
import { E2E_PREFIX } from './support/admin';

/** Smaže Služby vytvořené testy, aby nezůstávaly v lokálním webu. */
export default function globalTeardown(): void {
	const script = `ids=$(wp post list --post_type=pneukarnik_service --post_status=any --s="${E2E_PREFIX.trim()}" --field=ID --format=ids); [ -z "$ids" ] || wp post delete $ids --force --quiet`;
	execSync(`docker compose run --rm -T cli sh -c '${script}'`, { stdio: 'ignore' });
}
