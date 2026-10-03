<?php
/**
 * iCal feed Rezervací pro telefon Provozovatele: jen s platným tajným tokenem, potvrzené
 * Rezervace od dneška se začátkem a koncem podle Europe/Prague, text od Zákazníka nesmí
 * změnit strukturu kalendáře.
 */

declare(strict_types=1);

class CalendarFeedTest extends Pneukarnik_REST_Test_Case {

	public function test_customer_text_is_escaped_and_cannot_inject_events(): void {
		Pneukarnik_Clock::freeze( '2027-02-26 12:00' );
		$this->set_working_hours_every_day(
			[
				[
					'from' => '08:00',
					'to'   => '12:00',
				],
			]
		);
		$this->set_booking_rules( 30 );
		$injection = "Pozor\r\nEND:VEVENT\r\nBEGIN:VEVENT\r\nSUMMARY:Podvrh; a, b\\c";
		$this->assertSame(
			201,
			$this->book( $this->create_service( 60 ), '2027-03-01', '09:00', [ 'note' => $injection ] )->get_status()
		);
		update_option( 'pneukarnik_ical_token', 'tajny-token' );

		$ical = (string) $this->rest( 'GET', '/calendar', [ 'token' => 'tajny-token' ] )->get_data();

		$this->assertSame( 1, substr_count( $ical, "\r\nBEGIN:VEVENT\r\n" ) );
		$unfolded = str_replace( "\r\n ", '', $ical );
		$this->assertStringContainsString( 'Poznámka: Pozor\nEND:VEVENT\nBEGIN:VEVENT\nSUMMARY:Podvrh\; a\, b\\\\c', $unfolded );
	}

	public function test_event_names_all_services_of_the_booking(): void {
		Pneukarnik_Clock::freeze( '2027-02-26 12:00' );
		$this->set_working_hours_every_day(
			[
				[
					'from' => '08:00',
					'to'   => '12:00',
				],
			]
		);
		$this->set_booking_rules( 30 );
		$services = [ $this->create_service( 60, false, 'Přezutí' ), $this->create_service( 30, false, 'Vyvážení' ) ];
		$this->assertSame( 201, $this->book( $services, '2027-03-01', '09:00', [ 'name' => 'Jan Novák' ] )->get_status() );
		update_option( 'pneukarnik_ical_token', 'tajny-token' );

		$ical = str_replace( "\r\n ", '', (string) $this->rest( 'GET', '/calendar', [ 'token' => 'tajny-token' ] )->get_data() );

		$this->assertStringContainsString( "SUMMARY:Přezutí\\, Vyvážení — Jan Novák\r\n", $ical );
		$this->assertStringContainsString( "DTEND:20270301T093000Z\r\n", $ical );
	}

	/**
	 * @return array<string, array{mixed}>
	 */
	public static function invalid_tokens(): array {
		return [
			'bez tokenu' => [ null ],
			'prázdný'    => [ '' ],
			'cizí'       => [ 'jiny-token' ],
			'pole'       => [ [ 'tajny-token' ] ],
		];
	}

	/**
	 * @dataProvider invalid_tokens
	 */
	public function test_feed_without_the_secret_token_is_refused( mixed $token ): void {
		$this->book_monday_at_nine();
		update_option( 'pneukarnik_ical_token', 'tajny-token' );

		$response = $this->rest( 'GET', '/calendar', null === $token ? [] : [ 'token' => $token ] );

		$this->assertSame( 401, $response->get_status() );
		$this->assertSame( 'ical.invalid_token', $response->get_data()['code'] );
		$this->assertStringNotContainsString( 'Jan Novák', (string) wp_json_encode( $response->get_data() ) );
	}

