<?php
/**
 * Připomínka Termínu: den před Termínem v nastavenou hodinu každé potvrzené Rezervaci s e‑mailem
 * vytvořené aspoň 24 hodin předem, nejvýš jednou (po přesunu na jiný den znovu), vypínatelná.
 * Obsah e‑mailu a podepsaný odkaz na Zrušení vedle odkazu z potvrzení.
 * Nastavení a zkušební odeslání z formuláře ověřuje Playwright (emaily-zakaznikum.spec.ts).
 */

declare(strict_types=1);

class TerminReminderTest extends Pneukarnik_REST_Test_Case {

	private const PROVOZOVATEL = 'servis@example.test';

	/** Pondělí, Připomínka v neděli 28. 2. v 16:00. */
	private const MONDAY = '2027-03-01';

	private const SEND_AT = '2027-02-28 16:00';

	private int $tyres;

	public function set_up(): void {
		parent::set_up();
		$this->set_permalink_structure( '/%postname%/' );
		Pneukarnik_Clock::freeze( '2027-02-20 12:00' );
		$this->set_working_hours_every_day(
			[
				[
					'from' => '08:00',
					'to'   => '17:00',
				],
			]
		);
		$this->set_booking_rules( 60 );
		update_option( 'pneukarnik_cancellation_hours', 12 );
		update_option( 'pneukarnik_email', self::PROVOZOVATEL );
		update_option( 'pneukarnik_phone', '+420 775 565 326' );
		update_option( 'pneukarnik_address', 'Hlavní 1, Brno' );
		$this->tyres = $this->create_service( 60, false, 'Přezutí' );
		$this->capture_mails();
	}

	public function test_booking_for_tomorrow_gets_the_reminder_from_the_set_hour(): void {
		$this->book_online( 'jan@example.test' );

		$this->run_at( '2027-02-28 15:59' );
		$this->assertSame( [], $this->mails );

		$this->run_at( self::SEND_AT );
		$this->assertSame( [ [ 'jan@example.test' ] ], array_column( $this->mails, 'to' ) );
	}

	public function test_reminder_is_sent_late_the_same_evening_but_not_on_the_day_of_the_termin(): void {
		$this->book_online( 'jan@example.test', '09:00' );
		$this->book_online( 'eva@example.test', '11:00', '2027-03-02' );

		$this->run_at( '2027-03-01 23:30' );

		$this->assertSame( [ [ 'eva@example.test' ] ], array_column( $this->mails, 'to' ), 'Jen zítřejší Termín' );
		$this->assertStringContainsString( 'zítra v 11:00', $this->mails[0]['subject'] );
	}

	public function test_reminder_is_sent_only_once_even_when_the_job_runs_again_or_concurrently(): void {
		$this->book_online( 'jan@example.test' );
		$nested = false;
		add_action(
			'phpmailer_init',
			static function () use ( &$nested ): void {
				if ( ! $nested ) {
					$nested = true;
					do_action( Pneukarnik_Termin_Reminder::CRON_HOOK ); // Souběžné spuštění uprostřed odesílání.
				}
			}
		);

		$this->run_at( self::SEND_AT );
		$this->run_at( '2027-02-28 17:00' );
		$this->run_at( '2027-02-28 23:00' );

		$this->assertTrue( $nested );
		$this->assertCount( 1, $this->mails );
	}

	public function test_failed_sending_is_tried_again_next_time(): void {
		$this->book_online( 'jan@example.test' );
		$fail = static fn(): bool => false;
		add_filter( 'pre_wp_mail', $fail );
		$this->run_at( self::SEND_AT );
		remove_filter( 'pre_wp_mail', $fail );

		$this->run_at( '2027-02-28 17:00' );

		$this->assertSame( [ [ 'jan@example.test' ] ], array_column( $this->mails, 'to' ) );
	}

	public function test_booking_by_the_provozovatel_gets_it_too_when_it_has_an_email(): void {
		$this->log_in_as( 'pneukarnik_manager' );
		$this->admin_booking( $this->admin_book( $this->tyres, self::MONDAY, '09:00', [ 'email' => 'telefon@example.test' ] ) );
		$this->admin_booking( $this->admin_book( $this->tyres, self::MONDAY, '11:00' ) );
		$this->mails = [];

		$this->run_at( self::SEND_AT );

		$this->assertSame( [ [ 'telefon@example.test' ] ], array_column( $this->mails, 'to' ) );
	}

	public function test_customer_without_consent_to_offers_still_gets_it(): void {
		$this->book_online( 'jan@example.test' );

		$this->run_at( self::SEND_AT );

		$this->assertCount( 1, $this->mails );
	}

	public function test_cancelled_booking_gets_none(): void {
		$token = $this->book_online( 'jan@example.test' );
		$this->cancel( $token );
		$this->mails = [];

		$this->run_at( self::SEND_AT );

		$this->assertSame( [], $this->mails );
	}

