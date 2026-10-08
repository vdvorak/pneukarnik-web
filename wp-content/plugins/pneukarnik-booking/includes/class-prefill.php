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
 * rezervovat online, a uskladněná kola. Odkaz z Připomínky přezutí vydá kontaktní údaje a ze
 * sezónní Rezervace téhož e‑mailu její sezónní Služby a uskladněná kola. Nikdy poznámku, Termín
 * ani nic dalšího z jiných Rezervací. Po anonymizaci Rezervace odkaz přestane fungovat.
 *
 * Odkazy nesou i nepodepsaný parametr zdroj (objednat-znovu, pripominka-prezuti), podle kterého
 * rezervace.js měří v Matomu, odkud Zákazník přišel. Token do Matoma neodejde, zdroj ano.
 */
final class Pneukarnik_Prefill {

	public const INVALID_TOKEN = 'prefill.invalid_token';

	private const SCOPE_BOOKING = 'prefill';
	private const SCOPE_CONTACT = 'prefill-contact';

	private const SOURCE_BOOKING  = 'objednat-znovu';
	private const SOURCE_REMINDER = 'pripominka-prezuti';

	/**
	 * „Objednat znovu“: kontaktní údaje, Služby a uskladněná kola dané Rezervace.
	 */
	public static function url( int $booking_id ): string {
		return self::url_with( self::token( self::SCOPE_BOOKING, $booking_id ), self::SOURCE_BOOKING );
	}

	/**
	 * Připomínka přezutí: formulář předvyplněný kontaktními údaji z poslední Rezervace e‑mailu
	 * a sezónními Službami z poslední Rezervace, která nějakou měla (vyhodnotí se při otevření).
	 * Bez Rezervace (anonymizovaná) prázdný.
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
		return $id > 0 ? self::url_with( self::token( self::SCOPE_CONTACT, $id ), self::SOURCE_REMINDER ) : add_query_arg( 'zdroj', self::SOURCE_REMINDER, home_url( '/rezervace/' ) );
	}

	/**
	 * Údaje k předvyplnění z podepsaného tokenu, nebo null (neplatný podpis, Rezervace
	 * neexistuje nebo je anonymizovaná). Služby jen ty, které jde dál rezervovat online,
	 * u Připomínky jen sezónní a jen pokud nějaká zbude.
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
		if ( self::SCOPE_BOOKING === $scope ) {
			return $contact + [
				'service_ids'   => self::bookable_service_ids( $booking, false ),
				'stored_wheels' => $booking['stored_wheels'],
			];
		}
		$seasonal    = self::last_seasonal_booking( $contact['email'] );
		$service_ids = null === $seasonal ? [] : self::bookable_service_ids( $seasonal, true );
		return [] === $service_ids ? $contact : $contact + [
			'service_ids'   => $service_ids,
			'stored_wheels' => $seasonal['stored_wheels'],
		];
	}

	/**
	 * Služby Rezervace, které jde dál rezervovat online, případně jen sezónní.
	 *
	 * @param array<string,mixed> $booking
	 * @return list<int>
	 */
	private static function bookable_service_ids( array $booking, bool $seasonal_only ): array {
		return array_values(
			array_filter(
				array_map( 'intval', array_column( $booking['services'], 'service_id' ) ),
				static function ( int $service_id ) use ( $seasonal_only ): bool {
					$service = Pneukarnik_Service::find( $service_id );
					return null === Pneukarnik_Booking::service_refusal( $service ) && ( ! $seasonal_only || $service->seasonal );
				}
			)
		);
	}

	/**
	 * Poslední Rezervace e‑mailu s aspoň jednou sezónní Službou, nebo null.
	 *
	 * @return array<string,mixed>|null
	 */
	private static function last_seasonal_booking( string $email ): ?array {
		global $wpdb;
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT id FROM %i WHERE customer_email = %s ORDER BY booking_date DESC, id DESC',
				Pneukarnik_DB::bookings_table(),
				$email
			)
		);
		foreach ( $ids as $id ) {
			$booking = Pneukarnik_Booking::get_by_id( (int) $id );
			foreach ( $booking['services'] ?? [] as $service ) {
				if ( Pneukarnik_Service::find( (int) $service['service_id'] )?->seasonal ) {
					return $booking;
				}
			}
		}
		return null;
	}

	private static function url_with( string $token, string $source ): string {
		return add_query_arg(
			[
				'znovu' => $token,
				'zdroj' => $source,
			],
			home_url( '/rezervace/' )
		);
	}

	private static function token( string $scope, int $booking_id ): string {
		return $booking_id . '.' . hash_hmac( 'sha256', $scope . '|' . $booking_id, wp_salt( 'pneukarnik' ) );
	}
}
