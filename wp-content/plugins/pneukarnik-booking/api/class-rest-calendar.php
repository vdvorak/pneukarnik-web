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
		$utc     = new \DateTimeZone( 'UTC' );
		$now_utc = Pneukarnik_Clock::now()->setTimezone( $utc )->format( 'Ymd\THis\Z' );
		$site    = get_bloginfo( 'name' );
		$host    = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$address = trim( (string) get_option( 'pneukarnik_address', '' ) );

		$lines = [
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//' . self::escape_text( $site ) . '//pneukarnik-booking//CS',
			self::fold( 'X-WR-CALNAME:' . self::escape_text( 'Rezervace – ' . $site ) ),
			'X-WR-TIMEZONE:' . Pneukarnik_Clock::TIMEZONE,
			'CALSCALE:GREGORIAN',
			'METHOD:PUBLISH',
		];

		foreach ( $bookings as $b ) {
			// Datum a čas z DB jsou místní čas v Europe/Prague, iCal je dostane v UTC.
			$dtstart = Pneukarnik_Clock::at( $b['booking_date'] . ' ' . $b['time_start'] )->setTimezone( $utc )->format( 'Ymd\THis\Z' );
			$dtend   = Pneukarnik_Clock::at( $b['booking_date'] . ' ' . $b['time_end'] )->setTimezone( $utc )->format( 'Ymd\THis\Z' );

			$service     = '' !== $b['service_name'] ? $b['service_name'] : 'Rezervace';
			$desc_parts  = array_filter(
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
			$description = implode( '\n', array_map( [ self::class, 'escape_text' ], $desc_parts ) );

			$lines[] = 'BEGIN:VEVENT';
			$lines[] = 'UID:booking-' . (int) $b['id'] . '@' . $host;
			$lines[] = 'DTSTAMP:' . $now_utc;
			$lines[] = 'DTSTART:' . $dtstart;
			$lines[] = 'DTEND:' . $dtend;
			$lines[] = self::fold( 'SUMMARY:' . self::escape_text( $service . ' — ' . $b['customer_name'] ) );
			$lines[] = self::fold( 'DESCRIPTION:' . $description );
			if ( '' !== $address ) {
				$lines[] = self::fold( 'LOCATION:' . self::escape_text( $address ) );
			}
			$lines[] = self::fold( 'URL:' . Pneukarnik_Admin_Calendar::url( $b['booking_date'], (int) $b['id'] ) );
			$lines[] = 'STATUS:CONFIRMED';
			$lines[] = 'END:VEVENT';
		}

		$lines[] = 'END:VCALENDAR';

		return implode( "\r\n", $lines ) . "\r\n";
	}

	/**
	 * Escapování hodnoty TEXT podle RFC 5545: zpětné lomítko, středník, čárka a konce řádků.
	 * Text od Zákazníka tak nemůže přidat vlastní řádky ani události.
	 */
	private static function escape_text( string $text ): string {
		$text = str_replace( [ '\\', ';', ',' ], [ '\\\\', '\\;', '\\,' ], $text );
		return str_replace( [ "\r\n", "\r", "\n" ], '\\n', $text );
	}

	/**
	 * Zalomení řádku podle RFC 5545: nejvýš 75 oktetů, pokračování CRLF + mezera.
	 * mb_strcut nerozdělí vícebajtový znak UTF-8 (čeština) mezi dva řádky.
	 */
	private static function fold( string $line ): string {
		$output = '';
		$length = strlen( $line );
		while ( $length > 75 ) {
			$chunk   = mb_strcut( $line, 0, 75, 'UTF-8' );
			$output .= $chunk . "\r\n ";
			$line    = substr( $line, strlen( $chunk ) );
			$length  = strlen( $line );
		}
		return $output . $line;
	}
}
