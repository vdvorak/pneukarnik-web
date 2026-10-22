<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Oprávnění v administraci: „prohlížet rezervace“ (kalendář, seznam, PDF), „spravovat rezervace“
 * (navíc zadat, upravit a zrušit) a „spravovat provoz“ (Nastavení, Výjimky, E‑maily Zákazníkům).
 * Kdo spravuje, smí i prohlížet. Administrátor má všechny. Pro ostatní jsou role Rezervace – prohlížení
 * (technici), Rezervace – správa a Provozovatel: Editor (Služby, Akce, Průvodci, stránky) se všemi třemi.
 * Pluginy, šablona, uživatelé a Převod ze starého webu zůstávají administrátorovi.
 */
final class Pneukarnik_Access {

	public const VIEW   = 'pneukarnik_view_bookings';
	public const MANAGE = 'pneukarnik_manage_bookings';

	public const OPERATE = 'pneukarnik_manage_operations';

	/** Role => popisek, oprávnění a jestli má navíc vše, co Editor. */
	private const ROLES = [
		'pneukarnik_viewer'   => [ 'Rezervace – prohlížení', [ self::VIEW ], false ],
		'pneukarnik_manager'  => [ 'Rezervace – správa', [ self::VIEW, self::MANAGE ], false ],
		'pneukarnik_operator' => [ 'Provozovatel', [ self::VIEW, self::MANAGE, self::OPERATE ], true ],
	];

	public static function can_view(): bool {
		return current_user_can( self::VIEW ) || current_user_can( self::MANAGE );
	}

	public static function can_manage(): bool {
		return current_user_can( self::MANAGE );
	}

	public static function can_operate(): bool {
		return current_user_can( self::OPERATE );
	}

	/**
	 * Capabilities administrátora a role pluginu. Bez zápisu do DB, když už jsou.
	 */
	public static function ensure(): void {
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( [ self::VIEW, self::MANAGE, self::OPERATE ] as $cap ) {
				if ( ! $admin->has_cap( $cap ) ) {
					$admin->add_cap( $cap );
				}
			}
		}
		foreach ( self::ROLES as $name => [ $label, $caps, $editor ] ) {
			if ( null === get_role( $name ) ) {
				$base = $editor ? (array) get_role( 'editor' )?->capabilities : [];
				add_role( $name, $label, array_merge( $base, array_fill_keys( [ 'read', ...$caps ], true ) ) );
			}
		}
	}
}
