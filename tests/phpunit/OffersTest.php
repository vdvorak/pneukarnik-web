<?php
/**
 * Nabídky a připomínky (ADR 0004): chodí jen se souhlasem, každý druh zvlášť zaškrtnutým políčkem
 * v online rezervaci. Souhlas platí hned. Nezaškrtnuté políčko nic nemění, dřívější souhlas ani
 * odvolání. Rezervace zadaná Provozovatelem souhlas nezapíše. Souhlas přežije anonymizaci
 * Rezervace, výmaz osobních údajů ho smaže.
 *
 * Doručení se ověřuje na Připomínce přezutí, Rozesílky viz MailingTest.
 */

declare(strict_types=1);

class OffersTest extends Pneukarnik_REST_Test_Case {

	/** Jarní Sezóna od 15. 3., Připomínky 14 dní předem, tedy od 1. 3. */
	private const BEFORE_SPRING = '2027-03-05 10:00';

	private int $tyres;

	public function set_up(): void {
		parent::set_up();
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
		$this->set_seasons( [ '03-15', '04-30' ], [ '10-15', '11-30' ] );
		update_option( 'pneukarnik_reminder_days', 14 );
		$this->tyres = $this->create_service( 60, true, 'Přezutí' );
		$this->capture_mails();
	}

	public function test_without_ticked_boxes_nothing_is_sent(): void {
		$this->book_online( 'jan@example.test', '2027-02-10' );

		$this->run_reminders_at( self::BEFORE_SPRING );

		$this->assertSame( [], $this->mails );
		$this->assertNull( $this->row( 'jan@example.test', Pneukarnik_Subscriptions::REMINDER ), 'Nic se nezapíše' );
	}

	public function test_consent_counts_right_away_even_before_the_visit(): void {
		$this->book_online( 'jan@example.test', '2027-03-10', reminder: true );

		$this->run_reminders_at( self::BEFORE_SPRING );

		$this->assertSame( [ [ 'jan@example.test' ] ], array_column( $this->mails, 'to' ) );
	}

	public function test_each_box_consents_to_its_own_kind_with_time_and_source(): void {
		$this->book_online( 'Jan@Example.test', '2027-02-10', reminder: true );
		$this->book_online( 'eva@example.test', '2027-02-10', '10:00', promotions: true );

		$this->assertTrue( Pneukarnik_Subscriptions::is_active( 'jan@example.test', Pneukarnik_Subscriptions::REMINDER ) );
		$this->assertFalse( Pneukarnik_Subscriptions::is_active( 'jan@example.test', Pneukarnik_Subscriptions::PROMOTIONS ) );
		$this->assertFalse( Pneukarnik_Subscriptions::is_active( 'eva@example.test', Pneukarnik_Subscriptions::REMINDER ) );
		$this->assertTrue( Pneukarnik_Subscriptions::is_active( 'eva@example.test', Pneukarnik_Subscriptions::PROMOTIONS ) );
		$this->assertSame(
			[
				'consent_source' => 'rezervace',
				'consented_at'   => '2027-02-01 12:00:00',
			],
			$this->row( 'jan@example.test', Pneukarnik_Subscriptions::REMINDER )
		);
	}

	public function test_cancelled_booking_keeps_the_consent(): void {
		$this->cancel_booking( $this->book_online( 'jan@example.test', '2027-02-10', reminder: true ) );

		$this->run_reminders_at( self::BEFORE_SPRING );

		$this->assertSame( [ [ 'jan@example.test' ] ], array_column( $this->mails, 'to' ), 'Souhlas je k e‑mailu, ne k Rezervaci' );
	}

	public function test_later_booking_without_ticked_boxes_keeps_the_consent(): void {
		$this->book_online( 'jan@example.test', '2027-02-10', '09:00', reminder: true );
		Pneukarnik_Clock::freeze( '2027-02-02 12:00' );
		$this->book_online( 'jan@example.test', '2027-02-11' );

		$this->assertSame( '2027-02-01 12:00:00', $this->row( 'jan@example.test', Pneukarnik_Subscriptions::REMINDER )['consented_at'] );
	}

	public function test_later_booking_without_ticked_boxes_keeps_the_withdrawal(): void {
		$this->book_online( 'jan@example.test', '2027-02-10', '09:00', reminder: true );
		$this->withdraw_everything( 'jan@example.test' );
		$this->book_online( 'jan@example.test', '2027-02-11' );

		$this->run_reminders_at( self::BEFORE_SPRING );

		$this->assertSame( [], $this->mails );
	}

