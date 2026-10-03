<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Vytvoření Rezervace a její načtení.
 *
 * Postup: validace polí → pravidla každé Služby → pravidla dne (Sezóna, leasing) → Termín je
 * v nabídce dne → se zámkem dne a v transakci ověřit, že úsek nic nepřekrývá, a zapsat.
 * Dílna má kapacitu 1.
 * Rezervace má 1..n Služeb a zabírá dílnu po dobu součtu jejich Délek.
 */
class Pneukarnik_Booking {

	public const SOURCE_WEB   = 'web';
	public const SOURCE_ADMIN = 'admin';

	/** Nejvíc Služeb v jedné Rezervaci. */
	public const MAX_SERVICES = 10;

	/** Maximální délky textových polí. */
	private const MAX_LENGTH = [
		'name'            => 120,
		'company'         => 120,
		'leasing_company' => 120,
		'phone'           => 30,
		'email'           => 254,
		'vehicle'         => 100,
		'note'            => 1000,
	];

	/**
	 * Smí Zákazníci rezervovat online? Provozovatel to vypíná v Nastavení.
	 * Zrušení odkazem z e‑mailu funguje i při vypnutí.
	 */
	public static function online_enabled(): bool {
		return (bool) get_option( 'pneukarnik_booking_enabled', '1' );
	}

	/** Zpráva Provozovatele, kterou web při vypnutých online rezervacích ukáže místo formuláře. */
	public static function online_disabled_message(): string {
		$message = trim( (string) get_option( 'pneukarnik_booking_disabled_msg', '' ) );
		return '' !== $message ? $message : __( 'Online rezervace jsou momentálně nedostupné. Kontaktujte nás telefonicky.', 'pneukarnik-booking' );
	}

	public static function save_online( bool $enabled, string $disabled_message ): void {
		update_option( 'pneukarnik_booking_enabled', $enabled ? '1' : '0' );
		update_option( 'pneukarnik_booking_disabled_msg', sanitize_textarea_field( $disabled_message ) );
	}

