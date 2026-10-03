<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Vytvoření Rezervace a její načtení.
 *
 * Postup: validace polí → pravidla Služby → Termín je v nabídce dne → se zámkem dne
 * a v transakci ověřit, že úsek nic nepřekrývá, a zapsat. Dílna má kapacitu 1.
 */
class Pneukarnik_Booking {

	public const SOURCE_WEB   = 'web';
	public const SOURCE_ADMIN = 'admin';

	/** Maximální délky textových polí. */
	private const MAX_LENGTH = [
		'name'    => 120,
		'company' => 120,
		'phone'   => 30,
		'email'   => 254,
		'vehicle' => 100,
		'note'    => 1000,
	];

	/**
	 * Vytvoří Rezervaci.
	 *
	 * Vstup: service_id, date (Y-m-d), time (HH:MM), name, phone, email, plate,
	 * volitelně company, vehicle a note, consent_gdpr (povinný jen z webu).
	 *
	 * @param array<mixed> $data Neověřený vstup.
	 * @return array{ok:true,booking:array<string,mixed>,confirmation_token:string}
	 *       |array{ok:false,code:string,status:int,errors?:array<string,string>}
	 */
	public static function create( array $data, string $source = self::SOURCE_WEB ): array {
		[ $input, $errors ] = self::validate_fields( $data, self::SOURCE_WEB === $source );
		if ( $errors ) {
			return [
				'ok'     => false,
				'code'   => 'booking.invalid_fields',
				'status' => 422,
				'errors' => $errors,
			];
		}

		$service = Pneukarnik_Service::find( $input['service_id'] );
		$refusal = self::service_refusal( $service );
		if ( null !== $refusal || null === $service ) {
			return $refusal ?? self::error( 'booking.service_not_found', 404 );
		}

		if ( ! Pneukarnik_Slot_Engine::is_offered( $service->duration, $input['date'], $input['time'], self::SOURCE_WEB === $source ) ) {
			return self::error( 'booking.slot_unavailable', 422 );
		}

		$time_end = Pneukarnik_Slot_Engine::minutes_to_hhmm( Pneukarnik_Slot_Engine::hhmm_to_minutes( $input['time'] ) + $service->duration );
		$result   = Pneukarnik_DB::with_day_lock(
			$input['date'],
			static fn(): array => self::insert_if_free( $input, $service, $time_end, $source )
		);
		if ( null === $result ) {
			return self::error( 'booking.busy', 503 );
		}
		if ( ! $result['ok'] ) {
			return $result;
		}

		$booking = self::get_by_id( $result['id'] );
		if ( null === $booking ) {
			return self::error( 'booking.internal_error', 500 );
		}
		Pneukarnik_Notifications::on_booking_created( $booking, $result['cancel_token'] );

		return [
			'ok'                 => true,
			'booking'            => $booking,
			'confirmation_token' => $result['confirmation_token'],
		];
	}

	/**
	 * Proč Službu nejde rezervovat online, nebo null, když jde.
	 *
	 * @return array{ok:false,code:string,status:int}|null
	 */
	public static function service_refusal( ?Pneukarnik_Service $service ): ?array {
		if ( ! $service || 'publish' !== $service->status ) {
			return self::error( 'booking.service_not_found', 404 );
		}
		if ( ! $service->bookable ) {
			return self::error( 'booking.service_not_bookable', 422 );
		}
		if ( Pneukarnik_Season::is_active() && ! $service->seasonal ) {
			return self::error( 'booking.seasonal_only', 422 );
		}
		return null;
	}

	public static function confirmation_url( string $token ): string {
		return add_query_arg( 'r', $token, home_url( '/rezervace/potvrzeni/' ) );
	}

