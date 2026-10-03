<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GET /wp-json/pneukarnik/v1/calendar?token=xxx
 * Vrátí iCal feed (.ics) potvrzených rezervací od dnešního dne.
 * Autentizace přes static token (WP option pneukarnik_ical_token).
 *
 * Výstup je zachycen přes rest_pre_serve_request filtr — REST API jinak
 * JSON-enkóduje string odpověď.
 */
class Pneukarnik_Rest_Calendar {

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

		add_filter( 'rest_pre_serve_request', [ $this, 'serve_ical' ], 10, 2 );
	}

	public function get_calendar( WP_REST_Request $request ): WP_REST_Response {
		$token        = sanitize_text_field( wp_unslash( $request->get_param( 'token' ) ?? '' ) );
		$stored_token = get_option( 'pneukarnik_ical_token', '' );

		if ( ! $stored_token || ! hash_equals( $stored_token, $token ) ) {
			return new WP_REST_Response(
				[
					'code'    => 'unauthorized',
					'message' => 'Neplatný token.',
				],
				401
			);
		}

		global $wpdb;
		$table = Pneukarnik_DB::bookings_table();
		$today = Pneukarnik_Clock::today()->format( 'Y-m-d' );

		$bookings = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i
				 WHERE booking_date >= %s
				   AND status = 'CONFIRMED'
				 ORDER BY booking_date ASC, time_start ASC",
				$table,
				$today
			),
			ARRAY_A
		);

		$address = get_option( 'pneukarnik_address', 'Jan Kárník Autoservis' );
		$ical    = self::build_ical( Pneukarnik_Booking::with_service_names( $bookings ?: [] ), $address );

		// Marker aby serve_ical věděl, že jde o ical response
		$response = new WP_REST_Response( $ical, 200 );
		$response->header( 'X-Pnk-Ical', '1' );
		return $response;
	}

	public function serve_ical( bool $served, WP_REST_Response $result ): bool {
		$headers = $result->get_headers();
		if ( ( $headers['X-Pnk-Ical'] ?? '' ) !== '1' ) {
			return $served;
		}
		$data = $result->get_data();
		if ( ! is_string( $data ) ) {
			return $served;
		}
		header( 'Content-Type: text/calendar; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="rezervace.ics"' );
		header( 'Cache-Control: no-store, no-cache' );
		echo $data; // phpcs:ignore WordPress.Security.EscapeOutput
		return true;
	}

	public static function get_or_create_token(): string {
		$token = get_option( 'pneukarnik_ical_token', '' );
		if ( ! $token ) {
			// Use cryptographically secure random source — consistent with cancel token pattern.
			$token = bin2hex( random_bytes( 32 ) );
			update_option( 'pneukarnik_ical_token', $token );
		}
		return $token;
	}

	public static function regenerate_token(): string {
		// Use cryptographically secure random source — consistent with cancel token pattern.
		$token = bin2hex( random_bytes( 32 ) );
		update_option( 'pneukarnik_ical_token', $token );
		return $token;
	}

	private static function build_ical( array $bookings, string $address ): string {
		$utc     = new \DateTimeZone( 'UTC' );
		$now_utc = Pneukarnik_Clock::now()->setTimezone( $utc )->format( 'Ymd\THis\Z' );

		$lines = [
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//Jan Kárník Autoservis//pneukarnik.cz//CS',
			'X-WR-CALNAME:Rezervace — Jan Kárník Autoservis',
			'X-WR-TIMEZONE:Europe/Prague',
			'CALSCALE:GREGORIAN',
			'METHOD:PUBLISH',
		];

		foreach ( $bookings as $b ) {
			// Datum a čas z DB jsou místní čas v Europe/Prague, iCal je chce v UTC.
			$dtstart = Pneukarnik_Clock::at( $b['booking_date'] . ' ' . $b['time_start'] )->setTimezone( $utc )->format( 'Ymd\THis\Z' );
			$dtend   = Pneukarnik_Clock::at( $b['booking_date'] . ' ' . $b['time_end'] )->setTimezone( $utc )->format( 'Ymd\THis\Z' );

			$service  = '' !== $b['service_name'] ? $b['service_name'] : 'Rezervace';
			$customer = $b['customer_name'];
			$plate    = $b['customer_plate'];
			$phone    = $b['customer_phone'];
			$note     = $b['customer_note'] ?? '';

			$desc_parts = [ 'SPZ: ' . $plate, 'Telefon: ' . $phone ];
			if ( $b['customer_company'] ) {
				$desc_parts[] = 'Firma: ' . $b['customer_company'];
			}
			if ( $note ) {
				$desc_parts[] = 'Poznámka: ' . $note;
			}
			$description = implode( '\n', array_map( [ self::class, 'escape_text' ], $desc_parts ) );

			$lines[] = 'BEGIN:VEVENT';
			$lines[] = 'UID:booking-' . (int) $b['id'] . '@pneukarnik.cz';
			$lines[] = 'DTSTAMP:' . $now_utc;
			$lines[] = 'DTSTART:' . $dtstart;
			$lines[] = 'DTEND:' . $dtend;
			$lines[] = self::fold( 'SUMMARY:' . self::escape_text( $service . ' — ' . $customer ) );
			$lines[] = self::fold( 'DESCRIPTION:' . $description );
			$lines[] = self::fold( 'LOCATION:' . self::escape_text( $address ) );
			$lines[] = 'STATUS:CONFIRMED';
			$lines[] = 'END:VEVENT';
		}

		$lines[] = 'END:VCALENDAR';

		return implode( "\r\n", $lines ) . "\r\n";
	}

	// RFC 5545 line folding: max 75 octets per line, continuation with CRLF + space.
	// mb_strcut nerozdělí vícebajtový znak UTF-8 (čeština) mezi dva řádky.
	/**
	 * Escapování hodnoty TEXT podle RFC 5545: zpětné lomítko, středník, čárka a konce řádků.
	 * Text od Zákazníka tak nemůže přidat vlastní řádky ani události.
	 */
	private static function escape_text( string $text ): string {
		$text = str_replace( [ '\\', ';', ',' ], [ '\\\\', '\\;', '\\,' ], $text );
		return str_replace( [ "\r\n", "\r", "\n" ], '\\n', $text );
	}

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
