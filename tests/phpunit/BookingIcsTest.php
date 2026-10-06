<?php
/**
 * Rezervace do kalendáře Zákazníka: soubor .ics ze stránky Potvrzení (chráněný jejím tokenem)
 * a příloha potvrzovacího e‑mailu. Jedna událost v UTC, bez osobních údajů Zákazníka.
 */

declare(strict_types=1);

class BookingIcsTest extends Pneukarnik_REST_Test_Case {

	private const CUSTOMER = 'jan@example.test';

	private int $tyres;
	private int $balancing;

	public function set_up(): void {
		parent::set_up();
		Pneukarnik_Clock::freeze( '2027-02-20 12:00' );
		$this->set_working_hours_every_day(
			[
				[
					'from' => '08:00',
					'to'   => '12:00',
				],
			]
		);
		$this->set_booking_rules( 30 );
		update_option( 'blogname', 'Pneuservis Kárník' );
		update_option( 'pneukarnik_cancellation_hours', 24 );
		update_option( 'pneukarnik_phone', '+420 775 565 326' );
		update_option( 'pneukarnik_email', 'servis@example.test' );
		update_option( 'pneukarnik_address', 'Dobšická 10, 669 02 Znojmo' );
		$this->tyres     = $this->create_service( 60, false, 'Přezutí' );
		$this->balancing = $this->create_service( 30, false, 'Vyvážení' );
		$this->capture_mails();
	}

	/**
	 * @return array<string, array{string, string, string}>
	 */
	public static function termins_around_daylight_saving(): array {
		// Letní čas začíná v neděli 28. 3. 2027.
		return [
			'zimní čas' => [ '2027-03-25', '20270325T080000Z', '20270325T093000Z' ],
			'letní čas' => [ '2027-03-30', '20270330T070000Z', '20270330T083000Z' ],
		];
	}

	/**
	 * @dataProvider termins_around_daylight_saving
	 */
	public function test_confirmation_page_offers_one_event_in_utc( string $date, string $start, string $end ): void {
		$response = $this->book( [ $this->tyres, $this->balancing ], $date, '09:00' );
		$booking  = $this->created_booking( $response );

		$ics = $this->rest( 'GET', '/confirmation/calendar', [ 'token' => self::confirmation_token( $response ) ] );

		$this->assertSame( 200, $ics->get_status() );
		$this->assertSame( 'text/calendar; charset=UTF-8; method=PUBLISH', $ics->get_headers()['Content-Type'] );
		$this->assertSame( 'attachment; filename="rezervace.ics"', $ics->get_headers()['Content-Disposition'] );
		$this->assertSame( 'no-store', $ics->get_headers()['Cache-Control'] );
		$event = self::event( (string) $ics->get_data() );
		$this->assertSame( 'rezervace-' . $booking['id'] . '@example.org', $event['UID'] );
		$this->assertSame( $start, $event['DTSTART'] );
		$this->assertSame( $end, $event['DTEND'] );
		$this->assertSame( 'Pneuservis Kárník: Přezutí\, Vyvážení', $event['SUMMARY'] );
		$this->assertSame( 'Dobšická 10\, 669 02 Znojmo', $event['LOCATION'] );
		$this->assertSame( 'Služby: Přezutí\, Vyvážení\nSPZ: 1AB2345\nTelefon: +420 775 565 326', $event['DESCRIPTION'] );
	}