	public function test_booking_created_less_than_24_hours_before_sending_gets_none(): void {
		Pneukarnik_Clock::freeze( '2027-02-27 16:00' );
		$this->book_online( 'presne@example.test', '09:00' );
		Pneukarnik_Clock::freeze( '2027-02-27 16:01' );
		$this->book_online( 'pozde@example.test', '11:00' );

		$this->run_at( '2027-02-28 20:00' );

		$this->assertSame( [ [ 'presne@example.test' ] ], array_column( $this->mails, 'to' ) );
	}

	public function test_switched_off_reminder_goes_to_nobody(): void {
		$this->book_online( 'jan@example.test' );
		Pneukarnik_Termin_Reminder::save( false, 16 );

		$this->run_at( self::SEND_AT );

		$this->assertSame( [], $this->mails );
	}

	public function test_reminder_is_on_by_default_at_four_pm(): void {
		delete_option( Pneukarnik_Termin_Reminder::OPTION_ENABLED );
		delete_option( Pneukarnik_Termin_Reminder::OPTION_HOUR );

		$this->assertTrue( Pneukarnik_Termin_Reminder::enabled() );
		$this->assertSame( 16, Pneukarnik_Termin_Reminder::hour() );
	}

	public function test_hour_and_the_24_hours_follow_the_setting(): void {
		Pneukarnik_Termin_Reminder::save( true, 9 );
		Pneukarnik_Clock::freeze( '2027-02-27 08:30' );
		$this->book_online( 'jan@example.test' );

		$this->run_at( '2027-02-28 08:59' );
		$this->assertSame( [], $this->mails );

		$this->run_at( '2027-02-28 09:00' );
		$this->assertCount( 1, $this->mails );
	}

	public function test_booking_moved_to_another_day_gets_a_reminder_for_the_new_termin(): void {
		$this->book_online( 'jan@example.test' );
		$id = $this->booking_id( 'jan@example.test' );
		$this->run_at( self::SEND_AT );
		$this->log_in_as( 'pneukarnik_manager' );

		$this->admin_booking( $this->rest( 'PATCH', "/admin/bookings/{$id}", [ 'time' => '13:00' ] ), 200 );
		$this->run_at( '2027-02-28 17:00' );
		$this->assertCount( 1, $this->mails, 'Jiný čas téhož dne Připomínku znovu neposílá' );

		$this->admin_booking( $this->rest( 'PATCH', "/admin/bookings/{$id}", [ 'date' => '2027-03-03' ] ), 200 );
		$this->run_at( '2027-03-02 16:00' );
		$this->assertCount( 2, $this->mails );
		$this->assertStringContainsString( 'zítra v 13:00', $this->mails[1]['subject'] );
	}

	public function test_reminder_has_termin_services_address_what_to_bring_and_phone(): void {
		$balance = $this->create_service( 30, false, 'Vyvážení' );
		$this->book( [ $this->tyres, $balance ], self::MONDAY, '09:00' );
		$this->mails = [];

		$this->run_at( self::SEND_AT );

		$mail = $this->mail_to( 'jan@example.test' );
		$this->assertSame( 'Připomínka: zítra v 9:00 vás čekáme', $mail['subject'] );
		$this->assertStringContainsString( '9:00–10:30', $mail['text'] );
		$this->assertStringContainsString( 'Přezutí', $mail['text'] );
		$this->assertStringContainsString( 'Vyvážení', $mail['text'] );
		$this->assertStringContainsString( 'Hlavní 1, Brno', $mail['text'] );
		$this->assertStringContainsString( 'Technický průkaz vozidla', $mail['text'] );
		$this->assertStringContainsString( '+420 775 565 326', $mail['text'] );
		$this->assertSame( [ self::PROVOZOVATEL ], $mail['reply_to'] );
		$this->assertStringNotContainsString( '/odhlaseni/', $mail['html'], 'Mezi Nabídky a připomínky nepatří' );
		$this->assertNotContains( 'List-Unsubscribe', array_column( $mail['headers'], 0 ) );
	}

	public function test_cancel_link_in_the_reminder_works_and_the_link_from_the_confirmation_too(): void {
		$confirmation = $this->book_online( 'jan@example.test' );
		$this->run_at( self::SEND_AT );
		$mail = $this->mail_to( 'jan@example.test' );
		$this->assertStringContainsString( 'nejpozději 28. 2. 2027 v 21:00', $mail['text'] );
		$signed = $this->signed_cancel_token_from( $mail );

		$this->assertSame( 'cancellation.allowed', $this->cancellation( $signed )->get_data()['code'] );
		$this->assertSame( 'cancellation.allowed', $this->cancellation( $confirmation )->get_data()['code'] );

		$this->assertSame( 'cancellation.cancelled', $this->cancel( $signed )->get_data()['code'] );
		$this->assertSame( 'cancellation.already_cancelled', $this->cancellation( $confirmation )->get_data()['code'] );
	}

