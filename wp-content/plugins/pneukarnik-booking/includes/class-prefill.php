<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Předvyplnění formuláře z dřívější Rezervace („Objednat znovu“, ADR 0002).
 *
 * Odkaz /rezervace/?znovu={id}.{podpis} nese id Rezervace podepsané HMAC-SHA256 tajným klíčem
 * webu, v DB se nic neukládá. „Objednat znovu“ vydá kontaktní údaje, Služby, které jde dál
 * rezervovat online, a uskladněná kola; odkaz z Připomínky přezutí jen kontaktní údaje. Nikdy
 * poznámku ani jiné Rezervace. Po anonymizaci Rezervace odkaz přestane fungovat.
 */
final class Pneukarnik_Prefill {

	public const INVALID_TOKEN = 'prefill.invalid_token';

	private const SCOPE_BOOKING = 'prefill';
	private const SCOPE_CONTACT = 'prefill-contact';

	/**
	 * „Objednat znovu“: kontaktní údaje, Služby a uskladněná kola dané Rezervace.
	 */
	public static function url( int $booking_id ): string {
		return self::url_with( self::token( self::SCOPE_BOOKING, $booking_id ) );
	}

	/**
	 * Formulář předvyplněný kontaktními údaji z poslední Rezervace e‑mailu, bez ní
	 * (anonymizovaná) prázdný.
	 */
	public static function url_for_email( string $email ): string {
		global $wpdb;
		$id = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM %i WHERE customer_email = %s ORDER BY booking_date DESC, id DESC LIMIT 1',
				Pneukarnik_DB::bookings_table(),
				strtolower( trim( $email ) )
			)
		);
		return $id > 0 ? self::url_with( self::token( self::SCOPE_CONTACT, $id ) ) : home_url( '/rezervace/' );
	}

	/**
	 * Údaje k předvyplnění z podepsaného tokenu, nebo null (neplatný podpis, Rezervace
	 * neexistuje nebo je anonymizovaná). Služby jen ty, které jde dál rezervovat online.
	 *
	 * @return array{name:string,phone:string,email:string,plate:string,vehicle:string,service_ids?:list<int>,stored_wheels?:bool}|null
	 */
	public static function details( mixed $token ): ?array {
		if ( ! is_string( $token ) || ! preg_match( '/^([1-9][0-9]*)\.([0-9a-f]{64})$/', $token, $m ) ) {
			return null;
		}
		$id    = (int) $m[1];
		$scope = null;
		foreach ( [ self::SCOPE_BOOKING, self::SCOPE_CONTACT ] as $candidate ) {
			if ( hash_equals( self::token( $candidate, $id ), $token ) ) {
				$scope = $candidate;
			}
		}
		$booking = null === $scope ? null : Pneukarnik_Booking::get_by_id( $id );
		if ( null === $booking || Pneukarnik_GDPR::is_anonymised( $booking ) ) {
			return null;
		}
		$contact = [
			'name'    => (string) $booking['customer_name'],
			'phone'   => (string) $booking['customer_phone'],
			'email'   => (string) $booking['customer_email'],
			'plate'   => (string) $booking['customer_plate'],
			'vehicle' => (string) $booking['vehicle'],
		];
		if ( self::SCOPE_CONTACT === $scope ) {
			return $contact;
		}
		$service_ids = array_values(
			array_filter(
				array_map( 'intval', array_column( $booking['services'], 'service_id' ) ),
				static fn( int $service_id ): bool => null === Pneukarnik_Booking::service_refusal( Pneukarnik_Service::find( $service_id ) )
			)
		);
		return $contact + [
			'service_ids'   => $service_ids,
			'stored_wheels' => $booking['stored_wheels'],
		];
	}

	private static function url_with( string $token ): string {
		return add_query_arg( 'znovu', $token, home_url( '/rezervace/' ) );
	}

	private static function token( string $scope, int $booking_id ): string {
		return $booking_id . '.' . hash_hmac( 'sha256', $scope . '|' . $booking_id, wp_salt( 'pneukarnik' ) );
	}
}
