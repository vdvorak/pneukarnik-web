<?php
/**
 * Automatická anonymizace Rezervací 1 rok po Termínu a výmaz osobních údajů nástrojem WordPressu:
 * osobní údaje zmizí, statistika zůstane, odkazy z e‑mailů přestanou fungovat.
 */

declare(strict_types=1);

class AnonymisationTest extends Pneukarnik_REST_Test_Case {

	private const MONDAY = '2027-03-01';

	private const ANONYMISED = [
		'name'            => '[anonymizováno]',
		'company'         => '',
		'phone'           => '',
		'email'           => 'anonymized@deleted.invalid',
		'plate'           => '[anonymizováno]',
		'vehicle'         => '',
		'note'            => '',
		'leasing_company' => '',
		'cancel_reason'   => '',
	];

	private int $tyres;

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
		$this->set_booking_rules( 60 );
		update_option( 'pneukarnik_cancellation_hours', 24 );
		$this->tyres = $this->create_service( 60, false, 'Přezutí' );
		$this->capture_mails();
	}

	public function test_personal_data_disappear_and_statistics_stay(): void {
		$this->log_in_as( 'pneukarnik_manager' );
		$booking   = $this->admin_booking(
			$this->admin_book(
				$this->tyres,
				self::MONDAY,
				'09:00',
				[
					'company'         => 'Firma s.r.o.',
					'email'           => 'firma@example.test',
					'plate'           => '1AB 2345',
					'vehicle'         => 'Škoda Octavia',
					'note'            => 'Volat předem',
					'leasing'         => true,
					'leasing_company' => 'ČSOB Leasing',
					'stored_wheels'   => true,
				]
			)
		);
		$cancelled = $this->admin_booking( $this->admin_book( $this->tyres, self::MONDAY, '10:00', [ 'vehicle' => 'Fabia' ] ) );
		$this->rest( 'POST', "/admin/bookings/{$cancelled['id']}/cancel", [ 'reason' => 'Pan Novák je nemocný' ] );
		$cancelled = $this->booking( $cancelled['id'] );

		$this->anonymise_at( '2028-03-02 03:00' );

		foreach ( [ $booking, $cancelled ] as $before ) {
			$after = $this->booking( $before['id'] );
			$this->assertSame( self::ANONYMISED, array_intersect_key( $after, self::ANONYMISED ) );
			$this->assertSame( array_diff_key( $before, self::ANONYMISED ), array_diff_key( $after, self::ANONYMISED ), 'statistika zůstala' );
		}
		$this->assertSame( [ 'CONFIRMED', true, true ], [ $booking['status'], $booking['leasing'], $booking['stored_wheels'] ] );
		$this->assertSame( 'CANCELLED', $cancelled['status'] );
	}

	/**
	 * @return array<string, array{0:string,1:bool}>
	 */
	public static function one_year_after_termin(): array {
		return [
			'den předtím'       => [ '2028-02-29 23:59', false ],
			'minutu před rokem' => [ '2028-03-01 08:59', false ],
			'přesně rok'        => [ '2028-03-01 09:00', true ],
			'den potom'         => [ '2028-03-02 03:00', true ],
		];
	}

	/**
	 * @dataProvider one_year_after_termin
	 */
	public function test_booking_is_anonymised_exactly_one_year_after_termin( string $now, bool $anonymised ): void {
		$this->log_in_as( 'pneukarnik_manager' );
		$booking = $this->admin_booking( $this->admin_book( $this->tyres, self::MONDAY, '09:00' ) );

		$this->anonymise_at( $now );

		$this->assertSame( $anonymised ? self::ANONYMISED['name'] : 'Telefonická objednávka', $this->booking( $booking['id'] )['name'] );
	}

	public function test_links_from_emails_stop_working(): void {
		$response = $this->book( $this->tyres, self::MONDAY, '09:00' );
		$this->assertSame( 201, $response->get_status() );
		parse_str( (string) wp_parse_url( $response->get_data()['confirmation_url'], PHP_URL_QUERY ), $query );
		$confirmation = (string) $query['r'];
		$mail         = $this->mail_to( 'jan@example.test' );
		$cancel       = $this->cancel_token_from( $mail );
		$prefill      = $this->prefill_token_from( $mail );
		$this->assertSame( 200, $this->cancellation( $cancel )->get_status() ); // Po Termínu už odkaz neplatí sám.

		$this->anonymise_at( '2028-02-29 12:00' );
		$this->assertSame( 200, $this->prefill( $prefill )->get_status() );
		$this->assertNotNull( Pneukarnik_Booking::find_by_confirmation_token( $confirmation ) );

		$this->anonymise_at( '2028-03-02 03:00' );
		$this->assertSame( 'cancellation.invalid_token', $this->cancellation( $cancel )->get_data()['code'] );
		$this->assertSame( 'prefill.invalid_token', $this->prefill( $prefill )->get_data()['code'] );
		$this->assertNull( Pneukarnik_Booking::find_by_confirmation_token( $confirmation ) );
	}

	public function test_running_again_changes_nothing_and_later_bookings_wait_for_their_year(): void {
		$this->log_in_as( 'pneukarnik_manager' );
		$old   = $this->admin_booking( $this->admin_book( $this->tyres, self::MONDAY, '09:00' ) );
		$later = $this->admin_booking( $this->admin_book( $this->tyres, '2027-03-08', '09:00' ) );

		$this->anonymise_at( '2028-03-02 03:00' );
		$first = [ $this->booking( $old['id'] ), $this->booking( $later['id'] ) ];
		$this->anonymise_at( '2028-03-03 03:00' );

		$this->assertSame( $first, [ $this->booking( $old['id'] ), $this->booking( $later['id'] ) ] );
		$this->assertSame( $later, $first[1] );

		$this->anonymise_at( '2028-03-09 03:00' );
		$this->assertSame( self::ANONYMISED['name'], $this->booking( $later['id'] )['name'] );
	}

	public function test_privacy_eraser_anonymises_every_booking_of_the_email(): void {
		$this->log_in_as( 'pneukarnik_manager' );
		$ids = [];
		foreach ( [ '01', '02', '03', '04', '05', '06', '07' ] as $day ) {
			foreach ( [ '08:00', '09:00', '10:00', '11:00' ] as $time ) {
				$ids[] = $this->admin_booking( $this->admin_book( $this->tyres, "2027-03-{$day}", $time, [ 'email' => 'jan@example.test' ] ) )['id'];
			}
		}
		$other = $this->admin_booking( $this->admin_book( $this->tyres, '2027-03-08', '08:00', [ 'email' => 'eva@example.test' ] ) );

		$eraser  = apply_filters( 'wp_privacy_personal_data_erasers', [] )['pneukarnik-bookings']['callback'];
		$removed = 0;
		for ( $page = 1; $page < 10; $page++ ) { // Stránkuje jako Nástroje → Smazání osobních údajů.
			$result   = $eraser( 'jan@example.test', $page );
			$removed += $result['items_removed'];
			if ( $result['done'] ) {
				break;
			}
		}

		$this->assertSame( count( $ids ), $removed );
		foreach ( $ids as $id ) {
			$this->assertSame( self::ANONYMISED['email'], $this->booking( $id )['email'] );
		}
		$this->assertSame( 'eva@example.test', $this->booking( $other['id'] )['email'] );
	}

	public function test_anonymisation_is_scheduled_daily_at_three_in_the_morning(): void {
		wp_clear_scheduled_hook( Pneukarnik_GDPR::CRON_HOOK );

		Pneukarnik_GDPR::schedule(); // Totéž dělá plugin při každém init, i když už je aktivní.

		$this->assertSame( 'daily', wp_get_schedule( Pneukarnik_GDPR::CRON_HOOK ) );
		$this->assertSame( Pneukarnik_Clock::at( '2027-02-21 03:00' )->getTimestamp(), wp_next_scheduled( Pneukarnik_GDPR::CRON_HOOK ) );

		wp_clear_scheduled_hook( Pneukarnik_GDPR::CRON_HOOK );
	}

	private function anonymise_at( string $now ): void {
		Pneukarnik_Clock::freeze( $now );
		do_action( Pneukarnik_GDPR::CRON_HOOK );
	}

	/**
	 * Rezervace, jak ji vidí Provozovatel v administraci.
	 *
	 * @return array<string, mixed>
	 */
	private function booking( int $id ): array {
		$this->log_in_as( 'pneukarnik_viewer' );
		return $this->admin_booking( $this->rest( 'GET', "/admin/bookings/{$id}" ), 200 );
	}
}
