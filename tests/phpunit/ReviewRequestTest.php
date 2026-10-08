<?php
/**
 * Žádost o hodnocení: den po Termínu nezrušené Rezervace od 10:00 e‑mailům, které smějí dostávat
 * Nabídky a připomínky (podrobně OffersTest), nejvýš jednou za celou dobu i po anonymizaci Rezervace,
 * výmaz osobních údajů záznam smaže. Bez Place ID se nic neposílá, vypínatelná.
 * Nastavení a zkušební odeslání z formuláře ověřuje Playwright (emaily-zakaznikum.spec.ts).
 */

declare(strict_types=1);

class ReviewRequestTest extends Pneukarnik_REST_Test_Case {

	private const PROVOZOVATEL = 'servis@example.test';

	private const PLACE_ID = 'ChIJ-test_místo';

	/** Návštěva ve středu 10. 2., Žádost ve čtvrtek 11. 2. od 10:00. */
	private const VISIT = '2027-02-10';

	private const SEND_AT = '2027-02-11 10:00';

	private int $tyres;

	public function set_up(): void {
		parent::set_up();
		$this->set_permalink_structure( '/%postname%/' );
		Pneukarnik_Clock::freeze( '2027-02-01 12:00' );
		$this->set_working_hours_every_day(
			[
				[
					'from' => '08:00',
					'to'   => '17:00',
				],
			]
		);
		$this->set_booking_rules( 60 );
		update_option( 'pneukarnik_email', self::PROVOZOVATEL );
		update_option( 'pneukarnik_reviews_place_id', self::PLACE_ID );
		$this->tyres = $this->create_service( 60, false, 'Přezutí' );
		$this->capture_mails();
	}

	public function test_first_visit_gets_the_request_the_day_after_from_ten(): void {
		$this->book_online( 'jan@example.test' );

		$this->run_at( '2027-02-10 23:00' );
		$this->run_at( '2027-02-11 09:59' );
		$this->assertSame( [], $this->mails );

		$this->run_at( self::SEND_AT );
		$this->assertSame( [ [ 'jan@example.test' ] ], array_column( $this->mails, 'to' ) );
	}

	public function test_later_visits_never_get_another_one(): void {
		$this->book_online( 'jan@example.test', '09:00' );
		$this->book_online( 'JAN@example.test', '11:00' );
		$this->book_online( 'jan@example.test', '09:00', '2027-02-15' );

		$this->run_at( self::SEND_AT );
		$this->run_at( '2027-02-16 10:00' );

		$this->assertCount( 1, $this->mails );
	}

	public function test_request_is_sent_only_once_even_when_the_job_runs_again_or_concurrently(): void {
		$this->book_online( 'jan@example.test' );
		$nested = false;
		add_action(
			'phpmailer_init',
			static function () use ( &$nested ): void {
				if ( ! $nested ) {
					$nested = true;
					do_action( Pneukarnik_Review_Request::CRON_HOOK ); // Souběžné spuštění uprostřed odesílání.
				}
			}
		);

		$this->run_at( self::SEND_AT );
		$this->run_at( '2027-02-11 11:00' );

		$this->assertTrue( $nested );
		$this->assertCount( 1, $this->mails );
	}

	public function test_failed_sending_is_tried_again_next_time(): void {
		$this->book_online( 'jan@example.test' );
		$fail = static fn(): bool => false;
		add_filter( 'pre_wp_mail', $fail );
		$this->run_at( self::SEND_AT );
		remove_filter( 'pre_wp_mail', $fail );

		$this->run_at( '2027-02-11 11:00' );

		$this->assertSame( [ [ 'jan@example.test' ] ], array_column( $this->mails, 'to' ) );
	}

	public function test_refusal_in_the_form_gets_none(): void {
		$this->book_online( 'jan@example.test', refuse: true );

		$this->run_at( self::SEND_AT );

		$this->assertSame( [], $this->mails );
	}

