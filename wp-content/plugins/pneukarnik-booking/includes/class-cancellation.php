<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Zrušení Rezervace (viz CONTEXT.md).
 *
 * Zákazník: odkazem z e‑mailu s tokenem (v DB jen jeho hash). Připomínka Termínu, odeslaná až
 * později, má místo tokenu id Rezervace podepsané spolu s hashem tokenu (signed_url), takže
 * odkaz z potvrzení platí dál a anonymizace oba odkazy zneplatní. Odkaz platí do Termínu a zrušit
 * jde nejpozději ve Lhůtě zrušení před Termínem. Zrušit jde jen jednou, opakovaný odkaz pak
 * hlásí „už zrušeno“. Provozovatel: kdykoli, bez Lhůty.
 * Zrušená Rezervace hned přestane zabírat dílnu (Termíny počítají jen potvrzené).
 */
final class Pneukarnik_Cancellation {

	public const ALLOWED           = 'cancellation.allowed';
	public const CANCELLED         = 'cancellation.cancelled';
	public const TOO_LATE          = 'cancellation.too_late';
	public const ALREADY_CANCELLED = 'cancellation.already_cancelled';
	public const INVALID_TOKEN     = 'cancellation.invalid_token';
	/** Pokus o Zrušení nad limit IP (Pneukarnik_Rate_Limit), Rezervace zůstala. */
	public const RATE_LIMITED = 'cancellation.rate_limited';

	/** HTTP status odmítnutého Zrušení podle kódu. */
	private const STATUS = [
		self::TOO_LATE          => 422,
		self::ALREADY_CANCELLED => 409,
		self::INVALID_TOKEN     => 404,
	];

	public static function url( string $token ): string {
		return add_query_arg( 'r', $token, home_url( '/rezervace/zruseni/' ) );
	}

	/**
	 * Odkaz na Zrušení bez tokenu z potvrzení (ten se neukládá), pro Připomínku Termínu.
	 * Prázdný, když Rezervace neexistuje nebo je anonymizovaná.
	 */
	public static function signed_url( int $booking_id ): string {
		$hash = self::token_hash( $booking_id );
		return null === $hash ? '' : self::url( self::signed_token( $booking_id, $hash ) );
	}

	/**
	 * Poslední okamžik, kdy Zákazník může Rezervaci zrušit: Termín minus Lhůta zrušení.
	 *
	 * @param array{booking_date:string,time_start:string} $booking
	 */
	public static function deadline( array $booking ): DateTimeImmutable {
		return self::termin( $booking )->modify( '-' . Pneukarnik_Working_Hours::get_cancellation_hours() . ' hours' );
	}

	/**
	 * Co odkaz udělá, bez změny, pro stránku Zrušení: allowed, too_late nebo already_cancelled,
	 * Rezervace bez osobních údajů a do kdy jde zrušit. Null pro neplatný odkaz.
	 *
	 * @return array{code:string,booking:array{date:string,time_start:string,time_end:string,services:list<string>,plate:string,status:string},cancel_until:string}|null
	 */
	public static function preview( mixed $token ): ?array {
		[ $booking, $code ] = self::resolve( $token );
		if ( null === $booking ) {
			return null;
		}
		return [
			'code'         => $code,
			'booking'      => self::public_view( $booking ),
			'cancel_until' => self::deadline( $booking )->format( 'Y-m-d H:i' ),
		];
	}

	/**
	 * Odkaz „Objednat znovu“ pro Rezervaci z odkazu na Zrušení, pro neplatný odkaz prázdný formulář.
	 * Do REST odpovědi nepatří: vydá kontaktní údaje.
	 */
	public static function prefill_url( mixed $token ): string {
		[ $booking ] = self::resolve( $token );
		return null === $booking ? home_url( '/rezervace/' ) : Pneukarnik_Prefill::url( (int) $booking['id'] );
	}

	/**
	 * Zrušení odkazem z e‑mailu.
	 *
	 * @return array{ok:true,code:string,booking:array<string,mixed>}|array{ok:false,code:string,status:int}
	 */
	public static function cancel_by_token( mixed $token ): array {
		[ $booking, $code ] = self::resolve( $token );
		if ( null === $booking || self::ALLOWED !== $code ) {
			return self::refusal( $code );
		}
		return self::cancel( $booking, null, true );
	}

	/**
	 * Zrušení Provozovatelem: kdykoli, s volitelným důvodem.
	 *
	 * @return array{ok:true,code:string,booking:array<string,mixed>}|array{ok:false,code:string,status:int}
	 */
	public static function cancel_by_provozovatel( int $booking_id, ?string $reason ): array {
		$booking = Pneukarnik_Booking::get_by_id( $booking_id );
		if ( null === $booking ) {
			return [
				'ok'     => false,
				'code'   => 'cancellation.not_found',
				'status' => 404,
			];
		}
		return self::cancel( $booking, $reason, false );
	}