	/**
	 * Vytvoří Rezervaci.
	 *
	 * Vstup: service_ids (seznam 1..n), date (Y-m-d), time (HH:MM), name, phone, email, plate,
	 * volitelně company, vehicle, note, leasing + leasing_company (povinná s leasingem),
	 * stored_wheels (uloží se, jen když se na ně ptá některá ze Služeb), consent_gdpr (povinný jen z webu).
	 * Sezóna a leasingové datum platí jen pro online Rezervace.
	 *
	 * @param array<mixed> $data Neověřený vstup.
	 * @return array{ok:true,booking:array<string,mixed>,confirmation_token:string}
	 *       |array{ok:false,code:string,status:int,errors?:array<string,string>,season?:array<string,mixed>}
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

		$resolved = self::resolve_services( $input['service_ids'] );
		if ( ! $resolved['ok'] ) {
			return $resolved;
		}
		$services = $resolved['services'];
		$duration = $resolved['duration'];

		if ( self::SOURCE_WEB === $source ) {
			$refusal = self::day_refusal( $services, $input['leasing'], $input['date'] );
			if ( null !== $refusal ) {
				return $refusal;
			}
		}

		if ( ! Pneukarnik_Slot_Engine::is_offered( $duration, $input['date'], $input['time'], self::SOURCE_WEB === $source ) ) {
			return self::error( 'booking.slot_unavailable', 422 );
		}

		$time_end = Pneukarnik_Slot_Engine::minutes_to_hhmm( Pneukarnik_Slot_Engine::hhmm_to_minutes( $input['time'] ) + $duration );
		$result   = Pneukarnik_DB::with_day_lock(
			$input['date'],
			static fn(): array => self::insert_if_free( $input, $services, $time_end, $source )
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
	 * Služby Rezervace v zadaném pořadí a součet jejich Délek, nebo důvod první Služby,
	 * kterou rezervovat nejde.
	 *
	 * @param list<int> $service_ids
	 * @return array{ok:true,services:list<Pneukarnik_Service>,duration:int}|array{ok:false,code:string,status:int}
	 */
	public static function resolve_services( array $service_ids ): array {
		$services = [];
		foreach ( $service_ids as $id ) {
			$service = Pneukarnik_Service::find( $id );
			$refusal = self::service_refusal( $service );
			if ( null !== $refusal || null === $service ) {
				return $refusal ?? self::error( 'booking.service_not_found', 404 );
			}
			$services[] = $service;
		}
		return [
			'ok'       => true,
			'services' => $services,
			'duration' => array_sum( array_map( static fn( Pneukarnik_Service $s ): int => $s->duration, $services ) ),
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
		return null;
	}

	/**
	 * Proč Služby nejde online rezervovat na daný den, nebo null, když jde. V Sezóně jen sezónní
	 * Služby, Leasingový zákazník až od leasingového data Sezóny. Odmítnutí nese Sezónu,
	 * aby web mohl vysvětlit proč.
	 *
	 * @param list<Pneukarnik_Service> $services
	 * @return array{ok:false,code:string,status:int,season:array{name:string,from:string,to:string,leasing_from:string|null}}|null
	 */
	public static function day_refusal( array $services, bool $leasing, string $date ): ?array {
		$season = Pneukarnik_Season::for_date( $date );
		if ( null === $season ) {
			return null;
		}
		foreach ( $services as $service ) {
			if ( ! $service->seasonal ) {
				return self::error( 'booking.seasonal_only', 422 ) + [ 'season' => $season ];
			}
		}
		if ( $leasing && ! Pneukarnik_Season::allows_leasing( $date ) ) {
			return self::error( 'booking.leasing_date', 422 ) + [ 'season' => $season ];
		}
		return null;
	}

	/**
	 * Dny měsíce s alespoň jedním volným Termínem pro online Rezervaci Služeb a omezení
	 * (Sezóna, leasing), kvůli kterým jiné takové dny nabídnuté nejsou.
	 *
	 * @param list<Pneukarnik_Service> $services
	 * @param string                   $month YYYY-MM
	 * @return array{days:list<string>,restrictions:list<array{code:string,season:array{name:string,from:string,to:string,leasing_from:string|null}}>}
	 */
	public static function available_days( array $services, int $duration, bool $leasing, string $month ): array {
		$days         = [];
		$restrictions = [];
		foreach ( Pneukarnik_Slot_Engine::days_with_free_termin( $duration, $month ) as $date ) {
			$refusal = self::day_refusal( $services, $leasing, $date );
			if ( null === $refusal ) {
				$days[] = $date;
				continue;
			}
			// Každé omezení jednou: kód a Sezóna.
			$restrictions[ $refusal['code'] . $refusal['season']['from'] ] = [
				'code'   => $refusal['code'],
				'season' => $refusal['season'],
			];
		}
		return [
			'days'         => $days,
			'restrictions' => array_values( $restrictions ),
		];
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
	 * Doplní řádkům Rezervací z DB klíč service_name: názvy jejich Služeb oddělené čárkou
	 * (pro výpisy: seznam v administraci, PDF, iCal).
	 *
	 * @param list<array<string,mixed>> $rows Řádky tabulky Rezervací.
	 * @return list<array<string,mixed>>
	 */
	public static function with_service_names( array $rows ): array {
		if ( ! $rows ) {
			return $rows;
		}
		global $wpdb;
		$ids          = array_map( static fn( array $row ): int => (int) $row['id'], $rows );
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// $placeholders jsou jen %d, hodnoty jdou přes prepare().
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		$names = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT booking_id, GROUP_CONCAT(service_name ORDER BY position SEPARATOR ', ') AS names FROM %i WHERE booking_id IN ({$placeholders}) GROUP BY booking_id",
				Pneukarnik_DB::booking_services_table(),
				...$ids
			),
			OBJECT_K
		);
		// phpcs:enable
		return array_map(
			static fn( array $row ): array => [ 'service_name' => $names[ $row['id'] ]->names ?? '' ] + $row,
			$rows
		);
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
	 * @param array{service_ids:list<int>,date:string,time:string,name:string,company:string,phone:string,email:string,plate:string,vehicle:string,note:string,leasing:bool,leasing_company:string,stored_wheels:bool,consent_gdpr:bool} $input
	 * @param list<Pneukarnik_Service> $services
	 * @return array{ok:true,id:int,cancel_token:string,confirmation_token:string}|array{ok:false,code:string,status:int}
	 */
	private static function insert_if_free( array $input, array $services, string $time_end, string $source ): array {
		Pneukarnik_DB::begin();
		try {
			return self::insert_in_transaction( $input, $services, $time_end, $source );
		} catch ( \Throwable $e ) {
			Pneukarnik_DB::rollback();
			throw $e;
		}
	}

	/**
	 * Část insert_if_free uvnitř otevřené transakce. Vždy ji ukončí (commit nebo rollback).
	 *
	 * @param array{service_ids:list<int>,date:string,time:string,name:string,company:string,phone:string,email:string,plate:string,vehicle:string,note:string,leasing:bool,leasing_company:string,stored_wheels:bool,consent_gdpr:bool} $input
	 * @param list<Pneukarnik_Service> $services
	 * @return array{ok:true,id:int,cancel_token:string,confirmation_token:string}|array{ok:false,code:string,status:int}
	 */
	private static function insert_in_transaction( array $input, array $services, string $time_end, string $source ): array {
		global $wpdb;

		if ( Pneukarnik_Slot_Engine::overlaps_confirmed( $input['date'], $input['time'], $time_end ) ) {
			Pneukarnik_DB::rollback();
			return self::error( 'booking.slot_taken', 409 );
		}

		$cancel_token       = bin2hex( random_bytes( 32 ) );
		$confirmation_token = bin2hex( random_bytes( 32 ) );
		$now                = Pneukarnik_Clock::now();

		$inserted = $wpdb->insert(
			Pneukarnik_DB::bookings_table(),
			[
				'customer_name'      => $input['name'],
				'customer_company'   => '' !== $input['company'] ? $input['company'] : null,
				'customer_plate'     => $input['plate'],
				'customer_email'     => strtolower( $input['email'] ),
				'customer_phone'     => $input['phone'],
				'customer_note'      => '' !== $input['note'] ? $input['note'] : null,
				'vehicle'            => '' !== $input['vehicle'] ? $input['vehicle'] : null,
				'leasing'            => $input['leasing'] ? 1 : 0,
				'leasing_company'    => $input['leasing'] ? $input['leasing_company'] : null,
				'stored_wheels'      => $input['stored_wheels'] && self::asks_stored_wheels( $services ) ? 1 : 0,
				'booking_date'       => $input['date'],
				'time_start'         => $input['time'],
				'time_end'           => $time_end,
				'status'             => 'CONFIRMED',
				'cancel_token_hash'  => hash( 'sha256', $cancel_token ),
				'confirm_token_hash' => hash( 'sha256', $confirmation_token ),
				'consent_gdpr_at'    => $input['consent_gdpr'] ? $now->format( 'Y-m-d H:i:s' ) : null,
				'source'             => $source,
				'created_at'         => $now->format( 'Y-m-d H:i:s' ),
			]
		);
		if ( ! $inserted ) {
			Pneukarnik_DB::rollback();
			return self::error( 'booking.internal_error', 500 );
		}
		$id = (int) $wpdb->insert_id;

		foreach ( $services as $position => $service ) {
			$price    = $service->price_by_vehicle ? null : $service->price;
			$inserted = $wpdb->insert(
				Pneukarnik_DB::booking_services_table(),
				[
					'booking_id'   => $id,
					'position'     => $position,
					'service_id'   => $service->id,
					'service_name' => $service->title,
					'duration'     => $service->duration,
					'price'        => $price,
					'price_from'   => null !== $price && $service->price_from ? 1 : 0,
				]
			);
			if ( ! $inserted ) {
				Pneukarnik_DB::rollback();
				return self::error( 'booking.internal_error', 500 );
			}
		}
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
	 * @return array{0:array{service_ids:list<int>,date:string,time:string,name:string,company:string,phone:string,email:string,plate:string,vehicle:string,note:string,leasing:bool,leasing_company:string,stored_wheels:bool,consent_gdpr:bool},1:array<string,string>}
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

		$service_ids = self::validate_service_ids( $data['service_ids'] ?? null );
		if ( is_string( $service_ids ) ) {
			$errors['service_ids'] = $service_ids;
			$service_ids           = [];
		}

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

		$leasing         = self::checked( $data['leasing'] ?? null );
		$leasing_company = sanitize_text_field( $text( 'leasing_company' ) );
		$check( 'leasing_company', $leasing ? $leasing_company : '', $leasing );

		$consent = self::checked( $data['consent_gdpr'] ?? null );
		if ( $consent_required && ! $consent ) {
			$errors['consent_gdpr'] = 'required';
		}

		return [
			[
				'service_ids'     => $service_ids,
				'date'            => $date,
				'time'            => $time,
				'name'            => $name,
				'company'         => $company,
				'phone'           => $phone,
				'email'           => $email,
				'plate'           => $plate,
				'vehicle'         => $vehicle,
				'note'            => $note,
				'leasing'         => $leasing,
				'leasing_company' => $leasing_company,
				'stored_wheels'   => self::checked( $data['stored_wheels'] ?? null ),
				'consent_gdpr'    => $consent,
			],
			$errors,
		];
	}

	/**
	 * Zaškrtnuté políčko z JSON nebo formuláře.
	 */
	private static function checked( mixed $value ): bool {
		return in_array( $value, [ true, 1, '1', 'on', 'true' ], true );
	}

	/**
	 * @param list<Pneukarnik_Service> $services
	 */
	private static function asks_stored_wheels( array $services ): bool {
		foreach ( $services as $service ) {
			if ( $service->ask_stored_wheels ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Seznam id Služeb, nebo kód chyby pole: required, invalid, too_many, duplicate.
	 *
	 * @return list<int>|string
	 */
	private static function validate_service_ids( mixed $value ): array|string {
		if ( null === $value || [] === $value ) {
			return 'required';
		}
		if ( ! is_array( $value ) || ! array_is_list( $value ) ) {
			return 'invalid';
		}
		if ( count( $value ) > self::MAX_SERVICES ) {
			return 'too_many';
		}
		$ids = [];
		foreach ( $value as $id ) {
			if ( ! is_int( $id ) && ! ( is_string( $id ) && ctype_digit( $id ) ) ) {
				return 'invalid';
			}
			$ids[] = (int) $id;
		}
		return count( array_unique( $ids ) ) === count( $ids ) ? $ids : 'duplicate';
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
		$services = self::services_of( (int) $row['id'] );
		return [
			'id'               => (int) $row['id'],
			'services'         => $services,
			'service_name'     => implode( ', ', array_column( $services, 'name' ) ),
			'customer_name'    => $row['customer_name'],
			'customer_company' => $row['customer_company'],
			'customer_plate'   => $row['customer_plate'],
			'customer_email'   => $row['customer_email'],
			'customer_phone'   => $row['customer_phone'],
			'customer_note'    => $row['customer_note'],
			'vehicle'          => $row['vehicle'] ?? null,
			'leasing'          => (bool) $row['leasing'],
			'leasing_company'  => $row['leasing_company'],
			'stored_wheels'    => (bool) $row['stored_wheels'],
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

	/**
	 * Služby Rezervace v pořadí, jak je Zákazník vybral, s Délkou a cenou z okamžiku vytvoření.
	 *
	 * @return list<array{service_id:int,name:string,duration:int,price:int|null,price_from:bool}>
	 */
	private static function services_of( int $booking_id ): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT service_id, service_name, duration, price, price_from FROM %i WHERE booking_id = %d ORDER BY position',
				Pneukarnik_DB::booking_services_table(),
				$booking_id
			),
			ARRAY_A
		);
		return array_map(
			static fn( array $row ): array => [
				'service_id' => (int) $row['service_id'],
				'name'       => $row['service_name'],
				'duration'   => (int) $row['duration'],
				'price'      => null === $row['price'] ? null : (int) $row['price'],
				'price_from' => (bool) $row['price_from'],
			],
			$rows ?: []
		);
	}
}
