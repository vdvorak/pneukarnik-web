<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GET /wp-json/pneukarnik/v1/confirmation/calendar?token=…  soubor .ics s jednou událostí
 * pro Zákazníka: tlačítko „Přidat do kalendáře“ na stránce Potvrzení. Tentýž soubor jde jako
 * příloha potvrzovacího e‑mailu. Chrání ho tajný token stránky Potvrzení; neplatný token,
 * zrušená i anonymizovaná Rezervace vrátí stejnou 404 confirmation.invalid_token.
 *
 * Na rozdíl od feedu Provozovatele (Pneukarnik_Rest_Calendar) bez jména, telefonu, e‑mailu
 * a poznámky Zákazníka: v kalendáři je nepotřebuje a soubor se může dostat dál.
 */
class Pneukarnik_Rest_Booking_Ics {

	public const FILENAME     = 'rezervace.ics';
	public const CONTENT_TYPE = 'text/calendar; charset=UTF-8; method=PUBLISH';

	public function register_routes(): void {
		register_rest_route(
			PNEUKARNIK_REST_NAMESPACE,
			'/confirmation/calendar',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_ics' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	public function get_ics( WP_REST_Request $request ): WP_REST_Response {
		$token   = $request->get_param( 'token' );
		$booking = is_string( $token ) ? Pneukarnik_Booking::find_by_confirmation_token( $token ) : null;
		if ( null === $booking ) {
			$response = new WP_REST_Response(
				[
					'code'    => 'confirmation.invalid_token',
					'message' => 'confirmation.invalid_token',
					'data'    => [ 'status' => 404 ],
				],
				404
			);
			$response->header( 'Cache-Control', 'no-store' );
			return $response;
		}

		return new Pneukarnik_File_Response( self::ics( $booking ), self::CONTENT_TYPE, self::FILENAME, 'attachment' );
	}

	/** Odkaz na soubor .ics ze stránky Potvrzení. */
	public static function url( string $confirmation_token ): string {
		return add_query_arg( 'token', $confirmation_token, rest_url( PNEUKARNIK_REST_NAMESPACE . '/confirmation/calendar' ) );
	}

	/**
	 * Kalendář s jednou událostí pro Rezervaci. UID je stálé, opakovaný import událost
	 * aktualizuje. Odkaz na Zrušení jen s tokenem (zná ho jen potvrzovací e‑mail, v DB je hash)
	 * a jen dokud běží Lhůta zrušení.
	 *
	 * @param array<string,mixed> $booking      Rezervace (Pneukarnik_Booking::get_by_id).
	 * @param string              $cancel_token Token pro odkaz na Zrušení, prázdný = bez odkazu.
	 */
	public static function ics( array $booking, string $cancel_token = '' ): string {
		$site     = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
		$site     = '' !== $site ? $site : Pneukarnik_Contact::company();
		$host     = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$services = array_column( $booking['services'], 'name' );
		$phone    = Pneukarnik_Contact::phone();
		$deadline = Pneukarnik_Cancellation::deadline( $booking );

		$description = array_filter(
			[
				/* translators: %s: názvy Služeb oddělené čárkou */
				sprintf( count( $services ) > 1 ? __( 'Služby: %s', 'pneukarnik-booking' ) : __( 'Služba: %s', 'pneukarnik-booking' ), implode( ', ', $services ) ),
				/* translators: %s: SPZ */
				sprintf( __( 'SPZ: %s', 'pneukarnik-booking' ), $booking['customer_plate'] ),
				/* translators: %s: telefon Provozovatele */
				'' !== $phone ? sprintf( __( 'Telefon: %s', 'pneukarnik-booking' ), $phone ) : '',
				'' !== $cancel_token && $deadline >= Pneukarnik_Clock::now()
					/* translators: 1: den a čas, do kdy jde Rezervaci zrušit, 2: odkaz na Zrušení */
					? sprintf( __( 'Zrušit rezervaci nejpozději %1$s: %2$s', 'pneukarnik-booking' ), $deadline->format( 'j. n. Y \v G:i' ), Pneukarnik_Cancellation::url( $cancel_token ) )
					: '',
			]
		);

		$event = array_filter(
			[
				'UID'         => 'rezervace-' . (int) $booking['id'] . '@' . $host,
				'DTSTAMP'     => Pneukarnik_Ical::now(),
				'DTSTART'     => Pneukarnik_Ical::utc( $booking['booking_date'], $booking['time_start'] ),
				'DTEND'       => Pneukarnik_Ical::utc( $booking['booking_date'], $booking['time_end'] ),
				'SUMMARY'     => Pneukarnik_Ical::text( $site . ': ' . implode( ', ', $services ) ),
				'DESCRIPTION' => Pneukarnik_Ical::text( implode( "\n", $description ) ),
				'LOCATION'    => Pneukarnik_Ical::text( Pneukarnik_Contact::address() ),
				'STATUS'      => 'CONFIRMED',
			],
			static fn( string $value ): bool => '' !== $value
		);

			return Pneukarnik_Ical::calendar(
				[
					'VERSION'  => '2.0',
					'PRODID'   => '-//' . Pneukarnik_Ical::text( $site ) . '//pneukarnik-booking//CS',
					'CALSCALE' => 'GREGORIAN',
					'METHOD'   => 'PUBLISH',
				],
				[ $event ]
			);
	}
}
