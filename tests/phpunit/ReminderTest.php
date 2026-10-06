<?php
/**
 * Připomínka přezutí: souhlas z rezervace, výběr příjemců, nejvýš jedna Připomínka na e‑mail
 * a Sezónu, odhlášení podepsaným odkazem i starým /cancel-subscription?email=…,
 * náhled a zkušební odeslání Provozovateli.
 */

declare(strict_types=1);

class ReminderTest extends Pneukarnik_REST_Test_Case {

	private const PROVOZOVATEL = 'servis@example.test';

	/** Jarní Sezóna od 15. 3., Připomínky 14 dní předem, tedy od 1. 3. */
	private const BEFORE_SPRING = '2027-03-05 10:00';

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
		$this->set_seasons( [ '03-15', '04-30' ], [ '10-15', '11-30' ] );
		update_option( 'pneukarnik_reminder_days', 14 );
		update_option( 'pneukarnik_email', self::PROVOZOVATEL );
		update_option( 'pneukarnik_phone', '+420 775 565 326' );
		$this->tyres = $this->create_service( 60, true, 'Přezutí' );
		$this->capture_mails();
	}

	public function test_reminder_goes_only_to_customers_who_consented(): void {
		$this->book_with_consent( 'ano@example.test', true, '09:00' );
		$this->book_with_consent( 'ne@example.test', false, '11:00' );

		$this->run_reminders_at( self::BEFORE_SPRING );

		$this->assertSame( [ [ 'ano@example.test' ] ], array_column( $this->mails, 'to' ) );
	}

	public function test_one_reminder_per_email_and_season_even_with_more_bookings(): void {
		$this->book_with_consent( 'jan@example.test', true, '09:00' );
		$this->book_with_consent( 'JAN@example.test', true, '11:00' );

		$this->run_reminders_at( self::BEFORE_SPRING );
		$this->run_reminders_at( '2027-03-06 10:00' );

		$this->assertCount( 1, $this->mails );
	}

	public function test_next_season_gets_its_own_reminder(): void {
		$this->book_with_consent( 'jan@example.test', true );
		$this->run_reminders_at( self::BEFORE_SPRING );

		$this->run_reminders_at( '2027-10-05 10:00' );

		$this->assertCount( 2, $this->mails );
		$this->assertStringContainsString( 'podzim', mb_strtolower( $this->mails[1]['subject'] ) );
	}

	/**
	 * @return array<string, array{0:string}>
	 */
	public static function outside_of_the_window(): array {
		return [
			'den před oknem'   => [ '2027-02-28 23:00' ],
			'začátek Sezóny'   => [ '2027-03-15 08:00' ],
			'uprostřed Sezóny' => [ '2027-04-10 08:00' ],
			'mezi Sezónami'    => [ '2027-07-01 08:00' ],
		];
	}

	/**
	 * @dataProvider outside_of_the_window
	 */
	public function test_nothing_is_sent_outside_of_the_days_before_a_season( string $now ): void {
		$this->book_with_consent( 'jan@example.test', true );

		$this->run_reminders_at( $now );

		$this->assertSame( [], $this->mails );
	}

	public function test_first_day_of_the_window_is_sent(): void {
		$this->book_with_consent( 'jan@example.test', true );

		$this->run_reminders_at( '2027-03-01 00:30' );

		$this->assertCount( 1, $this->mails );
	}

	public function test_zero_days_before_season_turns_reminders_off(): void {
		$this->book_with_consent( 'jan@example.test', true );
		update_option( 'pneukarnik_reminder_days', 0 );

		$this->run_reminders_at( self::BEFORE_SPRING );

		$this->assertSame( [], $this->mails );
	}

	public function test_reminder_has_prefilled_booking_and_unsubscribe_link(): void {
		$this->book_with_consent( 'jan@example.test', true );

		$this->run_reminders_at( self::BEFORE_SPRING );

		$mail = $this->mail_to( 'jan@example.test' );
		$this->assertStringContainsString( 'přezout', mb_strtolower( $mail['subject'] ) );
		$this->assertStringContainsString( '15. 3. 2027', $mail['text'] );
		$this->assertSame( 'jan@example.test', $this->prefill( $this->prefill_token_from( $mail ) )->get_data()['email'] );
		$unsubscribe = $this->unsubscribe_url_from( $mail );
		$this->assertContains( [ 'List-Unsubscribe', '<' . $unsubscribe . '>' ], $mail['headers'] );
		$this->assertContains( [ 'List-Unsubscribe-Post', 'List-Unsubscribe=One-Click' ], $mail['headers'] );
	}

	public function test_reminder_link_prefills_seasonal_services_and_stored_wheels_but_not_the_date(): void {
		$storage = $this->create_service( 30, true, 'Uskladnění' );
		update_post_meta( $this->tyres, '_service_ask_stored_wheels', '1' );
		$this->book_services_with_consent( [ $this->tyres, $storage ], [ 'stored_wheels' => true ] );

		$prefill = $this->reminder_prefill();

		$this->assertSame(
			[
				'name'          => 'Jan Novák',
				'phone'         => '+420 603 123 456',
				'email'         => 'jan@example.test',
				'plate'         => '1AB2345',
				'vehicle'       => '',
				'service_ids'   => [ $this->tyres, $storage ],
				'stored_wheels' => true,
			],
			$prefill
		);
	}

	public function test_services_come_from_the_last_seasonal_booking_and_contacts_from_the_last_booking(): void {
		$oil = $this->create_service( 30, false, 'Výměna oleje' );
		update_post_meta( $this->tyres, '_service_ask_stored_wheels', '1' );
		$this->book_services_with_consent( [ $oil, $this->tyres ], [ 'stored_wheels' => true ] );
		$this->book_services_with_consent( [ $oil ], [ 'phone' => '+420 777 000 111' ], '2027-02-11' );

		$prefill = $this->reminder_prefill();

		$this->assertSame( '+420 777 000 111', $prefill['phone'], 'Kontakty z poslední Rezervace' );
		$this->assertSame( [ $this->tyres ], $prefill['service_ids'], 'Jen sezónní Služby z poslední sezónní Rezervace' );
		$this->assertTrue( $prefill['stored_wheels'] );
	}

	public function test_seasonal_services_are_read_when_the_link_is_opened(): void {
		$oil = $this->create_service( 30, false, 'Výměna oleje' );
		$this->book_services_with_consent( [ $oil ] );
		$this->run_reminders_at( self::BEFORE_SPRING );
		$token = $this->prefill_token_from( $this->mail_to( 'jan@example.test' ) );

		$this->assertArrayNotHasKey( 'service_ids', $this->prefill( $token )->get_data() );
		$this->book_services_with_consent( [ $this->tyres ], [], '2027-03-08' );
		$this->assertSame( [ $this->tyres ], $this->prefill( $token )->get_data()['service_ids'] );
	}

	public function test_without_seasonal_booking_the_link_prefills_only_contacts(): void {
		$oil = $this->create_service( 30, false, 'Výměna oleje' );
		$this->book_services_with_consent( [ $oil ] );

		$prefill = $this->reminder_prefill();

		$this->assertSame( 'jan@example.test', $prefill['email'] );
		$this->assertArrayNotHasKey( 'service_ids', $prefill );
		$this->assertArrayNotHasKey( 'stored_wheels', $prefill );
	}

	public function test_seasonal_service_no_longer_bookable_online_is_left_out(): void {
		$storage = $this->create_service( 30, true, 'Uskladnění' );
		$this->book_services_with_consent( [ $this->tyres, $storage ] );
		update_post_meta( $storage, '_service_bookable', '' );

		$this->assertSame( [ $this->tyres ], $this->reminder_prefill()['service_ids'] );

		update_post_meta( $this->tyres, '_service_bookable', '' );
		$this->assertArrayNotHasKey( 'service_ids', $this->reminder_prefill(), 'Žádná Služba online: jen kontakty' );
	}

	public function test_reminder_link_stops_working_once_the_booking_is_anonymised_or_forged(): void {
		$this->book_with_consent( 'jan@example.test', true );
		$this->run_reminders_at( self::BEFORE_SPRING );
		$token = $this->prefill_token_from( $this->mail_to( 'jan@example.test' ) );

		$this->assertSame( 404, $this->prefill( substr( $token, 0, -1 ) . ( str_ends_with( $token, '0' ) ? '1' : '0' ) )->get_status() );

		Pneukarnik_Clock::freeze( '2030-01-01 12:00' );
		do_action( Pneukarnik_GDPR::CRON_HOOK );
		$this->assertSame( 404, $this->prefill( $token )->get_status() );
	}

	public function test_unsubscribe_link_withdraws_consent_right_away(): void {
		$this->book_with_consent( 'jan@example.test', true );
		$this->run_reminders_at( self::BEFORE_SPRING );
		$token = $this->unsubscribe_token_from( $this->mail_to( 'jan@example.test' ) );

		$response = $this->rest( 'POST', '/unsubscribe', [ 'token' => $token ] );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'unsubscribe.done', $response->get_data()['code'] );
		$this->assertSame( 'no-store', $response->get_headers()['Cache-Control'] );
		$this->run_reminders_at( '2027-10-05 10:00' );
		$this->assertCount( 1, $this->mails );

		$again = $this->rest( 'POST', '/unsubscribe', [ 'token' => $token ] );
		$this->assertSame( 'unsubscribe.done', $again->get_data()['code'] );
	}

	public function test_unsubscribe_page_withdraws_consent_on_opening(): void {
		$this->book_with_consent( 'jan@example.test', true );
		$this->run_reminders_at( self::BEFORE_SPRING );

		$this->go_to( $this->unsubscribe_url_from( $this->mail_to( 'jan@example.test' ) ) );

		$this->assertSame( 'odhlaseni', Pneukarnik_Booking_Pages::current() );
		$this->assertSame( 'unsubscribe.done', Pneukarnik_Booking_Pages::unsubscription()['code'] );
		$this->assertFalse( Pneukarnik_Booking_Pages::unsubscription()['legacy'] );
		$this->run_reminders_at( '2027-10-05 10:00' );
		$this->assertCount( 1, $this->mails );
	}

	/**
	 * @return array<string, array{0:mixed}>
	 */
	public static function invalid_tokens(): array {
		return [
			'chybí'        => [ null ],
			'nesmysl'      => [ 'abc' ],
			'cizí podpis'  => [ '1.' . str_repeat( 'a', 64 ) ],
			'není řetězec' => [ [ 'token' ] ],
			'neexistující' => [ '999999.' . str_repeat( '0', 64 ) ],
		];
	}

	/**
	 * @dataProvider invalid_tokens
	 */
	public function test_invalid_unsubscribe_token_is_refused( mixed $token ): void {
		$response = $this->rest( 'POST', '/unsubscribe', [ 'token' => $token ] );

		$this->assertSame( 404, $response->get_status() );
		$this->assertSame( 'unsubscribe.invalid_token', $response->get_data()['code'] );
	}

	public function test_token_of_one_customer_cannot_be_changed_to_another(): void {
		$this->book_with_consent( 'jan@example.test', true, '09:00' );
		$this->book_with_consent( 'eva@example.test', true, '11:00' );
		$this->run_reminders_at( self::BEFORE_SPRING );
		[ $id, $signature ] = explode( '.', $this->unsubscribe_token_from( $this->mail_to( 'jan@example.test' ) ) );

		$response = $this->rest( 'POST', '/unsubscribe', [ 'token' => ( (int) $id + 1 ) . '.' . $signature ] );

		$this->assertSame( 404, $response->get_status() );
		$this->mails = [];
		$this->run_reminders_at( '2027-10-05 10:00' );
		$this->assertCount( 2, $this->mails );
	}

	public function test_new_consent_after_unsubscribing_subscribes_again(): void {
		$this->book_with_consent( 'jan@example.test', true );
		$this->run_reminders_at( self::BEFORE_SPRING );
		$this->rest( 'POST', '/unsubscribe', [ 'token' => $this->unsubscribe_token_from( $this->mail_to( 'jan@example.test' ) ) ] );

		Pneukarnik_Clock::freeze( '2027-06-01 12:00' );
		$this->book_with_consent( 'jan@example.test', true, '09:00', '2027-06-10' );
		$this->mails = [];
		$this->run_reminders_at( '2027-10-05 10:00' );

		$this->assertCount( 1, $this->mails );
	}

	public function test_link_from_before_unsubscribing_and_consenting_again_no_longer_works(): void {
		$this->book_with_consent( 'jan@example.test', true );
		$this->run_reminders_at( self::BEFORE_SPRING );
		$old = $this->unsubscribe_token_from( $this->mail_to( 'jan@example.test' ) );
		$this->rest( 'POST', '/unsubscribe', [ 'token' => $old ] );
		Pneukarnik_Clock::freeze( '2027-06-01 12:00' );
		$this->book_with_consent( 'jan@example.test', true, '09:00', '2027-06-10' );

		$response = $this->rest( 'POST', '/unsubscribe', [ 'token' => $old ] );

		$this->assertSame( 404, $response->get_status() );
		$this->run_reminders_at( '2027-10-05 10:00' );
		$this->assertCount( 1, $this->mails );
	}

	public function test_another_booking_with_consent_keeps_the_unsubscribe_link_working(): void {
		$this->book_with_consent( 'jan@example.test', true, '09:00' );
		$this->run_reminders_at( self::BEFORE_SPRING );
		$token = $this->unsubscribe_token_from( $this->mail_to( 'jan@example.test' ) );
		Pneukarnik_Clock::freeze( '2027-03-06 12:00' );
		$this->book_with_consent( 'jan@example.test', true, '09:00', '2027-03-10' );

		$this->assertSame( 'unsubscribe.done', $this->rest( 'POST', '/unsubscribe', [ 'token' => $token ] )->get_data()['code'] );
		$this->mails = [];
		$this->run_reminders_at( '2027-10-05 10:00' );
		$this->assertSame( [], $this->mails );
	}

	public function test_booking_without_consent_keeps_earlier_consent(): void {
		$this->book_with_consent( 'jan@example.test', true, '09:00' );
		$this->book_with_consent( 'jan@example.test', false, '11:00' );

		$this->run_reminders_at( self::BEFORE_SPRING );

		$this->assertCount( 1, $this->mails );
	}

	public function test_old_cancel_subscription_link_unsubscribes_from_the_old_list(): void {
		Pneukarnik_Subscriptions::consent( 'stary@example.test', Pneukarnik_Subscriptions::LEGACY, 'import' );
		$this->assertTrue( Pneukarnik_Subscriptions::is_active( 'stary@example.test', Pneukarnik_Subscriptions::LEGACY ) );

		$this->go_to( home_url( '/odhlaseni/?email=Stary%40example.test' ) );

		$this->assertSame( 'odhlaseni', Pneukarnik_Booking_Pages::current() );
		$this->assertSame(
			[
				'code'   => 'unsubscribe.done',
				'legacy' => true,
			],
			Pneukarnik_Booking_Pages::unsubscription()
		);
		$this->assertFalse( Pneukarnik_Subscriptions::is_active( 'stary@example.test', Pneukarnik_Subscriptions::LEGACY ) );
	}

	public function test_old_cancel_subscription_address_redirects_permanently_to_unsubscribe_page(): void {
		$redirects = [];
		add_filter(
			'wp_redirect',
			static function ( string $location, int $status ) use ( &$redirects ): bool {
				$redirects[] = [ $status, $location ];
				return false; // Bez odeslání hlaviček a exit.
			},
			10,
			2
		);

		$this->go_to( home_url( '/cancel-subscription?email=stary%40example.test' ) );
		Pneukarnik_Booking_Pages::redirect_old_unsubscribe();

		$this->assertSame( [ [ 301, home_url( '/odhlaseni/?email=stary%40example.test' ) ] ], $redirects );
	}

	public function test_old_link_does_not_touch_reminder_consent(): void {
		$this->book_with_consent( 'jan@example.test', true );
		Pneukarnik_Subscriptions::consent( 'jan@example.test', Pneukarnik_Subscriptions::LEGACY, 'import' );

		$response = $this->rest( 'POST', '/unsubscribe', [ 'email' => 'jan@example.test' ] );

		$this->assertSame( 'unsubscribe.done', $response->get_data()['code'] );
		$this->assertFalse( Pneukarnik_Subscriptions::is_active( 'jan@example.test', Pneukarnik_Subscriptions::LEGACY ) );
		$this->assertTrue( Pneukarnik_Subscriptions::is_active( 'jan@example.test', Pneukarnik_Subscriptions::REMINDER ) );
	}

	public function test_old_link_answers_the_same_for_unknown_email(): void {
		$response = $this->rest( 'POST', '/unsubscribe', [ 'email' => 'nikdo@example.test' ] );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'unsubscribe.done', $response->get_data()['code'] );
	}

	public function test_legacy_consent_alone_gets_no_reminder(): void {
		Pneukarnik_Subscriptions::consent( 'stary@example.test', Pneukarnik_Subscriptions::LEGACY, 'import' );

		$this->run_reminders_at( self::BEFORE_SPRING );

		$this->assertSame( [], $this->mails );
	}

	public function test_preview_shows_season_start_and_number_of_recipients(): void {
		$this->book_with_consent( 'jan@example.test', true, '09:00' );
		$this->book_with_consent( 'eva@example.test', true, '11:00' );
		$this->book_with_consent( 'ne@example.test', false, '13:00' );
		$this->log_in_as( 'pneukarnik_manager' );

		$response = $this->rest( 'GET', '/admin/reminder' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			[
				'enabled'     => true,
				'days_before' => 14,
				'season'      => 'spring',
				'season_from' => '2027-03-15',
				'send_from'   => '2027-03-01',
				'recipients'  => 2,
			],
			$response->get_data()
		);
	}

	public function test_preview_counts_only_those_still_waiting_for_this_season(): void {
		$this->book_with_consent( 'jan@example.test', true, '09:00' );
		$this->run_reminders_at( self::BEFORE_SPRING );
		$this->book_with_consent( 'eva@example.test', true, '11:00', '2027-03-08' );
		$this->log_in_as( 'pneukarnik_manager' );

		$this->assertSame( 1, $this->rest( 'GET', '/admin/reminder' )->get_data()['recipients'] );
	}

	public function test_test_reminder_goes_only_to_the_provozovatel(): void {
		$this->book_with_consent( 'jan@example.test', true );
		$this->log_in_as( 'pneukarnik_manager' );
		$this->mails = [];

		$response = $this->rest( 'POST', '/admin/reminder/test', [ 'email' => 'utocnik@example.test' ] );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( self::PROVOZOVATEL, $response->get_data()['sent_to'] );
		$this->assertSame( [ [ self::PROVOZOVATEL ] ], array_column( $this->mails, 'to' ) );
		$this->assertStringContainsString( 'přezout', mb_strtolower( $this->mails[0]['subject'] ) );

		// Zkouška se nepočítá jako odeslaná Připomínka.
		$this->mails = [];
		$this->run_reminders_at( self::BEFORE_SPRING );
		$this->assertSame( [ [ 'jan@example.test' ] ], array_column( $this->mails, 'to' ) );
	}

	public function test_test_reminder_needs_provozovatel_email(): void {
		update_option( 'pneukarnik_email', '' );
		$this->log_in_as( 'administrator' );
		$this->mails = [];

		$response = $this->rest( 'POST', '/admin/reminder/test' );

		$this->assertSame( 409, $response->get_status() );
		$this->assertSame( 'reminder.no_provozovatel_email', $response->get_data()['code'] );
		$this->assertSame( [], $this->mails );
	}

	/**
	 * @return array<string, array{0:string|null}>
	 */
	public static function without_permission(): array {
		return [
			'nepřihlášený'   => [ null ],
			'předplatitel'   => [ 'subscriber' ],
			'jen prohlížení' => [ 'pneukarnik_viewer' ],
		];
	}

	/**
	 * @dataProvider without_permission
	 */
	public function test_preview_and_test_need_permission_to_manage( ?string $role ): void {
		if ( null !== $role ) {
			$this->log_in_as( $role );
		}
		$this->mails = [];

		$this->assertContains( $this->rest( 'GET', '/admin/reminder' )->get_status(), [ 401, 403 ] );
		$this->assertContains( $this->rest( 'POST', '/admin/reminder/test' )->get_status(), [ 401, 403 ] );
		$this->assertSame( [], $this->mails );
	}

	public function test_reminders_are_scheduled(): void {
		$this->assertNotFalse( wp_next_scheduled( Pneukarnik_Reminder::CRON_HOOK ) );
	}

	/**
	 * Online Rezervace s/bez souhlasu s Připomínkou. Den mimo Sezónu, aby šla i nesezónní pravidla.
	 */
	private function book_with_consent( string $email, bool $consent, string $time = '09:00', string $date = '2027-02-10' ): void {
		$response = $this->book(
			$this->tyres,
			$date,
			$time,
			[
				'email'            => $email,
				'consent_reminder' => $consent,
			]
		);
		$this->assertSame( 201, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );
		$this->mails = []; // Potvrzení Rezervace nás tu nezajímá.
	}

	/**
	 * Online Rezervace Služeb se souhlasem s Připomínkou, další údaje podle $overrides.
	 *
	 * @param list<int>            $services
	 * @param array<string, mixed> $overrides
	 */
	private function book_services_with_consent( array $services, array $overrides = [], string $date = '2027-02-10' ): void {
		$response = $this->book( $services, $date, '09:00', $overrides + [ 'consent_reminder' => true ] );
		$this->assertSame( 201, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );
		$this->mails = [];
	}

	/**
	 * Údaje z odkazu „Objednat přezutí“ v Připomínce pro jan@example.test.
	 *
	 * @return array<string, mixed>
	 */
	private function reminder_prefill(): array {
		$this->run_reminders_at( self::BEFORE_SPRING );
		$response = $this->prefill( $this->prefill_token_from( $this->mail_to( 'jan@example.test' ) ) );
		$this->assertSame( 200, $response->get_status() );
		return $response->get_data();
	}

	private function run_reminders_at( string $now ): void {
		Pneukarnik_Clock::freeze( $now );
		do_action( Pneukarnik_Reminder::CRON_HOOK );
	}

	/**
	 * @param array{html:string} $mail
	 */
	private function unsubscribe_url_from( array $mail ): string {
		$this->assertSame( 1, preg_match( '~"(http[^"]*/odhlaseni/\?t=[0-9]+\.[0-9a-f]{64})"~', $mail['html'], $m ), 'Odkaz na odhlášení v e‑mailu' );
		return html_entity_decode( $m[1] );
	}

	/**
	 * @param array{html:string} $mail
	 */
	private function unsubscribe_token_from( array $mail ): string {
		parse_str( (string) wp_parse_url( $this->unsubscribe_url_from( $mail ), PHP_URL_QUERY ), $query );
		return (string) $query['t'];
	}
}
