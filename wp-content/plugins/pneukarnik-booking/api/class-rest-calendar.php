<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GET /wp-json/pneukarnik/v1/calendar?token=…  iCal feed potvrzených Rezervací od dneška
 * pro kalendář v telefonu Provozovatele. Chrání ho tajný token v odkazu (option
 * pneukarnik_ical_token), který jde v Nastavení přegenerovat; starý odkaz pak vrátí 401
 * ical.invalid_token. Časy jsou v UTC, převedené z místního času Europe/Prague.
 */
class Pneukarnik_Rest_Calendar {

	private const OPTION = 'pneukarnik_ical_token';

	public function register_routes(): void {
		register_rest_route(
			PNEUKARNIK_REST_NAMESPACE,
			'/calendar',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_calendar' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	public function get_calendar( WP_REST_Request $request ): WP_REST_Response {
		$token  = $request->get_param( 'token' );
		$stored = (string) get_option( self::OPTION, '' );
		if ( '' === $stored || ! is_string( $token ) || ! hash_equals( $stored, $token ) ) {
			$response = new WP_REST_Response(
				[
					'code'    => 'ical.invalid_token',
					'message' => 'ical.invalid_token',
					'data'    => [ 'status' => 401 ],
				],
				401
			);
			$response->header( 'Cache-Control', 'no-store' );
			return $response;
		}

		$bookings = Pneukarnik_Booking::confirmed_between( Pneukarnik_Clock::today()->format( 'Y-m-d' ), '9999-12-31' );
		return new Pneukarnik_File_Response( self::build_ical( $bookings ), 'text/calendar; charset=UTF-8', 'rezervace.ics' );
	}

	public static function url(): string {
		return add_query_arg( 'token', self::get_or_create_token(), rest_url( PNEUKARNIK_REST_NAMESPACE . '/calendar' ) );
	}

	public static function get_or_create_token(): string {
		$token = (string) get_option( self::OPTION, '' );
		return '' !== $token ? $token : self::regenerate_token();
	}

	/**
	 * Nový tajný token, starý odkaz tím přestane fungovat.
	 */
	public static function regenerate_token(): string {
		$token = bin2hex( random_bytes( 32 ) );
		update_option( self::OPTION, $token, false );
		return $token;
	}

	/**
	 * @param list<array<string,mixed>> $bookings
	 */
	private static function build_ical( array $bookings ): string {
		$now     = Pneukarnik_Ical::now();
		$site    = get_bloginfo( 'name' );
		$host    = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$address = Pneukarnik_Contact::address();

		$events = [];
		foreach ( $bookings as $b ) {
			$service    = '' !== $b['service_name'] ? $b['service_name'] : 'Rezervace';
			$desc_parts = array_filter(
				[
					'SPZ: ' . $b['customer_plate'],
					$b['vehicle'] ? 'Vozidlo: ' . $b['vehicle'] : '',
					'Telefon: ' . $b['customer_phone'],
					$b['customer_company'] ? 'Firma: ' . $b['customer_company'] : '',
					$b['leasing'] ? 'Leasing: ' . ( $b['leasing_company'] ?: 'ano' ) : '',
					$b['stored_wheels'] ? 'Kola uskladněná u nás' : '',
					$b['customer_note'] ? 'Poznámka: ' . $b['customer_note'] : '',
				]
			);

			$events[] = array_filter(
				[
					'UID'         => 'booking-' . (int) $b['id'] . '@' . $host,
					'DTSTAMP'     => $now,
					'DTSTART'     => Pneukarnik_Ical::utc( $b['booking_date'], $b['time_start'] ),
					'DTEND'       => Pneukarnik_Ical::utc( $b['booking_date'], $b['time_end'] ),
					'SUMMARY'     => Pneukarnik_Ical::text( $service . ' — ' . $b['customer_name'] ),
					'DESCRIPTION' => Pneukarnik_Ical::text( implode( "\n", $desc_parts ) ),
					'LOCATION'    => Pneukarnik_Ical::text( $address ),
					'URL'         => Pneukarnik_Admin_Calendar::url( $b['booking_date'], (int) $b['id'] ),
					'STATUS'      => 'CONFIRMED',
				],
				static fn( string $value ): bool => '' !== $value
			);
		}

		return Pneukarnik_Ical::calendar(
			[
				'VERSION'       => '2.0',
				'PRODID'        => '-//' . Pneukarnik_Ical::text( $site ) . '//pneukarnik-booking//CS',
				'X-WR-CALNAME'  => Pneukarnik_Ical::text( 'Rezervace – ' . $site ),
				'X-WR-TIMEZONE' => Pneukarnik_Clock::TIMEZONE,
				'CALSCALE'      => 'GREGORIAN',
				'METHOD'        => 'PUBLISH',
			],
			$events
		);
	}
}