	public function test_send_nothing_on_the_settings_page_gets_none(): void {
		$this->book_online( 'jan@example.test' );
		$key = (string) wp_parse_args( (string) wp_parse_url( Pneukarnik_Subscriptions::settings_url( 'jan@example.test' ), PHP_URL_QUERY ) )['k'];
		Pneukarnik_Subscriptions::withdraw_everything( $key, 'nastaveni' );

		$this->run_at( self::SEND_AT );

		$this->assertSame( [], $this->mails );
	}

	public function test_cancelled_booking_gets_none(): void {
		$this->assertSame( 200, $this->cancel( $this->book_online( 'jan@example.test' ) )->get_status() );
		$this->mails = [];

		$this->run_at( self::SEND_AT );

		$this->assertSame( [], $this->mails );
	}

	public function test_booking_by_the_provozovatel_gets_it_only_with_consent(): void {
		$this->book_by_provozovatel( 'bez@example.test', '09:00' );
		$confirmation = $this->book_by_provozovatel( 'ano@example.test', '11:00' );
		$this->assertSame( 1, preg_match( '~/odhlaseni/\?s=([0-9]+\.[0-9a-f]{64})~', $confirmation['html'], $m ), 'Odkaz „Ano, posílejte“' );
		$this->assertNotNull( Pneukarnik_Subscriptions::accept_offer( $m[1] ) );
		$this->mails = [];

		$this->run_at( self::SEND_AT );

		$this->assertSame( [ [ 'ano@example.test' ] ], array_column( $this->mails, 'to' ) );
		$this->assertStringContainsString( 'souhlasili', $this->mails[0]['text'] );
	}

	public function test_customer_who_could_not_get_it_after_the_first_visit_gets_it_after_a_later_one(): void {
		$this->book_online( 'jan@example.test', refuse: true );
		$this->run_at( self::SEND_AT );
		$this->assertSame( [], $this->mails );

		$this->book_online( 'jan@example.test', '09:00', '2027-02-15' ); // Tentokrát „Neposílat“ nezaškrtl.
		$this->run_at( '2027-02-16 10:00' );
		$this->assertSame( [ [ 'jan@example.test' ] ], array_column( $this->mails, 'to' ) );
	}

	public function test_without_place_id_nothing_is_sent(): void {
		update_option( 'pneukarnik_reviews_place_id', '' );
		$this->book_online( 'jan@example.test' );

		$this->run_at( self::SEND_AT );

		$this->assertSame( [], $this->mails );
		$this->assertNull( Pneukarnik_Review_Request::send_test() );
	}

	public function test_switched_off_request_goes_to_nobody(): void {
		$this->book_online( 'jan@example.test' );
		Pneukarnik_Review_Request::save( false, Pneukarnik_Review_Request::intro() );

		$this->run_at( self::SEND_AT );

		$this->assertSame( [], $this->mails );
	}

	public function test_request_is_on_by_default(): void {
		delete_option( Pneukarnik_Review_Request::OPTION_ENABLED );

		$this->assertTrue( Pneukarnik_Review_Request::enabled() );
	}

	public function test_request_has_the_intro_google_link_and_email_settings(): void {
		Pneukarnik_Review_Request::save( true, 'Vlastní úvod Žádosti' );
		$this->book_online( 'jan@example.test' );

		$this->run_at( self::SEND_AT );

		$mail = $this->mail_to( 'jan@example.test' );
		$this->assertSame( 'Jak jste u nás byli spokojeni?', $mail['subject'] );
		$this->assertStringContainsString( 'Vlastní úvod Žádosti', $mail['text'] );
		$this->assertStringContainsString( esc_url( 'https://search.google.com/local/writereview?placeid=' . rawurlencode( self::PLACE_ID ) ), $mail['html'] );
		$this->assertStringContainsString( 'neodmítli', $mail['text'] );
		$this->assertStringContainsString( esc_url( Pneukarnik_Subscriptions::settings_url( 'jan@example.test' ) ), $mail['html'] );
		$this->assertContains( [ 'List-Unsubscribe', '<' . Pneukarnik_Subscriptions::settings_url( 'jan@example.test' ) . '>' ], $mail['headers'] );
		$this->assertContains( [ 'List-Unsubscribe-Post', 'List-Unsubscribe=One-Click' ], $mail['headers'] );
		$this->assertSame( [ self::PROVOZOVATEL ], $mail['reply_to'] );
	}

