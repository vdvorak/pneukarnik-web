import { execFileSync } from 'node:child_process';
import { E2E_PREFIX } from './support/admin';

/** Smaže Služby, Rezervace a Výjimky vytvořené testy, aby nezůstávaly v lokálním webu. */
export default function globalTeardown(): void {
	const prefix = E2E_PREFIX.trim();
	const script = [
		`ids=$(wp post list --post_type=pneukarnik_service --post_status=any --s="${prefix}" --field=ID --format=ids)`,
		'[ -z "$ids" ] || wp post delete $ids --force --quiet',
		`wp db query "DELETE b, s FROM wp_pneukarnik_bookings b LEFT JOIN wp_pneukarnik_booking_services s ON s.booking_id = b.id WHERE b.customer_name LIKE '${prefix}%'"`,
		`wp db query "DELETE FROM wp_pneukarnik_day_exceptions WHERE note LIKE '${prefix}%'"`,
	].join('; ');
	execFileSync('docker', ['compose', 'run', '--rm', '-T', 'cli', 'sh', '-c', script], { stdio: 'ignore' });
}