	public function test_confirmation_email_attaches_the_same_event_with_the_cancel_link(): void {
		$response = $this->book( $this->tyres, '2027-03-01', '09:00' );
		$mail     = $this->mail_to( self::CUSTOMER );
		$download = self::event( (string) $this->rest( 'GET', '/confirmation/calendar', [ 'token' => self::confirmation_token( $response ) ] )->get_data() );

		$this->assertCount( 1, $mail['attachments'] );
		$this->assertSame( 'rezervace.ics', $mail['attachments'][0]['filename'] );
		$this->assertSame( 'text/calendar; charset=UTF-8; method=PUBLISH', $mail['attachments'][0]['type'] );
		$attached = self::event( $mail['attachments'][0]['content'] );
		$this->assertSame(
			array_diff_key( $download, [ 'DESCRIPTION' => 0 ] ),
			array_diff_key( $attached, [ 'DESCRIPTION' => 0 ] )
		);
		$cancel = Pneukarnik_Cancellation::url( $this->cancel_token_from( $mail ) );
		$this->assertSame( $download['DESCRIPTION'] . '\nZrušit rezervaci nejpozději 28. 2. 2027 v 9:00: ' . $cancel, $attached['DESCRIPTION'] );
		foreach ( [ $mail['html'], $mail['text'] ] as $body ) {
			$this->assertStringNotContainsString( 'VCALENDAR', $body );
		}
	}

	public function test_attachment_has_no_cancel_link_after_the_cancellation_deadline(): void {
		Pneukarnik_Clock::freeze( '2027-02-28 12:00' );
		$this->log_in_as( 'pneukarnik_manager' );
		$this->admin_booking( $this->admin_book( $this->tyres, '2027-03-01', '09:00', [ 'email' => self::CUSTOMER ] ) );

		$attached = self::event( $this->mail_to( self::CUSTOMER )['attachments'][0]['content'] );

		$this->assertSame( '20270301T080000Z', $attached['DTSTART'] );
		$this->assertStringNotContainsString( 'Zrušit', $attached['DESCRIPTION'] );
	}

	public function test_other_emails_have_no_attachment(): void {
		$this->book( $this->tyres, '2027-03-01', '09:00' );
		$this->cancel( $this->cancel_token_from( $this->mail_to( self::CUSTOMER ) ) );

		$this->assertCount( 4, $this->mails ); // Potvrzení a Zrušení Zákazníkovi i Provozovateli.
		$with_attachment = array_filter( $this->mails, static fn( array $mail ): bool => [] !== $mail['attachments'] );
		$this->assertSame( [ 'Potvrzení rezervace na pondělí 1. 3. 2027 v 9:00' ], array_column( $with_attachment, 'subject' ) );
	}

	public function test_event_has_no_personal_data_of_the_customer(): void {
		$response   = $this->book(
			$this->tyres,
			'2027-03-01',
			'09:00',
			[
				'name'    => 'Jan Novák',
				'company' => 'Novák s.r.o.',
				'phone'   => '+420 603 123 456',
				'note'    => 'Volejte po obědě',
				'vehicle' => 'Škoda Fabia',
			]
		);
		$downloaded = (string) $this->rest( 'GET', '/confirmation/calendar', [ 'token' => self::confirmation_token( $response ) ] )->get_data();
		$attached   = $this->mail_to( self::CUSTOMER )['attachments'][0]['content'];

		foreach ( [ $downloaded, $attached ] as $ics ) {
			$unfolded = str_replace( "\r\n ", '', $ics );
			foreach ( [ 'Novák', 'jan@example', '603', 'Volejte', 'Fabia' ] as $personal ) {
				$this->assertStringNotContainsString( $personal, $unfolded );
			}
		}
	}

