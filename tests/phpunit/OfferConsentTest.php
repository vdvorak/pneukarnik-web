<?php
/**
 * Souhlasy z potvrzení Rezervace zadané Provozovatelem (ADR 0004): Zákazník neviděl políčka z online
 * rezervace, proto mu je potvrzení nabídne, když jeho e‑mail ještě nemá souhlas ani odvolání. Odkaz
 * otevře stránku se dvěma nezaškrtnutými políčky, souhlas se zaškrtnutými druhy zapíše až tlačítko.
 */

declare(strict_types=1);

class OfferConsentTest extends Pneukarnik_REST_Test_Case {

	/** Jarní Sezóna od 15. 3., Připomínky 14 dní předem, tedy od 1. 3. */
	private const BEFORE_SPRING = '2027-03-05 10:00';

	private const CUSTOMER = 'jan@example.test';

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
		$this->tyres = $this->create_service( 60, true, 'Přezutí' );
		$this->capture_mails();
	}

	public function tear_down(): void {
		unset( $_SERVER['REQUEST_METHOD'] );
		$_POST = [];
		parent::tear_down();
	}

	public function test_confirmation_of_a_booking_by_provozovatel_offers_the_choice(): void {
		$this->book_by_provozovatel( self::CUSTOMER );

		$mail = $this->mail_to( self::CUSTOMER );

		$url = home_url( '/odhlaseni/?s=' . $this->offer_token_from( $mail ) );
		foreach ( [ $mail['html'], $mail['text'] ] as $body ) {
			$this->assertStringContainsString( 'Chcete před sezónou připomenout přezutí nebo dostávat naše akce?', $body );
			$this->assertStringContainsString( 'Vybrat e‑maily', $body );
		}
		$this->assertStringContainsString( $url, $mail['text'] );
	}

	public function test_ticked_kinds_get_consent_that_counts_right_away(): void {
		$this->book_by_provozovatel( self::CUSTOMER, '2027-03-20' );
		$token = $this->offer_token_from( $this->mail_to( self::CUSTOMER ) );
		Pneukarnik_Clock::freeze( '2027-02-02 18:30' );

		$redirects = $this->save_offer( $token, [ 'reminder' => '1' ] );

		$this->assertSame( [ [ 303, add_query_arg( 'ulozeno', '1', Pneukarnik_Subscriptions::settings_url( self::CUSTOMER ) ) ] ], $redirects->list );
		$this->assertSame(
			[
				'consent_source' => 'potvrzeni',
				'consented_at'   => '2027-02-02 18:30:00',
			],
			$this->row( self::CUSTOMER, Pneukarnik_Subscriptions::REMINDER, 'consent_source, consented_at' )
		);
		$this->assertNull( $this->row( self::CUSTOMER, Pneukarnik_Subscriptions::PROMOTIONS, 'id' ), 'Akce nezaškrtl' );
		$this->mails = [];
		$this->run_reminders_at( self::BEFORE_SPRING );
		$this->assertSame( [ [ self::CUSTOMER ] ], array_column( $this->mails, 'to' ), 'Termín 20. 3. ještě neproběhl' );
	}

	public function test_settings_page_after_saving_shows_the_choice(): void {
		$this->book_by_provozovatel( self::CUSTOMER );
		$this->save_offer(
			$this->offer_token_from( $this->mail_to( self::CUSTOMER ) ),
			[
				'reminder'   => '1',
				'promotions' => '1',
			]
		);

		$this->go_to( add_query_arg( 'ulozeno', '1', Pneukarnik_Subscriptions::settings_url( self::CUSTOMER ) ) );
		$page = Pneukarnik_Booking_Pages::email_settings();

		$this->assertSame( [ 'settings', 'saved' ], [ $page['state'], $page['notice'] ] );
		$this->assertSame( [ true, true ], [ $page['settings']['reminder'], $page['settings']['promotions'] ] );
	}

	public function test_saving_with_nothing_ticked_writes_nothing_and_stays_on_the_page(): void {
		$this->book_by_provozovatel( self::CUSTOMER );
		$token = $this->offer_token_from( $this->mail_to( self::CUSTOMER ) );

		$redirects = $this->save_offer( $token, [] );

		$this->assertSame( [ [ 303, home_url( '/odhlaseni/?s=' . $token ) ] ], $redirects->list );
		$this->assertSame( '', Pneukarnik_Subscriptions::settings_url( self::CUSTOMER ) );
	}

	public function test_opening_the_link_only_asks_and_writes_nothing(): void {
		$this->book_by_provozovatel( self::CUSTOMER );
		$token = $this->offer_token_from( $this->mail_to( self::CUSTOMER ) );

		$this->go_to( home_url( '/odhlaseni/?s=' . $token ) );
		$page = Pneukarnik_Booking_Pages::email_settings();

		$this->assertSame( 'offer', $page['state'] );
		$this->assertSame(
			[
				'email' => self::CUSTOMER,
				'url'   => home_url( '/odhlaseni/?s=' . $token ),
			],
			$page['offer']
		);
		$this->assertSame( '', Pneukarnik_Subscriptions::settings_url( self::CUSTOMER ), 'Otevření odkazu (i skenerem pošty) souhlas nezapíše' );
	}

	public function test_online_booking_never_has_the_offer(): void {
		foreach ( [ false, true ] as $i => $consent ) {
			$email    = "web{$i}@example.test";
			$response = $this->book(
				$this->tyres,
				'2027-02-10',
				sprintf( '%02d:00', 9 + $i ),
				[
					'email'            => $email,
					'consent_reminder' => $consent,
				]
			);
			$this->assertSame( 201, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );

			$this->assertStringNotContainsString( 'Vybrat e‑maily', $this->mail_to( $email )['text'] );
		}
	}

	/**
	 * @return array<string, array{0:callable(self):mixed}>
	 */
	public static function existing_relations(): array {
		return [
			'souhlas v online Rezervaci' => [ static fn( self $test ) => $test->book_online() ],
			'souhlas'                    => [ static fn() => Pneukarnik_Subscriptions::consent( self::CUSTOMER, Pneukarnik_Subscriptions::REMINDER, 'nastaveni' ) ],
			'odvolání'                   => [
				static function () {
					Pneukarnik_Subscriptions::consent( self::CUSTOMER, Pneukarnik_Subscriptions::PROMOTIONS, 'nastaveni' );
					parse_str( (string) wp_parse_url( Pneukarnik_Subscriptions::settings_url( self::CUSTOMER ), PHP_URL_QUERY ), $query );
					Pneukarnik_Subscriptions::withdraw_everything( $query['k'], 'nastaveni' );
				},
			],
			'starý souhlas o slevách'    => [ static fn() => Pneukarnik_Subscriptions::import_legacy( self::CUSTOMER, '2024-05-01 10:00:00' ) ],
			'odhlášení starým odkazem'   => [ static fn() => Pneukarnik_Subscriptions::withdraw_legacy( self::CUSTOMER ) ],
		];
	}

	/**
	 * @dataProvider existing_relations
	 * @param callable(self):mixed $relation
	 */
	public function test_email_with_a_relation_to_offers_does_not_get_the_offer( callable $relation ): void {
		$relation( $this );
		$this->mails = [];

		$this->book_by_provozovatel( 'Jan@Example.test', '2027-02-11' );

		$this->assertCount( 1, $this->mails );
		$this->assertStringNotContainsString( 'Vybrat e‑maily', $this->mails[0]['text'] );
	}

	public function test_cancellation_email_has_no_offer(): void {
		$this->book_by_provozovatel( self::CUSTOMER );
		$token       = $this->cancel_token_from( $this->mail_to( self::CUSTOMER ) );
		$this->mails = [];

		$this->assertSame( 200, $this->cancel( $token )->get_status() );

		$this->assertStringNotContainsString( 'Vybrat e‑maily', $this->mail_to( self::CUSTOMER )['text'] );
	}

	/**
	 * @return array<string, array{0:string}>
	 */
	public static function invalid_tokens(): array {
		return [
			'nesmysl'      => [ 'abc' ],
			'cizí podpis'  => [ '{id}.' . str_repeat( 'a', 64 ) ],
			'neexistující' => [ '999999.' . str_repeat( '0', 64 ) ],
		];
	}

	/**
	 * @dataProvider invalid_tokens
	 */
	public function test_invalid_link_writes_nothing( string $token ): void {
		$booking = $this->book_by_provozovatel( self::CUSTOMER );
		$token   = str_replace( '{id}', (string) $booking['id'], $token );

		$redirects = $this->save_offer( $token, [ 'reminder' => '1' ] );

		$this->assertSame( [ [ 303, home_url( '/odhlaseni/?s=' . $token ) ] ], $redirects->list );
		$this->assertSame( '', Pneukarnik_Subscriptions::settings_url( self::CUSTOMER ) );
		$this->go_to( home_url( '/odhlaseni/?s=' . $token ) );
		$this->assertSame( 'invalid', Pneukarnik_Booking_Pages::email_settings()['state'] );
	}

	public function test_link_of_one_booking_cannot_be_moved_to_another(): void {
		$this->book_by_provozovatel( self::CUSTOMER );
		[ , $signature ] = explode( '.', $this->offer_token_from( $this->mail_to( self::CUSTOMER ) ) );
		$eva             = $this->book_by_provozovatel( 'eva@example.test', '2027-02-11' );

		$this->save_offer( $eva['id'] . '.' . $signature, [ 'reminder' => '1' ] );

		$this->assertSame( '', Pneukarnik_Subscriptions::settings_url( 'eva@example.test' ) );
	}

	public function book_online(): void {
		$response = $this->book(
			$this->tyres,
			'2027-02-10',
			'09:00',
			[
				'email'            => self::CUSTOMER,
				'consent_reminder' => true,
			]
		);
		$this->assertSame( 201, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );
	}

	/**
	 * Telefonická objednávka, e‑maily předtím zahodí.
	 *
	 * @return array<string, mixed>
	 */
	private function book_by_provozovatel( string $email, string $date = '2027-02-10' ): array {
		$this->mails = [];
		$this->log_in_as( 'pneukarnik_manager' );
		$booking = $this->admin_booking( $this->admin_book( $this->tyres, $date, '09:00', [ 'email' => $email ] ) );
		wp_set_current_user( 0 );
		return $booking;
	}

	/**
	 * Uložení zaškrtnutých políček na stránce z odkazu.
	 *
	 * @param array<string,string> $ticked
	 * @return object{list:list<array{0:int,1:string}>}
	 */
	private function save_offer( string $token, array $ticked ): object {
		$redirects = $this->catch_redirects();
		$this->go_to( home_url( '/odhlaseni/?s=' . $token ) );
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = [ 'volba' => 'ulozit' ] + $ticked;
		Pneukarnik_Booking_Pages::handle_offer_form();
		unset( $_SERVER['REQUEST_METHOD'] );
		$_POST = [];
		return $redirects;
	}

	/**
	 * @param array{html:string} $mail
	 */
	private function offer_token_from( array $mail ): string {
		$this->assertSame( 1, preg_match( '~"http[^"]*/odhlaseni/\?s=([0-9]+\.[0-9a-f]{64})"~', $mail['html'], $m ), 'Odkaz na souhlasy' );
		return $m[1];
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function row( string $email, string $purpose, string $columns ): ?array {
		global $wpdb;
		// $columns jsou pevné názvy sloupců z testů.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row( $wpdb->prepare( "SELECT {$columns} FROM %i WHERE email = %s AND purpose = %s", Pneukarnik_DB::subscriptions_table(), $email, $purpose ), ARRAY_A );
	}

	private function run_reminders_at( string $now ): void {
		Pneukarnik_Clock::freeze( $now );
		do_action( Pneukarnik_Reminder::CRON_HOOK );
	}
}
