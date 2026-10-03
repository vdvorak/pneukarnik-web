<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Season management.
 * Season is defined by MM-DD range (year-agnostic) + optional force override.
 * is_active = forced OR (today within MM-DD range).
 */
class Pneukarnik_Season {

	public static function get_settings(): array {
		return [
			'from'      => get_option( 'pneukarnik_season_from', null ) ?: null,
			'to'        => get_option( 'pneukarnik_season_to', null ) ?: null,
			'forced'    => (bool) get_option( 'pneukarnik_season_forced', false ),
			'is_active' => self::is_active(),
		];
	}

	public static function is_active(): bool {
		if ( (bool) get_option( 'pneukarnik_season_forced', false ) ) {
			return true;
		}

		$from = get_option( 'pneukarnik_season_from', '' );
		$to   = get_option( 'pneukarnik_season_to', '' );

		if ( ! $from || ! $to ) {
			return false;
		}

		$today    = Pneukarnik_Clock::today();
		$today_md = $today->format( 'm-d' );

		// Handle wrap-around (e.g. Nov–Apr crosses year boundary)
		if ( $from <= $to ) {
			return $today_md >= $from && $today_md <= $to;
		}

		// Wraps year boundary
		return $today_md >= $from || $today_md <= $to;
	}

	public static function save( string $from, string $to, bool $forced ): bool {
		if ( $from && ! preg_match( '/^\d{2}-\d{2}$/', $from ) ) {
			return false;
		}
		if ( $to && ! preg_match( '/^\d{2}-\d{2}$/', $to ) ) {
			return false;
		}
		update_option( 'pneukarnik_season_from', $from );
		update_option( 'pneukarnik_season_to', $to );
		update_option( 'pneukarnik_season_forced', $forced ? '1' : '' );
		return true;
	}
}