	public function test_once_ever_holds_after_the_booking_is_anonymised(): void {
		$this->book_online( 'jan@example.test' );
		$this->run_at( self::SEND_AT );
		$this->anonymise_at( '2028-02-11 03:00' );

		Pneukarnik_Clock::freeze( '2028-02-20 12:00' );
		$this->book_online( 'jan@example.test', '09:00', '2028-03-01' );
		$this->run_at( '2028-03-02 10:00' );

		$this->assertCount( 1, $this->mails );
	}

	public function test_erasing_personal_data_forgets_the_request_was_sent(): void {
		$this->book_online( 'jan@example.test' );
		$this->run_at( self::SEND_AT );
		$eraser = apply_filters( 'wp_privacy_personal_data_erasers', [] )['pneukarnik-bookings']['callback'];
		$eraser( 'jan@example.test', 1 );

		$this->book_online( 'jan@example.test', '09:00', '2027-02-15' );
		$this->run_at( '2027-02-16 10:00' );

		$this->assertCount( 2, $this->mails );
	}

	public function test_test_request_goes_only_to_the_provozovatel(): void {
		$this->book_online( 'jan@example.test' );
		Pneukarnik_Clock::freeze( self::SEND_AT );

		$this->assertSame( self::PROVOZOVATEL, Pneukarnik_Review_Request::send_test() );

		$mail = $this->mail_to( self::PROVOZOVATEL );
		$this->assertSame( '[Zkouška] Jak jste u nás byli spokojeni?', $mail['subject'] );
		$this->assertStringContainsString( 'Toto je zkušební Žádost o hodnocení', $mail['text'] );
		$this->assertCount( 1, $this->mails );

		$this->run_at( self::SEND_AT );
		$this->assertSame( [ [ 'jan@example.test' ] ], array_column( array_slice( $this->mails, 1 ), 'to' ), 'Zkouška nic neoznačí' );
	}

	public function test_requests_are_scheduled(): void {
		$this->assertNotFalse( wp_next_scheduled( Pneukarnik_Review_Request::CRON_HOOK ) );
	}

	private function run_at( string $now ): void {
		Pneukarnik_Clock::freeze( $now );
		do_action( Pneukarnik_Review_Request::CRON_HOOK );
	}

	private function anonymise_at( string $now ): void {
		Pneukarnik_Clock::freeze( $now );
		do_action( Pneukarnik_GDPR::CRON_HOOK );
	}

	/**
	 * Online Rezervace. Vrátí token Zrušení z potvrzení.
	 */
	private function book_online( string $email, string $time = '09:00', string $date = self::VISIT, bool $refuse = false ): string {
		$before   = count( $this->mails );
		$response = $this->book(
			$this->tyres,
			$date,
			$time,
			[
				'email'         => $email,
				'refuse_offers' => $refuse,
			]
		);
		$this->assertSame( 201, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );
		$token       = $this->cancel_token_from( $this->mails[ $before ] );
		$this->mails = array_slice( $this->mails, 0, $before ); // Potvrzení Rezervace nás tu nezajímá.
		return $token;
	}

	/**
	 * Telefonická objednávka. Vrátí potvrzení Zákazníkovi.
	 *
	 * @return array{html:string}
	 */
	private function book_by_provozovatel( string $email, string $time ): array {
		$this->log_in_as( 'pneukarnik_manager' );
		$this->admin_booking( $this->admin_book( $this->tyres, self::VISIT, $time, [ 'email' => $email ] ) );
		wp_set_current_user( 0 );
		return $this->mail_to( $email );
	}
}