	/**
	 * @param array<string,mixed> $booking
	 * @return array{ok:true,code:string,booking:array<string,mixed>}|array{ok:false,code:string,status:int}
	 */
	private static function cancel( array $booking, ?string $reason, bool $by_customer ): array {
		global $wpdb;
		// Jen potvrzenou: ze dvou souběžných Zrušení projde jedno.
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE %i SET status = 'CANCELLED', cancelled_at = %s, cancel_reason = %s WHERE id = %d AND status = 'CONFIRMED'",
				Pneukarnik_DB::bookings_table(),
				Pneukarnik_Clock::now()->format( 'Y-m-d H:i:s' ),
				$reason,
				$booking['id']
			)
		);
		if ( 1 !== $updated ) {
			return self::refusal( self::ALREADY_CANCELLED );
		}

		$cancelled = Pneukarnik_Booking::get_by_id( (int) $booking['id'] ) ?? $booking;
		Pneukarnik_Notifications::on_booking_cancelled( $cancelled, $by_customer );
		return [
			'ok'      => true,
			'code'    => self::CANCELLED,
			'booking' => $cancelled,
		];
	}

	/**
	 * Rezervace bez osobních údajů: jen to, podle čeho ji Zákazník pozná.
	 *
	 * @param array<string,mixed> $booking
	 * @return array{date:string,time_start:string,time_end:string,services:list<string>,plate:string,status:string}
	 */
	public static function public_view( array $booking ): array {
		return [
			'date'       => $booking['booking_date'],
			'time_start' => $booking['time_start'],
			'time_end'   => $booking['time_end'],
			'services'   => array_column( $booking['services'], 'name' ),
			'plate'      => $booking['customer_plate'],
			'status'     => $booking['status'],
		];
	}

	/**
	 * Rezervace z odkazu a co odkaz udělá. Odkaz platí do Termínu, i když už zrušil.
	 *
	 * @return array{0:array<string,mixed>|null,1:string} Rezervace (null pro neplatný odkaz) a kód.
	 */
	private static function resolve( mixed $token ): array {
		$booking = self::find( $token );
		$now     = Pneukarnik_Clock::now();
		if ( null === $booking || $now > self::termin( $booking ) ) {
			return [ null, self::INVALID_TOKEN ];
		}
		if ( 'CANCELLED' === $booking['status'] ) {
			return [ $booking, self::ALREADY_CANCELLED ];
		}
		return [ $booking, $now > self::deadline( $booking ) ? self::TOO_LATE : self::ALLOWED ];
	}

	/**
	 * Rezervace podle tokenu z odkazu, nebo null.
	 *
	 * @return array<string,mixed>|null
	 */
	private static function find( mixed $token ): ?array {
		if ( is_string( $token ) && preg_match( '/^([1-9][0-9]*)-[0-9a-f]{64}$/', $token, $m ) ) {
			$hash = self::token_hash( (int) $m[1] );
			return null !== $hash && hash_equals( self::signed_token( (int) $m[1], $hash ), $token ) ? Pneukarnik_Booking::get_by_id( (int) $m[1] ) : null;
		}
		if ( ! is_string( $token ) || ! preg_match( '/^[0-9a-f]{64}$/', $token ) ) {
			return null;
		}
		global $wpdb;
		$id = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM %i WHERE cancel_token_hash = %s',
				Pneukarnik_DB::bookings_table(),
				hash( 'sha256', $token )
			)
		);
		return null === $id ? null : Pneukarnik_Booking::get_by_id( (int) $id );
	}

	/**
	 * Pomlčka, ne tečka: stránka Zrušení čte token přes sanitize_key.
	 */
	private static function signed_token( int $booking_id, string $token_hash ): string {
		return $booking_id . '-' . hash_hmac( 'sha256', 'cancel|' . $booking_id . '|' . $token_hash, wp_salt( 'pneukarnik' ) );
	}

	private static function token_hash( int $booking_id ): ?string {
		global $wpdb;
		$hash = $wpdb->get_var( $wpdb->prepare( 'SELECT cancel_token_hash FROM %i WHERE id = %d', Pneukarnik_DB::bookings_table(), $booking_id ) );
		return null === $hash || '' === $hash ? null : (string) $hash;
	}

	/**
	 * @param array{booking_date:string,time_start:string} $booking
	 */
	private static function termin( array $booking ): DateTimeImmutable {
		return Pneukarnik_Clock::at( $booking['booking_date'] . ' ' . substr( $booking['time_start'], 0, 5 ) );
	}

	/**
	 * @return array{ok:false,code:string,status:int}
	 */
	private static function refusal( string $code ): array {
		return [
			'ok'     => false,
			'code'   => $code,
			'status' => self::STATUS[ $code ] ?? 422,
		];
	}
}
