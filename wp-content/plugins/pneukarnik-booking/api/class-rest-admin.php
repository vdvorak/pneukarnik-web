<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST pro Provozovatele (administrace). Prohlížet: Pneukarnik_Access::can_view(),
 * měnit: can_manage(). Bez oprávnění WordPress odmítne s rest_forbidden (401/403).
 *
 * GET   /admin/calendar?from=&to=          dny s efektivní Pracovní dobou a potvrzenými Rezervacemi
 * GET   /admin/bookings?from=&to=&status=&search=&page=   seznam s filtry
 * POST  /admin/bookings                    telefonická objednávka (zdroj „provozovatel“)
 * GET   /admin/bookings/{id}               detail
 * PATCH /admin/bookings/{id}               úprava (kontakt, poznámka, Služby, Termín)
 * POST  /admin/bookings/{id}/cancel {reason}   Zrušení Provozovatelem, bez Lhůty
 * GET   /admin/day-sheet?date=             PDF denní přehled k tisku (odkaz s _wpnonce)
 *
 * Mimo Pracovní dobu se zadání i přesun odmítne kódem booking.outside_working_hours,
 * dokud nepřijde outside_working_hours: true. Překryv (booking.slot_taken) nikdy.
 * Odpovědi obsahují osobní údaje, proto Cache-Control: no-store.
 */
class Pneukarnik_Rest_Admin {

	/** Nejdelší období kalendáře ve dnech (šest týdnů). */
	public const MAX_CALENDAR_DAYS = 42;

	public const PER_PAGE = 25;