	public function test_regenerated_link_stops_the_old_one(): void {
		$this->book_monday_at_nine();
		$old = Pneukarnik_Rest_Calendar::get_or_create_token();
		$this->assertSame( 200, $this->rest( 'GET', '/calendar', [ 'token' => $old ] )->get_status() );

		$new = Pneukarnik_Rest_Calendar::regenerate_token();

		$this->assertNotSame( $old, $new );
		$this->assertSame( 401, $this->rest( 'GET', '/calendar', [ 'token' => $old ] )->get_status() );
		$this->assertSame( 200, $this->rest( 'GET', '/calendar', [ 'token' => $new ] )->get_status() );
		$this->assertStringContainsString( 'token=' . $new, Pneukarnik_Rest_Calendar::url() );
	}

	public function test_feed_has_confirmed_bookings_from_today_in_prague_time(): void {
		Pneukarnik_Clock::freeze( '2027-02-26 07:00' );
		$this->set_working_hours_every_day(
			[
				[
					'from' => '08:00',
					'to'   => '12:00',
				],
			]
		);
		$this->set_booking_rules( 30, 0, 365 );
		$tyres = $this->create_service( 60, false, 'Přezutí' );
		// Letní čas začíná v neděli 28. 3. 2027.
		$this->created_booking( $this->book( $tyres, '2027-03-25', '09:00', [ 'name' => 'Zimní čas' ] ) );
		$this->created_booking( $this->book( $tyres, '2027-03-30', '09:00', [ 'name' => 'Letní čas' ] ) );
		$this->created_booking( $this->book( $tyres, '2027-02-26', '11:00', [ 'name' => 'Dnes' ] ) );
		$cancelled = $this->created_booking( $this->book( $tyres, '2027-03-30', '10:00', [ 'name' => 'Zrušená' ] ) );
		Pneukarnik_Cancellation::cancel_by_provozovatel( (int) $cancelled['id'], null );
		$this->log_in_as( 'administrator' );
		$this->admin_booking(
			$this->admin_book(
				$tyres,
				'2027-03-27',
				'07:30',
				[
					'name'                  => 'Telefonická v sobotu',
					'outside_working_hours' => true,
				]
			)
		);
		wp_set_current_user( 0 );
		Pneukarnik_Clock::freeze( '2027-02-27 08:00' ); // „Dnes“ je včerejší.
		update_option( 'pneukarnik_ical_token', 'tajny-token' );

		$response = $this->rest( 'GET', '/calendar', [ 'token' => 'tajny-token' ] );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'text/calendar; charset=UTF-8', $response->get_headers()['Content-Type'] );
		$this->assertSame( 'no-store', $response->get_headers()['Cache-Control'] );
		$events = self::events( (string) $response->get_data() );
		$this->assertSame(
			[
				'Přezutí — Zimní čas'            => [ '20270325T080000Z', '20270325T090000Z' ],
				'Přezutí — Telefonická v sobotu' => [ '20270327T063000Z', '20270327T073000Z' ],
				'Přezutí — Letní čas'            => [ '20270330T070000Z', '20270330T080000Z' ],
			],
			$events
		);
	}

	private function book_monday_at_nine(): void {
		Pneukarnik_Clock::freeze( '2027-02-26 12:00' );
		$this->set_working_hours_every_day(
			[
				[
					'from' => '08:00',
					'to'   => '12:00',
				],
			]
		);
		$this->set_booking_rules( 30 );
		$this->created_booking( $this->book( $this->create_service( 60 ), '2027-03-01', '09:00', [ 'name' => 'Jan Novák' ] ) );
	}

	/**
	 * Události feedu jako SUMMARY => [DTSTART, DTEND].
	 *
	 * @return array<string, array{string, string}>
	 */
	private static function events( string $ical ): array {
		$events = [];
		preg_match_all( "~BEGIN:VEVENT\r\n(.*?)END:VEVENT~s", str_replace( "\r\n ", '', $ical ), $matches );
		foreach ( $matches[1] as $event ) {
			preg_match( '~^SUMMARY:(.*)\r$~m', $event, $summary );
			preg_match( '~^DTSTART:(.*)\r$~m', $event, $start );
			preg_match( '~^DTEND:(.*)\r$~m', $event, $end );
			$events[ $summary[1] ] = [ $start[1], $end[1] ];
		}
		return $events;
	}
}