	public function test_ticked_box_after_withdrawal_is_a_new_consent(): void {
		$this->book_online( 'jan@example.test', '2027-02-10', '09:00', reminder: true );
		$this->withdraw_everything( 'jan@example.test' );
		Pneukarnik_Clock::freeze( '2027-02-02 12:00' );
		$this->book_online( 'jan@example.test', '2027-02-11', reminder: true );

		$this->run_reminders_at( self::BEFORE_SPRING );

		$this->assertSame( [ [ 'jan@example.test' ] ], array_column( $this->mails, 'to' ) );
		$this->assertSame( '2027-02-02 12:00:00', $this->row( 'jan@example.test', Pneukarnik_Subscriptions::REMINDER )['consented_at'] );
	}

	public function test_consent_cannot_be_sent_by_provozovatel(): void {
		$this->log_in_as( 'pneukarnik_manager' );
		$this->admin_booking(
			$this->admin_book(
				$this->tyres,
				'2027-02-10',
				'09:00',
				[
					'email'              => 'jan@example.test',
					'consent_reminder'   => true,
					'consent_promotions' => true,
				]
			)
		);
		wp_set_current_user( 0 );
		$this->mails = [];

		$this->run_reminders_at( self::BEFORE_SPRING );

		$this->assertSame( [], $this->mails, 'Souhlasí jen Zákazník sám' );
		$this->assertNull( $this->row( 'jan@example.test', Pneukarnik_Subscriptions::REMINDER ) );
	}

	public function test_footer_says_why_the_customer_gets_the_email(): void {
		$this->book_online( 'jan@example.test', '2027-02-10', reminder: true );

		$this->run_reminders_at( self::BEFORE_SPRING );

		$this->assertStringContainsString( 'Připomínku dostáváte, protože jste s ní souhlasili.', $this->mail_to( 'jan@example.test' )['text'] );
	}

	public function test_consent_survives_anonymisation_of_the_booking(): void {
		$this->book_online( 'jan@example.test', '2027-02-10', reminder: true );
		$this->anonymise_at( '2028-02-11 03:00' );

		$this->run_reminders_at( '2028-03-05 10:00' );

		$this->assertSame( [ [ 'jan@example.test' ] ], array_column( $this->mails, 'to' ) );
	}

	public function test_erasing_personal_data_removes_the_consent(): void {
		$this->book_online( 'jan@example.test', '2027-02-10', reminder: true, promotions: true );

		$eraser = apply_filters( 'wp_privacy_personal_data_erasers', [] )['pneukarnik-bookings']['callback'];
		$result = $eraser( 'jan@example.test', 1 );
		$this->run_reminders_at( self::BEFORE_SPRING );

		$this->assertTrue( $result['done'] );
		$this->assertSame( [], $this->mails );
		$this->assertNull( $this->row( 'jan@example.test', Pneukarnik_Subscriptions::PROMOTIONS ) );
	}

	/**
	 * Online Rezervace se zaškrtnutými políčky souhlasu.
	 *
	 * @return string Token Zrušení z potvrzení.
	 */
	private function book_online( string $email, string $date, string $time = '09:00', bool $reminder = false, bool $promotions = false ): string {
		$response = $this->book(
			$this->tyres,
			$date,
			$time,
			[
				'email'              => $email,
				'consent_reminder'   => $reminder,
				'consent_promotions' => $promotions,
			]
		);
		$this->assertSame( 201, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );
		$token       = $this->cancel_token_from( $this->mails[0] );
		$this->mails = []; // Potvrzení Rezervace nás tu nezajímá.
		return $token;
	}

	private function withdraw_everything( string $email ): void {
		parse_str( (string) wp_parse_url( Pneukarnik_Subscriptions::settings_url( $email ), PHP_URL_QUERY ), $query );
		$this->assertSame( Pneukarnik_Subscriptions::DONE, Pneukarnik_Subscriptions::withdraw_everything( $query['k'], 'nastaveni' ) );
	}

	/**
	 * @return array{consent_source:string|null,consented_at:string|null}|null
	 */
	private function row( string $email, string $purpose ): ?array {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare( 'SELECT consent_source, consented_at FROM %i WHERE email = %s AND purpose = %s', Pneukarnik_DB::subscriptions_table(), $email, $purpose ),
			ARRAY_A
		);
	}

	private function cancel_booking( string $token ): void {
		$this->assertSame( 200, $this->cancel( $token )->get_status() );
		$this->mails = [];
	}

	private function run_reminders_at( string $now ): void {
		Pneukarnik_Clock::freeze( $now );
		do_action( Pneukarnik_Reminder::CRON_HOOK );
	}

	private function anonymise_at( string $now ): void {
		Pneukarnik_Clock::freeze( $now );
		do_action( Pneukarnik_GDPR::CRON_HOOK );
	}
}
