import { execFileSync } from 'node:child_process';
import { E2E_PREFIX } from './support/admin';

/** Smaže Služby a Rezervace vytvořené testy, aby nezůstávaly v lokálním webu. */
export default function globalTeardown(): void {
	const prefix = E2E_PREFIX.trim();
	const script = [
		`ids=$(wp post list --post_type=pneukarnik_service --post_status=any --s="${prefix}" --field=ID --format=ids)`,
		'[ -z "$ids" ] || wp post delete $ids --force --quiet',
		`wp db query "DELETE FROM wp_pneukarnik_bookings WHERE customer_name LIKE '${prefix}%'"`,
	].join('; ');
	execFileSync('docker', ['compose', 'run', '--rm', '-T', 'cli', 'sh', '-c', script], { stdio: 'ignore' });
}