	public function test_service_names_are_escaped_and_long_lines_folded_without_breaking_characters(): void {
		$response = $this->book( [ $this->tyres, $this->balancing ], '2027-03-01', '09:00' );
		$booking  = $this->created_booking( $response );
		global $wpdb;
		$wpdb->update(
			Pneukarnik_DB::booking_services_table(),
			[ 'service_name' => "Přezutí; zimní, letní \\ obě\r\nEND:VEVENT ěščřžýáíéůú ěščřžýáíéůú" ],
			[
				'booking_id' => $booking['id'],
				'position'   => 0,
			]
		);

		$ics = (string) $this->rest( 'GET', '/confirmation/calendar', [ 'token' => self::confirmation_token( $response ) ] )->get_data();

		$this->assertSame( 1, substr_count( $ics, "\r\nBEGIN:VEVENT\r\n" ) );
		$this->assertSame( 1, substr_count( $ics, "\r\nEND:VEVENT\r\n" ) );
		$this->assertSame(
			'Pneuservis Kárník: Přezutí\; zimní\, letní \\\\ obě\nEND:VEVENT ěščřžýáíéůú ěščřžýáíéůú\, Vyvážení',
			self::event( $ics )['SUMMARY']
		);
		foreach ( explode( "\r\n", rtrim( $ics, "\r\n" ) ) as $line ) {
			// Pokračování začíná mezerou, za ní nejvýš 75 oktetů jako ve feedu Provozovatele.
			$this->assertLessThanOrEqual( 75, strlen( str_starts_with( $line, ' ' ) ? substr( $line, 1 ) : $line ), $line );
			$this->assertTrue( mb_check_encoding( $line, 'UTF-8' ), $line );
		}
	}

	/**
	 * @return array<string, array{mixed}>
	 */
	public static function invalid_tokens(): array {
		return [
			'bez tokenu' => [ null ],
			'prázdný'    => [ '' ],
			'cizí'       => [ str_repeat( 'a', 64 ) ],
			'pole'       => [ [ 'token' ] ],
		];
	}

	/**
	 * @dataProvider invalid_tokens
	 */
	public function test_invalid_token_gets_404_without_any_booking( mixed $token ): void {
		$this->book( $this->tyres, '2027-03-01', '09:00' );

		$response = $this->rest( 'GET', '/confirmation/calendar', null === $token ? [] : [ 'token' => $token ] );

		self::assert_not_found( $response );
	}

	public function test_cancelled_booking_gets_404(): void {
		$response = $this->book( $this->tyres, '2027-03-01', '09:00' );
		$this->assertSame( 200, $this->cancel( $this->cancel_token_from( $this->mail_to( self::CUSTOMER ) ) )->get_status() );

		self::assert_not_found( $this->rest( 'GET', '/confirmation/calendar', [ 'token' => self::confirmation_token( $response ) ] ) );
	}

	public function test_anonymised_booking_gets_404(): void {
		$response = $this->book( $this->tyres, '2027-03-01', '09:00' );
		$token    = self::confirmation_token( $response );
		$this->assertSame( 200, $this->rest( 'GET', '/confirmation/calendar', [ 'token' => $token ] )->get_status() );

		Pneukarnik_Clock::freeze( '2028-03-02 03:00' );
		do_action( Pneukarnik_GDPR::CRON_HOOK );

		self::assert_not_found( $this->rest( 'GET', '/confirmation/calendar', [ 'token' => $token ] ) );
	}

	private static function assert_not_found( WP_REST_Response $response ): void {
		self::assertSame( 404, $response->get_status() );
		self::assertSame( 'confirmation.invalid_token', $response->get_data()['code'] );
		self::assertStringNotContainsString( 'BEGIN:', (string) wp_json_encode( $response->get_data() ) );
	}

	private static function confirmation_token( WP_REST_Response $response ): string {
		parse_str( (string) wp_parse_url( $response->get_data()['confirmation_url'], PHP_URL_QUERY ), $query );
		return (string) $query['r'];
	}

	/**
	 * Vlastnosti jediné události kalendáře po rozložení zalomených řádků.
	 *
	 * @return array<string, string>
	 */
	private static function event( string $ics ): array {
		self::assertSame( 1, preg_match_all( "~\r\nBEGIN:VEVENT\r\n(.*?)\r\nEND:VEVENT\r\n~s", str_replace( "\r\n ", '', $ics ), $matches ) );
		$event = [];
		foreach ( explode( "\r\n", $matches[1][0] ) as $line ) {
			[ $name, $value ] = explode( ':', $line, 2 );
			$event[ $name ]   = $value;
		}
		unset( $event['DTSTAMP'] );
		return $event;
	}
}
