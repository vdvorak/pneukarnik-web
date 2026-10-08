<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Vytvoření, úprava a načtení Rezervace.
 *
 * Postup: validace polí → pravidla každé Služby → pravidla dne (Sezóna, leasing) → Termín je
 * v nabídce dne → se zámkem dne a v transakci ověřit, že úsek nic nepřekrývá, a zapsat.
 * Dílna má kapacitu 1.
 * Rezervace má 1..n Služeb a zabírá dílnu po dobu součtu jejich Délek.
 *
 * Provozovatel (telefonické objednávky): e‑mail a SPZ nepovinné, i Služby jen na telefon,
 * bez Sezóny, mřížky, předstihu a horizontu. Mimo Pracovní dobu jen s outside_working_hours,
 * překryv nikdy.
 */
class Pneukarnik_Booking {

	public const SOURCE_WEB          = 'web';
	public const SOURCE_PROVOZOVATEL = 'provozovatel';
	/** Převedená ze starého webu (Pneukarnik_Legacy_Import). */
	public const SOURCE_STARY_WEB = 'stary-web';

	public const STATUS_CONFIRMED = 'CONFIRMED';
	public const STATUS_CANCELLED = 'CANCELLED';

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
	 * stored_wheels (z webu se uloží, jen když se na ně ptá některá ze Služeb), consent_gdpr (povinný jen z webu),
	 * refuse_offers (zaškrtnuté „Neposílat Nabídky a připomínky“, jen z webu, platí pro e‑mail Rezervace,
	 * nezaškrtnuté založí nárok, viz Pneukarnik_Subscriptions).
	 * Sezóna a leasingové datum platí jen pro online Rezervace. Provozovatel: viz popis třídy.
	 *
	 * @param array<mixed> $data Neověřený vstup.
	 * @return array{ok:true,booking:array<string,mixed>,confirmation_token:string}
	 *       |array{ok:false,code:string,status:int,errors?:array<string,string>,season?:array<string,mixed>}
	 */
	public static function create( array $data, string $source = self::SOURCE_WEB ): array {
		$online             = self::SOURCE_WEB === $source;
		[ $input, $errors ] = self::validate_fields( $data, $online );
		if ( $errors ) {
			return self::invalid_fields( $errors );
		}

		$resolved = self::resolve_services( $input['service_ids'], $online );
		if ( ! $resolved['ok'] ) {
			return $resolved;
		}
		$services = $resolved['services'];
		$duration = $resolved['duration'];

		if ( $online ) {
			$refusal = self::day_refusal( $services, $input['leasing'], $input['date'] );
			if ( null !== $refusal ) {
				return $refusal;
			}
			if ( ! Pneukarnik_Slot_Engine::is_offered( $duration, $input['date'], $input['time'] ) ) {
				return self::error( 'booking.slot_unavailable', 422 );
			}
		} else {
			$refusal = self::provozovatel_time_refusal( $input['date'], $input['time'], $duration, self::checked( $data['outside_working_hours'] ?? null ) );
			if ( null !== $refusal ) {
				return $refusal;
			}
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
		if ( $online ) {
			Pneukarnik_Subscriptions::after_online_booking( $booking['customer_email'], $input['refuse_offers'] );
		}
		Pneukarnik_Notifications::on_booking_created( $booking, $result['cancel_token'] );

		return [
			'ok'                 => true,
			'booking'            => $booking,
			'confirmation_token' => $result['confirmation_token'],
		];
	}

	/**
	 * Úprava Rezervace Provozovatelem: kontakt, poznámka, leasing, uskladněná kola, Služby a Termín.
	 * Chybějící pole zůstanou. Při změně Služeb nebo Termínu platí stejná pravidla času jako
	 * při zadání Provozovatelem. Služby, které se nezměnily, si nechají název, Délku a cenu
	 * z okamžiku vytvoření. Zrušenou Rezervaci upravit nejde.
	 *
	 * @param array<mixed> $data Neověřený vstup, pole jako u create() a outside_working_hours.
	 * @return array{ok:true,booking:array<string,mixed>}
	 *       |array{ok:false,code:string,status:int,errors?:array<string,string>}
	 */
	public static function update( int $id, array $data ): array {
		$booking = self::get_by_id( $id );
		if ( null === $booking ) {
			return self::error( 'booking.not_found', 404 );
		}
		if ( self::STATUS_CONFIRMED !== $booking['status'] ) {
			return self::error( 'booking.cancelled', 409 );
		}

		$current            = self::input_of( $booking );
		[ $input, $errors ] = self::validate_fields( array_intersect_key( $data, $current ) + $current, false );
		if ( $errors ) {
			return self::invalid_fields( $errors );
		}

		$services = null;
		$duration = (int) array_sum( array_column( $booking['services'], 'duration' ) );
		if ( $input['service_ids'] !== $current['service_ids'] ) {
			$resolved = self::resolve_services( $input['service_ids'], false );
			if ( ! $resolved['ok'] ) {
				return $resolved;
			}
			$services = $resolved['services'];
			$duration = $resolved['duration'];
		}

		$moved = null !== $services || $input['date'] !== $current['date'] || $input['time'] !== $current['time'];
		if ( ! $moved ) {
			self::write_contact( $id, $input );
			return [
				'ok'      => true,
				'booking' => self::get_by_id( $id ) ?? $booking,
			];
		}

		$refusal = self::provozovatel_time_refusal( $input['date'], $input['time'], $duration, self::checked( $data['outside_working_hours'] ?? null ) );
		if ( null !== $refusal ) {
			return $refusal;
		}
		$time_end = Pneukarnik_Slot_Engine::minutes_to_hhmm( Pneukarnik_Slot_Engine::hhmm_to_minutes( $input['time'] ) + $duration );
		$result   = Pneukarnik_DB::with_day_lock(
			$input['date'],
			static fn(): array => self::move_if_free( $id, $input, $services, $time_end, $input['date'] !== $current['date'] )
		);
		if ( null === $result ) {
			return self::error( 'booking.busy', 503 );
		}
		if ( ! $result['ok'] ) {
			return $result;
		}
		return [
			'ok'      => true,
			'booking' => self::get_by_id( $id ) ?? $booking,
		];
	}

	/**
	 * Proč Provozovatel Rezervaci na tento čas zadat nemůže, nebo null. Mimo Pracovní dobu
	 * (celý úsek v jednom bloku efektivní Pracovní doby dne) jen s vědomým potvrzením.
	 *
	 * @return array{ok:false,code:string,status:int}|null
	 */
	private static function provozovatel_time_refusal( string $date, string $time, int $duration, bool $outside_confirmed ): ?array {
		$start = Pneukarnik_Slot_Engine::hhmm_to_minutes( $time );
		if ( $duration <= 0 ) {
			return self::error( 'booking.no_duration', 422 );
		}
		if ( $start + $duration > 24 * 60 ) {
			return self::error( 'booking.past_midnight', 422 );
		}
		if ( ! $outside_confirmed && ! Pneukarnik_Slot_Engine::within_working_hours( $date, $start, $start + $duration ) ) {
			return self::error( 'booking.outside_working_hours', 422 );
		}
		return null;
	}

	/**
	 * Služby Rezervace v zadaném pořadí a součet jejich Délek, nebo důvod první Služby,
	 * kterou rezervovat nejde. Provozovatel (online = false) zadá i Službu jen na telefon.
	 *
	 * @param list<int> $service_ids
	 * @return array{ok:true,services:list<Pneukarnik_Service>,duration:int}|array{ok:false,code:string,status:int}
	 */
	public static function resolve_services( array $service_ids, bool $online = true ): array {
		$services = [];
		foreach ( $service_ids as $id ) {
			$service = Pneukarnik_Service::find( $id );
			if ( null === $service || 'publish' !== $service->status ) {
				return self::error( 'booking.service_not_found', 404 );
			}
			$refusal = $online ? self::service_refusal( $service ) : null;
			if ( null !== $refusal ) {
				return $refusal;
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

	/**
	 * Nejbližší den v horizontu s alespoň jedním volným Termínem pro online Rezervaci Služeb,
	 * nebo null, když žádný není. Kalendář formuláře podle něj přeskočí plné měsíce.
	 *
	 * @param list<Pneukarnik_Service> $services
	 * @return string|null YYYY-MM-DD
	 */
	public static function first_available_day( array $services, int $duration, bool $leasing ): ?string {
		$today = Pneukarnik_Clock::today();
		$last  = $today->modify( '+' . Pneukarnik_Working_Hours::get_horizon_days() . ' days' );
		for ( $day = $today; $day <= $last; $day = $day->modify( '+1 day' ) ) {
			$date = $day->format( 'Y-m-d' );
			if ( null === self::day_refusal( $services, $leasing, $date ) && Pneukarnik_Slot_Engine::free_termins( $duration, $date ) ) {
				return $date;
			}
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
	 * Potvrzené Rezervace ve dnech od–do (včetně) podle Termínu.
	 *
	 * @return list<array<string,mixed>>
	 */
	public static function confirmed_between( string $from, string $to ): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE booking_date BETWEEN %s AND %s AND status = %s ORDER BY booking_date, time_start',
				Pneukarnik_DB::bookings_table(),
				$from,
				$to,
				self::STATUS_CONFIRMED
			),
			ARRAY_A
		);
		return array_map( [ self::class, 'hydrate' ], $rows ?: [] );
	}

	/**
	 * Seznam Rezervací pro Provozovatele podle Termínu vzestupně.
	 * Hledání podle jména (i firmy), SPZ a e‑mailu po částech textu, telefonu podle číslic.
	 *
	 * @param array{from?:string,to?:string,status?:string,search?:string} $filters status: CONFIRMED, CANCELLED, nebo prázdný = vše.
	 * @return array{bookings:list<array<string,mixed>>,total:int}
	 */
	public static function search( array $filters, int $page, int $per_page ): array {
		global $wpdb;
		$wheres = [ '1=1' ];
		$args   = [];
		if ( '' !== ( $filters['from'] ?? '' ) ) {
			$wheres[] = 'booking_date >= %s';
			$args[]   = $filters['from'];
		}
		if ( '' !== ( $filters['to'] ?? '' ) ) {
			$wheres[] = 'booking_date <= %s';
			$args[]   = $filters['to'];
		}
		if ( '' !== ( $filters['status'] ?? '' ) ) {
			$wheres[] = 'status = %s';
			$args[]   = $filters['status'];
		}
		$search = trim( $filters['search'] ?? '' );
		if ( '' !== $search ) {
			$like   = '%' . $wpdb->esc_like( $search ) . '%';
			$plate  = '%' . $wpdb->esc_like( strtoupper( (string) preg_replace( '/[\s\-]/', '', $search ) ) ) . '%';
			$or     = [ 'customer_name LIKE %s', 'customer_company LIKE %s', 'customer_email LIKE %s', 'customer_plate LIKE %s' ];
			$args   = [ ...$args, $like, $like, $like, $plate ];
			$digits = (string) preg_replace( '/\D/', '', $search );
			if ( strlen( $digits ) >= 3 ) {
				$or[]   = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(customer_phone, ' ', ''), '-', ''), '/', ''), '(', ''), ')', '') LIKE %s";
				$args[] = '%' . $digits . '%';
			}
			$wheres[] = '(' . implode( ' OR ', $or ) . ')';
		}
		$where_sql = implode( ' AND ', $wheres );
		$table     = Pneukarnik_DB::bookings_table();