	public function test_cancel_link_from_the_reminder_respects_the_cancellation_deadline(): void {
		$this->book_online( 'jan@example.test' );
		$this->run_at( self::SEND_AT );
		$signed = $this->signed_cancel_token_from( $this->mail_to( 'jan@example.test' ) );

		Pneukarnik_Clock::freeze( '2027-02-28 21:01' );
		$response = $this->cancel( $signed );

		$this->assertSame( 422, $response->get_status() );
		$this->assertSame( 'cancellation.too_late', $response->get_data()['code'] );
	}

	public function test_forged_or_other_bookings_cancel_link_is_refused(): void {
		$this->book_online( 'jan@example.test', '09:00' );
		$this->book_online( 'eva@example.test', '11:00' );
		$this->run_at( self::SEND_AT );
		$signed             = $this->signed_cancel_token_from( $this->mail_to( 'jan@example.test' ) );
		[ $id, $signature ] = explode( '-', $signed );

		$this->assertSame( 404, $this->cancellation( ( (int) $id + 1 ) . '-' . $signature )->get_status() );
		$this->assertSame( 404, $this->cancellation( $id . '-' . str_repeat( 'a', 64 ) )->get_status() );
		$this->assertSame( 404, $this->cancel( $id . '-' . str_repeat( 'a', 64 ) )->get_status() );
	}

	public function test_after_the_cancellation_deadline_the_reminder_gives_the_phone_instead_of_the_link(): void {
		update_option( 'pneukarnik_cancellation_hours', 24 );
		$this->book_online( 'jan@example.test' );

		$this->run_at( self::SEND_AT );

		$mail = $this->mail_to( 'jan@example.test' );
		$this->assertStringNotContainsString( '/rezervace/zruseni/', $mail['html'] );
		$this->assertStringContainsString( 'zavolejte nám prosím na +420 775 565 326', $mail['text'] );
	}

	/**
	 * @return array<string, array{0:string,1:string,2:string,3:bool}>
	 */
	public static function daylight_saving_changes(): array {
		return [
			// Odeslání v neděli 16:00 letního času, 24 h předtím je sobota 15:00 zimního.
			'na letní čas, vytvořená 23,5 h předem' => [ '2027-03-29', '2027-03-27 15:30', '2027-03-28 16:00', false ],
			'na letní čas, vytvořená 24 h předem'   => [ '2027-03-29', '2027-03-27 15:00', '2027-03-28 16:00', true ],
			'na letní čas, před 16:00 letního času' => [ '2027-03-29', '2027-03-20 12:00', '2027-03-28 15:59', false ],
			// Odeslání v neděli 16:00 zimního času, 24 h předtím je sobota 17:00 letního.
			'na zimní čas, vytvořená 24,5 h předem' => [ '2027-11-01', '2027-10-30 16:30', '2027-10-31 16:00', true ],
			'na zimní čas, vytvořená 23,5 h předem' => [ '2027-11-01', '2027-10-30 17:30', '2027-10-31 16:00', false ],
			'na zimní čas, před 16:00 zimního času' => [ '2027-11-01', '2027-10-20 12:00', '2027-10-31 15:59', false ],
		];
	}

	/**
	 * @dataProvider daylight_saving_changes
	 */
	public function test_times_across_daylight_saving_changes( string $date, string $created, string $now, bool $sent ): void {
		Pneukarnik_Clock::freeze( $created );
		$this->log_in_as( 'pneukarnik_manager' );
		$this->admin_booking(
			$this->admin_book(
				$this->tyres,
				$date,
				'09:00',
				[
					'email'                 => 'jan@example.test',
					'outside_working_hours' => true,
				]
			)
		);
		$this->mails = [];

		$this->run_at( $now );

		$this->assertCount( $sent ? 1 : 0, $this->mails );
	}

	public function test_reminders_are_scheduled(): void {
		$this->assertNotFalse( wp_next_scheduled( Pneukarnik_Termin_Reminder::CRON_HOOK ) );
	}

	private function run_at( string $now ): void {
		Pneukarnik_Clock::freeze( $now );
		do_action( Pneukarnik_Termin_Reminder::CRON_HOOK );
	}

	/**
	 * Online Rezervace. Vrátí token Zrušení z potvrzení.
	 */
	private function book_online( string $email, string $time = '09:00', string $date = self::MONDAY ): string {
		$response = $this->book( $this->tyres, $date, $time, [ 'email' => $email ] );
		$this->assertSame( 201, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );
		$token       = $this->cancel_token_from( $this->mail_to( $email ) );
		$this->mails = []; // Potvrzení Rezervace nás tu nezajímá.
		return $token;
	}

	private function booking_id( string $email ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE customer_email = %s', Pneukarnik_DB::bookings_table(), $email ) );
	}

	/**
	 * @param array{html:string} $mail
	 */
	private function signed_cancel_token_from( array $mail ): string {
		$this->assertSame( 1, preg_match( '~/rezervace/zruseni/\?r=([0-9]+-[0-9a-f]{64})~', $mail['html'], $m ), 'Odkaz na Zrušení v Připomínce' );
		return $m[1];
	}
}
