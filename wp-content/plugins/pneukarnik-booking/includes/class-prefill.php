<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Předvyplnění formuláře z dřívější Rezervace („Objednat znovu“, ADR 0002).
 *
 * Odkaz /rezervace/?znovu={id}.{podpis} nese id Rezervace podepsané HMAC-SHA256 tajným klíčem
 * webu, v DB se nic neukládá. Vydá jen kontaktní údaje té Rezervace, nikdy poznámku, Služby
 * ani jiné Rezervace. Po anonymizaci Rezervace odkaz přestane fungovat.
 */
final class Pneukarnik_Prefill {

	public const INVALID_TOKEN = 'prefill.invalid_token';

	public static function url( int $booking_id ): string {
		return add_query_arg( 'znovu', self::token( $booking_id ), home_url( '/rezervace/' ) );
	}

	/**
	 * Kontaktní údaje Rezervace z podepsaného tokenu, nebo null (neplatný podpis, Rezervace
	 * neexistuje nebo je anonymizovaná).
	 *
	 * @return array{name:string,phone:string,email:string,plate:string,vehicle:string}|null
	 */
	public static function contact( mixed $token ): ?array {
		if ( ! is_string( $token ) || ! preg_match( '/^([1-9][0-9]*)\.([0-9a-f]{64})$/', $token, $m ) || ! hash_equals( self::token( (int) $m[1] ), $token ) ) {
			return null;
		}
		$booking = Pneukarnik_Booking::get_by_id( (int) $m[1] );
		if ( null === $booking || Pneukarnik_GDPR::is_anonymised( $booking ) ) {
			return null;
		}
		return [
			'name'    => (string) $booking['customer_name'],
			'phone'   => (string) $booking['customer_phone'],
			'email'   => (string) $booking['customer_email'],
			'plate'   => (string) $booking['customer_plate'],
			'vehicle' => (string) $booking['vehicle'],
		];
	}

	private static function token( int $booking_id ): string {
		return $booking_id . '.' . hash_hmac( 'sha256', 'prefill|' . $booking_id, wp_salt( 'pneukarnik' ) );
	}
}