		// $where_sql skládá jen pevné fragmenty s placeholdery, hodnoty jdou přes prepare().
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE {$where_sql}", $table, ...$args ) );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE {$where_sql} ORDER BY booking_date, time_start, id LIMIT %d OFFSET %d",
				$table,
				...[ ...$args, $per_page, ( max( 1, $page ) - 1 ) * $per_page ]
			),
			ARRAY_A
		);
		// phpcs:enable

		return [
			'bookings' => array_map( [ self::class, 'hydrate' ], $rows ?: [] ),
			'total'    => $total,
		];
	}

	/**
	 * @param array{service_ids:list<int>,date:string,time:string,name:string,company:string,phone:string,email:string,plate:string,vehicle:string,note:string,leasing:bool,leasing_company:string,stored_wheels:bool,consent_gdpr:bool,refuse_offers:bool} $input
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
	 * @param array{service_ids:list<int>,date:string,time:string,name:string,company:string,phone:string,email:string,plate:string,vehicle:string,note:string,leasing:bool,leasing_company:string,stored_wheels:bool,consent_gdpr:bool,refuse_offers:bool} $input
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
				'stored_wheels'      => $input['stored_wheels'] && ( self::SOURCE_WEB !== $source || self::asks_stored_wheels( $services ) ) ? 1 : 0,
				'booking_date'       => $input['date'],
				'time_start'         => $input['time'],
				'time_end'           => $time_end,
				'status'             => self::STATUS_CONFIRMED,
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

		if ( ! self::insert_services( $id, $services ) ) {
			Pneukarnik_DB::rollback();
			return self::error( 'booking.internal_error', 500 );
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
	 * Zapíše Rezervaci převedenou ze starého webu s jednou Službou, bez pravidel nabídky
	 * Termínů a bez kontroly překryvu (ten hlásí převod). Nový token pro Zrušení, e‑mail neposílá.
	 *
	 * @param array{legacy_key:string,name:string,phone:string,email:string,plate:string,note:string,date:string,time:string,created_at:string} $fields
	 * @param int $duration Délka, se kterou byla Rezervace na starém webu (minuty).
	 * @return array{id:int,cancel_token:string}|null Null, když zápis selhal (např. souběžný převod téže Rezervace).
	 */
	public static function insert_imported( array $fields, Pneukarnik_Service $service, int $duration ): ?array {
		global $wpdb;
		$cancel_token = bin2hex( random_bytes( 32 ) );
		Pneukarnik_DB::begin();
		try {
			$suppress = $wpdb->suppress_errors();
			$inserted = $wpdb->insert(
				Pneukarnik_DB::bookings_table(),
				[
					'customer_name'     => $fields['name'],
					'customer_plate'    => $fields['plate'],
					'customer_email'    => $fields['email'],
					'customer_phone'    => $fields['phone'],
					'customer_note'     => '' !== $fields['note'] ? $fields['note'] : null,
					'booking_date'      => $fields['date'],
					'time_start'        => $fields['time'],
					'time_end'          => Pneukarnik_Slot_Engine::minutes_to_hhmm( Pneukarnik_Slot_Engine::hhmm_to_minutes( $fields['time'] ) + $duration ),
					'status'            => self::STATUS_CONFIRMED,
					'cancel_token_hash' => hash( 'sha256', $cancel_token ),
					// Starý formulář souhlas se zpracováním vyžadoval.
					'consent_gdpr_at'   => $fields['created_at'],
					'source'            => self::SOURCE_STARY_WEB,
					'legacy_key_hash'   => hash( 'sha256', $fields['legacy_key'] ),
					'created_at'        => $fields['created_at'],
				]
			);
			$wpdb->suppress_errors( $suppress );
			$id = (int) $wpdb->insert_id;
			if ( ! $inserted || ! self::insert_services( $id, [ $service ], [ $duration ] ) ) {
				Pneukarnik_DB::rollback();
				return null;
			}
			Pneukarnik_DB::commit();
		} catch ( \Throwable $e ) {
			Pneukarnik_DB::rollback();
			throw $e;
		}
		return [
			'id'           => $id,
			'cancel_token' => $cancel_token,
		];
	}

	/**
	 * Zapíše Služby Rezervace s názvem, Délkou a cenou platnými teď.
	 *
	 * @param list<Pneukarnik_Service> $services
	 * @param array<int,int>           $durations Jiná Délka podle pořadí Služby (převod ze starého webu).
	 */
	private static function insert_services( int $booking_id, array $services, array $durations = [] ): bool {
		global $wpdb;
		foreach ( $services as $position => $service ) {
			$price    = $service->price_by_vehicle ? null : $service->price;
			$inserted = $wpdb->insert(
				Pneukarnik_DB::booking_services_table(),
				[
					'booking_id'   => $booking_id,
					'position'     => $position,
					'service_id'   => $service->id,
					'service_name' => $service->title,
					'duration'     => $durations[ $position ] ?? $service->duration,
					'price'        => $price,
					'price_from'   => null !== $price && $service->price_from ? 1 : 0,
				]
			);
			if ( ! $inserted ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Přesun Rezervace na jiný Termín nebo změna jejích Služeb: se zámkem dne a v transakci
	 * ověřit, že úsek nepřekrývá jinou potvrzenou Rezervaci, a zapsat. Na jiný den se pošle
	 * Připomínka Termínu znovu, k novému Termínu.
	 *
	 * @param array{service_ids:list<int>,date:string,time:string,name:string,company:string,phone:string,email:string,plate:string,vehicle:string,note:string,leasing:bool,leasing_company:string,stored_wheels:bool,consent_gdpr:bool,refuse_offers:bool} $input
	 * @param list<Pneukarnik_Service>|null $services Nové Služby, null = beze změny.
	 * @return array{ok:true}|array{ok:false,code:string,status:int}
	 */
	private static function move_if_free( int $id, array $input, ?array $services, string $time_end, bool $new_day ): array {
		global $wpdb;
		Pneukarnik_DB::begin();
		try {
			if ( Pneukarnik_Slot_Engine::overlaps_confirmed( $input['date'], $input['time'], $time_end, $id ) ) {
				Pneukarnik_DB::rollback();
				return self::error( 'booking.slot_taken', 409 );
			}
			$moved    = $wpdb->update(
				Pneukarnik_DB::bookings_table(),
				[
					'booking_date' => $input['date'],
					'time_start'   => $input['time'],
					'time_end'     => $time_end,
				] + ( $new_day ? [ 'reminder_sent' => 0 ] : [] ) + self::contact_columns( $input ),
				[
					'id'     => $id,
					'status' => self::STATUS_CONFIRMED,
				]
			);
			$replaced = null === $services || (
				false !== $wpdb->delete( Pneukarnik_DB::booking_services_table(), [ 'booking_id' => $id ] )
				&& self::insert_services( $id, $services )
			);
			if ( false === $moved || ! $replaced ) {
				Pneukarnik_DB::rollback();
				return self::error( 'booking.internal_error', 500 );
			}
			Pneukarnik_DB::commit();
			return [ 'ok' => true ];
		} catch ( \Throwable $e ) {
			Pneukarnik_DB::rollback();
			throw $e;
		}
	}

	/**
	 * @param array{name:string,company:string,phone:string,email:string,plate:string,vehicle:string,note:string,leasing:bool,leasing_company:string,stored_wheels:bool} $input
	 */
	private static function write_contact( int $id, array $input ): void {
		global $wpdb;
		$wpdb->update( Pneukarnik_DB::bookings_table(), self::contact_columns( $input ), [ 'id' => $id ] );
	}

	/**
	 * Sloupce kontaktu a údajů o vozidle, které Provozovatel upravuje.
	 *
	 * @param array{name:string,company:string,phone:string,email:string,plate:string,vehicle:string,note:string,leasing:bool,leasing_company:string,stored_wheels:bool} $input
	 * @return array<string, string|int|null>
	 */
	private static function contact_columns( array $input ): array {
		return [
			'customer_name'    => $input['name'],
			'customer_company' => '' !== $input['company'] ? $input['company'] : null,
			'customer_plate'   => $input['plate'],
			'customer_email'   => strtolower( $input['email'] ),
			'customer_phone'   => $input['phone'],
			'customer_note'    => '' !== $input['note'] ? $input['note'] : null,
			'vehicle'          => '' !== $input['vehicle'] ? $input['vehicle'] : null,
			'leasing'          => $input['leasing'] ? 1 : 0,
			'leasing_company'  => $input['leasing'] ? $input['leasing_company'] : null,
			'stored_wheels'    => $input['stored_wheels'] ? 1 : 0,
		];
	}

	/**
	 * Uložená Rezervace jako vstup pro úpravu.
	 *
	 * @param array<string,mixed> $booking
	 * @return array<string,mixed>
	 */
	private static function input_of( array $booking ): array {
		return [
			'service_ids'     => array_map( 'intval', array_column( $booking['services'], 'service_id' ) ),
			'date'            => $booking['booking_date'],
			'time'            => $booking['time_start'],
			'name'            => $booking['customer_name'],
			'company'         => (string) $booking['customer_company'],
			'phone'           => $booking['customer_phone'],
			'email'           => $booking['customer_email'],
			'plate'           => $booking['customer_plate'],
			'vehicle'         => (string) $booking['vehicle'],
			'note'            => (string) $booking['customer_note'],
			'leasing'         => $booking['leasing'],
			'leasing_company' => (string) $booking['leasing_company'],
			'stored_wheels'   => $booking['stored_wheels'],
		];
	}

	/**
	 * @param array<string,string> $errors
	 * @return array{ok:false,code:string,status:int,errors:array<string,string>}
	 */
	private static function invalid_fields( array $errors ): array {
		return [
			'ok'     => false,
			'code'   => 'booking.invalid_fields',
			'status' => 422,
			'errors' => $errors,
		];
	}

	/**
	 * Ověří a znormalizuje pole. Chyby jsou po polích se stabilními kódy:
	 * required, invalid, too_long. Online jsou povinné i e‑mail, SPZ a souhlas,
	 * Provozovatel je zadat nemusí.
	 *
	 * @param array<mixed> $data
	 * @return array{0:array{service_ids:list<int>,date:string,time:string,name:string,company:string,phone:string,email:string,plate:string,vehicle:string,note:string,leasing:bool,leasing_company:string,stored_wheels:bool,consent_gdpr:bool,refuse_offers:bool},1:array<string,string>}
	 */
	private static function validate_fields( array $data, bool $online ): array {
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
		$check( 'email', $email, $online, static fn( string $v ): bool => (bool) is_email( $v ) );

		$plate = strtoupper( (string) preg_replace( '/[\s\-]/', '', $text( 'plate' ) ) );
		$check( 'plate', $plate, $online, static fn( string $v ): bool => (bool) preg_match( '/^[A-Z0-9]{2,10}$/', $v ) );

		$vehicle = sanitize_text_field( $text( 'vehicle' ) );
		$check( 'vehicle', $vehicle, false );

		$note = sanitize_textarea_field( $text( 'note' ) );
		$check( 'note', $note, false );

		$leasing         = self::checked( $data['leasing'] ?? null );
		$leasing_company = sanitize_text_field( $text( 'leasing_company' ) );
		$check( 'leasing_company', $leasing ? $leasing_company : '', $leasing );

		$consent = self::checked( $data['consent_gdpr'] ?? null );
		if ( $online && ! $consent ) {
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
				'refuse_offers'   => $online && self::checked( $data['refuse_offers'] ?? null ),
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
