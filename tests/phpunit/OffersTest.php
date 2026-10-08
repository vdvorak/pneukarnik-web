<?php
/**
 * Nabídky a připomínky (ADR 0003): kdo nezaškrtne „Neposílat“ v online rezervaci, dostane je
 * až po proběhlém Termínu nezrušené Rezervace. Zaškrtnuté „Neposílat“ je odmítne a další
 * Rezervace to nezmění. Rezervace zadaná Provozovatelem nárok nezakládá. Nárok po návštěvě
 * přežije anonymizaci Rezervace, výmaz osobních údajů ho smaže.
 *
 * Zatím z nich chodí jen Připomínka přezutí, na ní se to tu ověřuje.
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

	public function test_customer_who_did_not_refuse_gets_offers_only_after_the_visit(): void {
		$this->book_online( 'jan@example.test', false, '2027-03-10', '09:00' );

		$this->run_reminders_at( self::BEFORE_SPRING );
		$this->assertSame( [], $this->mails, 'Termín ještě neproběhl' );

		$this->run_reminders_at( '2027-03-10 09:30' );
		$this->assertSame( [ [ 'jan@example.test' ] ], array_column( $this->mails, 'to' ) );
	}

	public function test_cancelled_booking_is_not_a_visit(): void {
		$this->cancel_booking( $this->book_online( 'jan@example.test', false, '2027-02-10' ) );

		$this->run_reminders_at( self::BEFORE_SPRING );

		$this->assertSame( [], $this->mails );
	}

	public function test_refusal_in_the_form_stops_offers(): void {
		$this->book_online( 'jan@example.test', true, '2027-02-10' );

		$this->run_reminders_at( self::BEFORE_SPRING );

		$this->assertSame( [], $this->mails );
	}

	public function test_later_booking_without_refusal_keeps_the_refusal(): void {
		$this->book_online( 'jan@example.test', true, '2027-02-10', '09:00' );
		$this->book_online( 'JAN@example.test', false, '2027-02-11', '09:00' );

		$this->run_reminders_at( self::BEFORE_SPRING );

		$this->assertSame( [], $this->mails );
	}

	public function test_refusal_in_a_later_booking_stops_offers(): void {
		$this->book_online( 'jan@example.test', false, '2027-02-10', '09:00' );
		$this->book_online( 'jan@example.test', true, '2027-02-11', '09:00' );

		$this->run_reminders_at( self::BEFORE_SPRING );

		$this->assertSame( [], $this->mails );
	}

	public function test_booking_by_provozovatel_gives_no_right_to_offers(): void {
		$this->log_in_as( 'pneukarnik_manager' );
		$this->admin_booking( $this->admin_book( $this->tyres, '2027-02-10', '09:00', [ 'email' => 'jan@example.test' ] ) );
		wp_set_current_user( 0 );
		$this->mails = [];

		$this->run_reminders_at( self::BEFORE_SPRING );

		$this->assertSame( [], $this->mails );
	}

	public function test_earlier_visit_booked_by_provozovatel_counts_once_the_customer_books_online(): void {
		$this->log_in_as( 'pneukarnik_manager' );
		$this->admin_booking( $this->admin_book( $this->tyres, '2027-02-10', '09:00', [ 'email' => 'jan@example.test' ] ) );
		wp_set_current_user( 0 );
		$this->mails = [];
		$this->book_online( 'jan@example.test', false, '2027-03-12' );

		$this->run_reminders_at( self::BEFORE_SPRING );

		$this->assertSame( [ [ 'jan@example.test' ] ], array_column( $this->mails, 'to' ) );
	}

	public function test_refusal_cannot_be_sent_by_provozovatel_and_does_not_matter_there(): void {
		$this->log_in_as( 'pneukarnik_manager' );
		$this->admin_booking(
			$this->admin_book(
				$this->tyres,
				'2027-02-10',
				'09:00',
				[
					'email'         => 'jan@example.test',
					'refuse_offers' => true,
				]
			)
		);
		wp_set_current_user( 0 );
		$this->mails = [];
		$this->book_online( 'jan@example.test', false, '2027-02-11' );

		$this->run_reminders_at( self::BEFORE_SPRING );

		$this->assertSame( [ [ 'jan@example.test' ] ], array_column( $this->mails, 'to' ), 'Odmítnout jde jen v online rezervaci' );
	}

	public function test_footer_says_why_the_customer_gets_the_email(): void {
		$this->book_online( 'jan@example.test', false, '2027-02-10' );

		$this->run_reminders_at( self::BEFORE_SPRING );

		$text = $this->mail_to( 'jan@example.test' )['text'];
		$this->assertStringContainsString( 'byli', $text );
		$this->assertStringContainsString( 'neodmítli', $text );
	}

	public function test_the_visit_survives_anonymisation_of_the_booking(): void {
		$this->book_online( 'jan@example.test', false, '2027-02-10' );
		$this->anonymise_at( '2028-02-11 03:00' );

		$this->run_reminders_at( '2028-03-05 10:00' );

		$this->assertSame( [ [ 'jan@example.test' ] ], array_column( $this->mails, 'to' ) );
	}

	public function test_cancelled_booking_does_not_become_a_visit_on_anonymisation(): void {
		$this->cancel_booking( $this->book_online( 'jan@example.test', false, '2027-02-10' ) );
		$this->anonymise_at( '2028-02-11 03:00' );

		$this->run_reminders_at( '2028-03-05 10:00' );

		$this->assertSame( [], $this->mails );
	}

	public function test_erasing_personal_data_removes_the_right_to_offers(): void {
		$this->book_online( 'jan@example.test', false, '2027-02-10' );
		$this->anonymise_at( '2028-02-11 03:00' );

		$eraser = apply_filters( 'wp_privacy_personal_data_erasers', [] )['pneukarnik-bookings']['callback'];
		$result = $eraser( 'jan@example.test', 1 );
		$this->run_reminders_at( '2028-03-05 10:00' );

		$this->assertTrue( $result['done'] );
		$this->assertSame( [], $this->mails );
	}

	public function test_erasing_before_anonymisation_does_not_bring_the_visit_back(): void {
		$this->book_online( 'jan@example.test', false, '2027-02-10' );
		$eraser = apply_filters( 'wp_privacy_personal_data_erasers', [] )['pneukarnik-bookings']['callback'];
		$eraser( 'jan@example.test', 1 );
		$this->anonymise_at( '2028-02-11 03:00' );

		$this->run_reminders_at( '2028-03-05 10:00' );

		$this->assertSame( [], $this->mails );
	}

	/**
	 * Online Rezervace s/bez zaškrtnutého „Neposílat Nabídky a připomínky“.
	 *
	 * @return string Token Zrušení z potvrzení.
	 */
	private function book_online( string $email, bool $refuse, string $date, string $time = '09:00' ): string {
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
		$token       = $this->cancel_token_from( $this->mails[0] );
		$this->mails = []; // Potvrzení Rezervace nás tu nezajímá.
		return $token;
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