	public function register_routes(): void {
		$view   = [ Pneukarnik_Access::class, 'can_view' ];
		$manage = [ Pneukarnik_Access::class, 'can_manage' ];
		$id     = [
			'id' => [
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			],
		];

		register_rest_route(
			PNEUKARNIK_REST_NAMESPACE,
			'/admin/calendar',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'calendar' ],
				'permission_callback' => $view,
			]
		);
		register_rest_route(
			PNEUKARNIK_REST_NAMESPACE,
			'/admin/day-sheet',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'day_sheet' ],
				'permission_callback' => $view,
			]
		);
		register_rest_route(
			PNEUKARNIK_REST_NAMESPACE,
			'/admin/bookings',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'list_bookings' ],
					'permission_callback' => $view,
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_booking' ],
					'permission_callback' => $manage,
				],
			]
		);
		register_rest_route(
			PNEUKARNIK_REST_NAMESPACE,
			'/admin/bookings/(?P<id>\d+)',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_booking' ],
					'permission_callback' => $view,
					'args'                => $id,
				],
				[
					'methods'             => 'PATCH',
					'callback'            => [ $this, 'update_booking' ],
					'permission_callback' => $manage,
					'args'                => $id,
				],
			]
		);
		register_rest_route(
			PNEUKARNIK_REST_NAMESPACE,
			'/admin/bookings/(?P<id>\d+)/cancel',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'cancel_booking' ],
				'permission_callback' => $manage,
				'args'                => $id,
			]
		);
	}

	public function calendar( WP_REST_Request $request ): WP_REST_Response {
		$from = self::date_param( $request->get_param( 'from' ) );
		$to   = self::date_param( $request->get_param( 'to' ) );
		if ( null === $from || null === $to || $to < $from || $from->diff( $to )->days >= self::MAX_CALENDAR_DAYS ) {
			return self::refusal( 'calendar.invalid_range', 400 );
		}

		$bookings = [];
		foreach ( Pneukarnik_Booking::confirmed_between( $from->format( 'Y-m-d' ), $to->format( 'Y-m-d' ) ) as $booking ) {
			$bookings[ $booking['booking_date'] ][] = self::view( $booking );
		}
		$days = [];
		for ( $day = $from; $day <= $to; $day = $day->modify( '+1 day' ) ) {
			$date      = $day->format( 'Y-m-d' );
			$exception = Pneukarnik_Day_Exceptions::for_date( $date );
			$days[]    = [
				'date'     => $date,
				'hours'    => Pneukarnik_Slot_Engine::resolve_effective_hours( $date ),
				'note'     => $exception['note'] ?? '',
				'bookings' => $bookings[ $date ] ?? [],
			];
		}
		return self::no_store( new WP_REST_Response( [ 'days' => $days ], 200 ) );
	}

	public function day_sheet( WP_REST_Request $request ): WP_REST_Response {
		$date = self::date_param( $request->get_param( 'date' ) );
		if ( null === $date ) {
			return self::refusal( 'day_sheet.invalid_date', 400 );
		}
		$ymd = $date->format( 'Y-m-d' );
		return new Pneukarnik_File_Response( Pneukarnik_Day_Sheet::render( $ymd ), 'application/pdf', "rezervace-{$ymd}.pdf" );
	}

	/**
	 * Odkaz na PDF přehled dne pro přihlášeného (nonce WordPress REST v adrese).
	 */
	public static function day_sheet_url( string $date ): string {
		return add_query_arg(
			[
				'date'     => $date,
				'_wpnonce' => wp_create_nonce( 'wp_rest' ),
			],
			rest_url( PNEUKARNIK_REST_NAMESPACE . '/admin/day-sheet' )
		);
	}

	public function list_bookings( WP_REST_Request $request ): WP_REST_Response {
		$from   = $request->get_param( 'from' );
		$to     = $request->get_param( 'to' );
		$status = strtoupper( (string) $request->get_param( 'status' ) );
		$errors = [];
		if ( null !== $from && '' !== $from && null === self::date_param( $from ) ) {
			$errors['from'] = 'invalid';
		}
		if ( null !== $to && '' !== $to && null === self::date_param( $to ) ) {
			$errors['to'] = 'invalid';
		}
		if ( ! in_array( $status, [ '', Pneukarnik_Booking::STATUS_CONFIRMED, Pneukarnik_Booking::STATUS_CANCELLED ], true ) ) {
			$errors['status'] = 'invalid';
		}
		if ( $errors ) {
			return self::refusal( 'bookings.invalid_filters', 400, [ 'errors' => $errors ] );
		}

		$page   = max( 1, (int) $request->get_param( 'page' ) );
		$result = Pneukarnik_Booking::search(
			[
				'from'   => (string) $from,
				'to'     => (string) $to,
				'status' => $status,
				'search' => is_string( $request->get_param( 'search' ) ) ? sanitize_text_field( $request->get_param( 'search' ) ) : '',
			],
			$page,
			self::PER_PAGE
		);
		return self::no_store(
			new WP_REST_Response(
				[
					'bookings' => array_map( [ self::class, 'view' ], $result['bookings'] ),
					'total'    => $result['total'],
					'page'     => $page,
					'pages'    => (int) ceil( $result['total'] / self::PER_PAGE ),
				],
				200
			)
		);
	}

	public function create_booking( WP_REST_Request $request ): WP_REST_Response {
		$result = Pneukarnik_Booking::create( self::body( $request ), Pneukarnik_Booking::SOURCE_PROVOZOVATEL );
		return $result['ok'] ? self::booking( $result['booking'], 201 ) : self::failure( $result );
	}

	public function get_booking( WP_REST_Request $request ): WP_REST_Response {
		$booking = Pneukarnik_Booking::get_by_id( (int) $request->get_param( 'id' ) );
		return null === $booking ? self::refusal( 'booking.not_found', 404 ) : self::booking( $booking, 200 );
	}

	public function update_booking( WP_REST_Request $request ): WP_REST_Response {
		$result = Pneukarnik_Booking::update( (int) $request->get_param( 'id' ), self::body( $request ) );
		return $result['ok'] ? self::booking( $result['booking'], 200 ) : self::failure( $result );
	}

	public function cancel_booking( WP_REST_Request $request ): WP_REST_Response {
		$body   = self::body( $request );
		$reason = is_string( $body['reason'] ?? null ) ? sanitize_text_field( $body['reason'] ) : '';
		$result = Pneukarnik_Cancellation::cancel_by_provozovatel( (int) $request->get_param( 'id' ), '' !== $reason ? mb_substr( $reason, 0, 255 ) : null );
		if ( ! $result['ok'] ) {
			return self::refusal( $result['code'], $result['status'] );
		}
		return self::no_store(
			new WP_REST_Response(
				[
					'code'    => $result['code'],
					'booking' => self::view( $result['booking'] ),
				],
				200
			)
		);
	}

	/**
	 * Rezervace, jak ji vidí Provozovatel. Pole kontaktu se jmenují stejně jako při zadání.
	 *
	 * @param array<string,mixed> $booking
	 * @return array<string,mixed>
	 */
	public static function view( array $booking ): array {
		return [
			'id'              => $booking['id'],
			'status'          => $booking['status'],
			'source'          => $booking['source'],
			'date'            => $booking['booking_date'],
			'time_start'      => $booking['time_start'],
			'time_end'        => $booking['time_end'],
			'services'        => array_map(
				static fn( array $service ): array => [
					'id'         => $service['service_id'],
					'name'       => $service['name'],
					'duration'   => $service['duration'],
					'price'      => $service['price'],
					'price_from' => $service['price_from'],
				],
				$booking['services']
			),
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
			'created_at'      => $booking['created_at'],
			'cancelled_at'    => $booking['cancelled_at'],
			'cancel_reason'   => (string) $booking['cancel_reason'],
		];
	}

	/**
	 * @return array<mixed>
	 */
	private static function body( WP_REST_Request $request ): array {
		/** @var mixed $data Tělo může být i jiná JSON hodnota než objekt. */
		$data = $request->get_json_params();
		return is_array( $data ) ? $data : [];
	}

	private static function date_param( mixed $value ): ?DateTimeImmutable {
		if ( ! is_string( $value ) || ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
			return null;
		}
		return Pneukarnik_Clock::at( $value );
	}

	/**
	 * @param array<string,mixed> $booking
	 */
	private static function booking( array $booking, int $status ): WP_REST_Response {
		return self::no_store( new WP_REST_Response( [ 'booking' => self::view( $booking ) ], $status ) );
	}

	/**
	 * @param array{ok:false,code:string,status:int,errors?:array<string,string>} $result
	 */
	private static function failure( array $result ): WP_REST_Response {
		return self::refusal( $result['code'], $result['status'], isset( $result['errors'] ) ? [ 'errors' => $result['errors'] ] : [] );
	}

	/**
	 * @param array<string,mixed> $data
	 */
	private static function refusal( string $code, int $status, array $data = [] ): WP_REST_Response {
		return self::no_store(
			new WP_REST_Response(
				[
					'code'    => $code,
					'message' => $code,
					'data'    => [ 'status' => $status ] + $data,
				],
				$status
			)
		);
	}

	private static function no_store( WP_REST_Response $response ): WP_REST_Response {
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}
}
