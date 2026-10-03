<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Oprávnění k Rezervacím v administraci: „prohlížet rezervace“ (kalendář, seznam, PDF)
 * a „spravovat rezervace“ (navíc zadat, upravit a zrušit). Kdo spravuje, smí i prohlížet.
 * Administrátor má obě, pro ostatní jsou role Rezervace – prohlížení a Rezervace – správa.
 * Nastavení provozu (Pracovní doba, Výjimky, …) zůstává na manage_options.
 */
final class Pneukarnik_Access {

	public const VIEW   = 'pneukarnik_view_bookings';
	public const MANAGE = 'pneukarnik_manage_bookings';

	private const ROLES = [
		'pneukarnik_viewer'  => [ 'Rezervace – prohlížení', [ self::VIEW ] ],
		'pneukarnik_manager' => [ 'Rezervace – správa', [ self::VIEW, self::MANAGE ] ],
	];

	public static function can_view(): bool {
		return current_user_can( self::VIEW ) || current_user_can( self::MANAGE );
	}

	public static function can_manage(): bool {
		return current_user_can( self::MANAGE );
	}

	/**
	 * Capabilities administrátora a role pro Rezervace. Bez zápisu do DB, když už jsou.
	 */
	public static function ensure(): void {
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( [ self::VIEW, self::MANAGE ] as $cap ) {
				if ( ! $admin->has_cap( $cap ) ) {
					$admin->add_cap( $cap );
				}
			}
		}
		foreach ( self::ROLES as $name => [ $label, $caps ] ) {
			if ( null === get_role( $name ) ) {
				add_role( $name, $label, array_fill_keys( [ 'read', ...$caps ], true ) );
			}
		}
	}
}