	/**
	 * Potvrzená Rezervace podle tokenu z adresy stránky potvrzení.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function find_by_confirmation_token( string $token ): ?array {
		if ( ! preg_match( '/^[0-9a-f]{64}$/', $token ) ) {
			return null;
		}
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE confirm_token_hash = %s AND status = 'CONFIRMED'",
				Pneukarnik_DB::bookings_table(),
				hash( 'sha256', $token )
			),
			ARRAY_A
		);
		return $row ? self::hydrate( $row ) : null;
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public static function get_by_id( int $id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', Pneukarnik_DB::bookings_table(), $id ),
			ARRAY_A
		);
		return $row ? self::hydrate( $row ) : null;
	}

	/**
	 * @param array{service_id:int,date:string,time:string,name:string,company:string,phone:string,email:string,plate:string,vehicle:string,note:string,consent_gdpr:bool} $input
	 * @return array{ok:true,id:int,cancel_token:string,confirmation_token:string}|array{ok:false,code:string,status:int}
	 */
	private static function insert_if_free( array $input, Pneukarnik_Service $service, string $time_end, string $source ): array {
		Pneukarnik_DB::begin();
		try {
			return self::insert_in_transaction( $input, $service, $time_end, $source );
		} catch ( \Throwable $e ) {
			Pneukarnik_DB::rollback();
			throw $e;
		}
	}

	/**
	 * Část insert_if_free uvnitř otevřené transakce. Vždy ji ukončí (commit nebo rollback).
	 *
	 * @param array{service_id:int,date:string,time:string,name:string,company:string,phone:string,email:string,plate:string,vehicle:string,note:string,consent_gdpr:bool} $input
	 * @return array{ok:true,id:int,cancel_token:string,confirmation_token:string}|array{ok:false,code:string,status:int}
	 */
	private static function insert_in_transaction( array $input, Pneukarnik_Service $service, string $time_end, string $source ): array {
		global $wpdb;

		if ( Pneukarnik_Slot_Engine::overlaps_confirmed( $input['date'], $input['time'], $time_end ) ) {
			Pneukarnik_DB::rollback();
			return self::error( 'booking.slot_taken', 409 );
		}

		$cancel_token       = bin2hex( random_bytes( 32 ) );
		$confirmation_token = bin2hex( random_bytes( 32 ) );
		$now                = Pneukarnik_Clock::now();
		$in_30_days         = $now->modify( '+30 days' );
		$termin_day         = Pneukarnik_Clock::at( $input['date'] );

		$inserted = $wpdb->insert(
			Pneukarnik_DB::bookings_table(),
			[
				'service_id'              => $service->id,
				'customer_name'           => $input['name'],
				'customer_company'        => '' !== $input['company'] ? $input['company'] : null,
				'customer_plate'          => $input['plate'],
				'customer_email'          => strtolower( $input['email'] ),
				'customer_phone'          => $input['phone'],
				'customer_note'           => '' !== $input['note'] ? $input['note'] : null,
				'vehicle'                 => '' !== $input['vehicle'] ? $input['vehicle'] : null,
				'booking_date'            => $input['date'],
				'time_start'              => $input['time'],
				'time_end'                => $time_end,
				'status'                  => 'CONFIRMED',
				'cancel_token_hash'       => hash( 'sha256', $cancel_token ),
				'cancel_token_expires_at' => $termin_day < $in_30_days ? $termin_day->format( 'Y-m-d 23:59:59' ) : $in_30_days->format( 'Y-m-d H:i:s' ),
				'confirm_token_hash'      => hash( 'sha256', $confirmation_token ),
				'consent_gdpr_at'         => $input['consent_gdpr'] ? $now->format( 'Y-m-d H:i:s' ) : null,
				'source'                  => $source,
				'created_at'              => $now->format( 'Y-m-d H:i:s' ),
			]
		);
		if ( ! $inserted ) {
			Pneukarnik_DB::rollback();
			return self::error( 'booking.internal_error', 500 );
		}
		$id = (int) $wpdb->insert_id;
		Pneukarnik_DB::commit();

		return [
			'ok'                 => true,
			'id'                 => $id,
			'cancel_token'       => $cancel_token,
			'confirmation_token' => $confirmation_token,
		];
	}

	/**
	 * Ověří a znormalizuje pole. Chyby jsou po polích se stabilními kódy:
	 * required, invalid, too_long.
	 *
	 * @param array<mixed> $data
	 * @return array{0:array{service_id:int,date:string,time:string,name:string,company:string,phone:string,email:string,plate:string,vehicle:string,note:string,consent_gdpr:bool},1:array<string,string>}
	 */
	private static function validate_fields( array $data, bool $consent_required ): array {
		$errors = [];
		$text   = static function ( string $field ) use ( $data, &$errors ): string {
			$value = $data[ $field ] ?? null;
			if ( null === $value ) {
				return '';
			}
			if ( ! is_scalar( $value ) ) {
				$errors[ $field ] = 'invalid';
				return '';
			}
			return trim( (string) $value );
		};
		$check  = static function ( string $field, string $value, bool $required, ?callable $valid = null ) use ( &$errors ): void {
			if ( isset( $errors[ $field ] ) ) {
				return;
			}
			if ( '' === $value ) {
				if ( $required ) {
					$errors[ $field ] = 'required';
				}
				return;
			}
			if ( isset( self::MAX_LENGTH[ $field ] ) && mb_strlen( $value ) > self::MAX_LENGTH[ $field ] ) {
				$errors[ $field ] = 'too_long';
				return;
			}
			if ( $valid && ! $valid( $value ) ) {
				$errors[ $field ] = 'invalid';
			}
		};

		$service_id = $text( 'service_id' );
		$check( 'service_id', $service_id, true, static fn( string $v ): bool => ctype_digit( $v ) );

		$date = $text( 'date' );
		$check(
			'date',
			$date,
			true,
			static fn( string $v ): bool => (bool) preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m ) && checkdate( (int) $m[2], (int) $m[3], (int) $m[1] )
		);

		$time = $text( 'time' );
		$check( 'time', $time, true, static fn( string $v ): bool => (bool) preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $v ) );

		$name = sanitize_text_field( $text( 'name' ) );
		$check( 'name', $name, true );

		$company = sanitize_text_field( $text( 'company' ) );
		$check( 'company', $company, false );

		$phone = sanitize_text_field( $text( 'phone' ) );
		$check( 'phone', $phone, true, static fn( string $v ): bool => (bool) preg_match( '/^\+?\d{9,15}$/', (string) preg_replace( '/[\s\-\/().]/', '', $v ) ) );

		$email = sanitize_text_field( $text( 'email' ) );
		$check( 'email', $email, true, static fn( string $v ): bool => (bool) is_email( $v ) );

		$plate = strtoupper( (string) preg_replace( '/[\s\-]/', '', $text( 'plate' ) ) );
		$check( 'plate', $plate, true, static fn( string $v ): bool => (bool) preg_match( '/^[A-Z0-9]{2,10}$/', $v ) );

		$vehicle = sanitize_text_field( $text( 'vehicle' ) );
		$check( 'vehicle', $vehicle, false );

		$note = sanitize_textarea_field( $text( 'note' ) );
		$check( 'note', $note, false );

		$consent = in_array( $data['consent_gdpr'] ?? null, [ true, 1, '1', 'on', 'true' ], true );
		if ( $consent_required && ! $consent ) {
			$errors['consent_gdpr'] = 'required';
		}

		return [
			[
				'service_id'   => (int) $service_id,
				'date'         => $date,
				'time'         => $time,
				'name'         => $name,
				'company'      => $company,
				'phone'        => $phone,
				'email'        => $email,
				'plate'        => $plate,
				'vehicle'      => $vehicle,
				'note'         => $note,
				'consent_gdpr' => $consent,
			],
			$errors,
		];
	}

	/**
	 * @return array{ok:false,code:string,status:int}
	 */
	private static function error( string $code, int $status ): array {
		return [
			'ok'     => false,
			'code'   => $code,
			'status' => $status,
		];
	}

	/**
	 * @param array<string,mixed> $row
	 * @return array<string,mixed>
	 */
	private static function hydrate( array $row ): array {
		$service_name = get_the_title( (int) $row['service_id'] );
		return [
			'id'               => (int) $row['id'],
			'service_id'       => (int) $row['service_id'],
			'service_name'     => $service_name ?: '',
			'customer_name'    => $row['customer_name'],
			'customer_company' => $row['customer_company'],
			'customer_plate'   => $row['customer_plate'],
			'customer_email'   => $row['customer_email'],
			'customer_phone'   => $row['customer_phone'],
			'customer_note'    => $row['customer_note'],
			'vehicle'          => $row['vehicle'] ?? null,
			'booking_date'     => $row['booking_date'],
			'time_start'       => substr( $row['time_start'], 0, 5 ),
			'time_end'         => substr( $row['time_end'], 0, 5 ),
			'status'           => $row['status'],
			'source'           => $row['source'] ?? self::SOURCE_WEB,
			'created_at'       => $row['created_at'],
			'cancelled_at'     => $row['cancelled_at'] ?? null,
			'cancel_reason'    => $row['cancel_reason'] ?? null,
		];
	}
}
