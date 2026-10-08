<?php
/**
 * Stránka nastavení e‑mailů z Nabídek a připomínek (/odhlaseni/?k=…): zvlášť Připomínka přezutí
 * a Akce, „Neposílat nic“, zapnutí jako výslovný souhlas, odhlášení jedním kliknutím z pošty
 * (List-Unsubscribe) a neplatné odkazy. Odkazy odeslané dřív ověřuje ReminderTest.
 */

declare(strict_types=1);

class EmailSettingsTest extends Pneukarnik_REST_Test_Case {

	/** Jarní Sezóna od 15. 3., Připomínky 14 dní předem, tedy od 1. 3. */
	private const BEFORE_SPRING = '2027-03-05 10:00';

	private const BEFORE_AUTUMN = '2027-10-05 10:00';

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

	public function test_reminder_links_to_settings_of_its_email(): void {
		$this->book_online( 'jan@example.test' );
		$this->run_reminders_at( self::BEFORE_SPRING );

		$response = $this->rest( 'GET', '/email-settings', [ 'key' => $this->key_from( $this->mail_to( 'jan@example.test' ) ) ] );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'no-store', $response->get_headers()['Cache-Control'] );
		$this->assertSame(
			[
				'email'      => 'jan@example.test',
				'reminder'   => true,
				'promotions' => true,
			],
			$response->get_data()
		);
	}

	public function test_opening_the_settings_changes_nothing(): void {
		$this->book_online( 'jan@example.test' );

		$this->go_to( Pneukarnik_Subscriptions::settings_url( 'jan@example.test' ) );
		$page = Pneukarnik_Booking_Pages::email_settings();

		$this->assertSame( [ 'settings', '' ], [ $page['state'], $page['notice'] ] );
		$this->assertTrue( $page['settings']['reminder'] );
		$this->run_reminders_at( self::BEFORE_SPRING );
		$this->assertCount( 1, $this->mails, 'Otevření odkazu (i skenerem pošty) nic neodhlásí' );
	}

	public function test_reminder_turned_off_does_not_come_but_promotions_stay(): void {
		$this->book_online( 'jan@example.test' );

		$response = $this->save( 'jan@example.test', reminder: false, promotions: true );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( [ false, true ], [ $response->get_data()['reminder'], $response->get_data()['promotions'] ] );
		$this->run_reminders_at( self::BEFORE_SPRING );
		$this->assertSame( [], $this->mails );
		$this->assertTrue( Pneukarnik_Subscriptions::is_active( 'jan@example.test', Pneukarnik_Subscriptions::REVIEW ), 'Žádost o hodnocení se ukládáním nemění' );
	}

	public function test_turning_the_reminder_on_again_is_an_explicit_consent_with_time(): void {
		$this->book_online( 'jan@example.test' );
		$this->save( 'jan@example.test', reminder: false, promotions: true );
		Pneukarnik_Clock::freeze( '2027-02-20 08:15' );

		$this->save( 'jan@example.test', reminder: true, promotions: true );

		$this->assertSame(
			[
				'consent_source' => 'nastaveni',
				'consented_at'   => '2027-02-20 08:15:00',
				'withdrawn_at'   => null,
			],
			$this->row( 'jan@example.test', Pneukarnik_Subscriptions::REMINDER, 'consent_source, consented_at, withdrawn_at' )
		);
		$this->run_reminders_at( self::BEFORE_SPRING );
		$this->assertCount( 1, $this->mails );
	}

	public function test_saving_what_already_comes_keeps_the_claim_from_the_booking(): void {
		$this->book_online( 'jan@example.test' );

		$this->save( 'jan@example.test', reminder: true, promotions: true );

		$this->assertSame(
			[
				'claimed_at'   => '2027-02-01 12:00:00',
				'consented_at' => null,
			],
			$this->row( 'jan@example.test', Pneukarnik_Subscriptions::REMINDER, 'claimed_at, consented_at' )
		);
	}

	public function test_promotions_turned_off_also_withdraw_the_old_consent_to_discounts(): void {
		$this->book_online( 'jan@example.test' );
		Pneukarnik_Subscriptions::consent( 'jan@example.test', Pneukarnik_Subscriptions::LEGACY, 'import' );

		$this->save( 'jan@example.test', reminder: true, promotions: false );

		$this->assertFalse( Pneukarnik_Subscriptions::is_active( 'jan@example.test', Pneukarnik_Subscriptions::PROMOTIONS ) );
		$this->assertFalse( Pneukarnik_Subscriptions::is_active( 'jan@example.test', Pneukarnik_Subscriptions::LEGACY ) );
		$this->assertTrue( Pneukarnik_Subscriptions::is_active( 'jan@example.test', Pneukarnik_Subscriptions::REMINDER ) );
	}

	public function test_old_consent_to_discounts_shows_as_promotions_and_reminder_can_be_turned_on(): void {
		Pneukarnik_Subscriptions::import_legacy( 'stary@example.test', '2024-05-01 10:00:00' );
		$this->assertSame( [ true, false ], [ $this->settings( 'stary@example.test' )['promotions'], $this->settings( 'stary@example.test' )['reminder'] ] );

		$this->save( 'stary@example.test', reminder: true, promotions: true );

		$this->assertSame( 'nastaveni', $this->row( 'stary@example.test', Pneukarnik_Subscriptions::REMINDER, 'consent_source' )['consent_source'] );
		$this->assertNull( $this->row( 'stary@example.test', Pneukarnik_Subscriptions::PROMOTIONS, 'id' ), 'Akce chodí ze starého souhlasu, nový není potřeba' );
		$this->run_reminders_at( self::BEFORE_SPRING );
		$this->assertSame( [ [ 'stary@example.test' ] ], array_column( $this->mails, 'to' ) );
	}

	public function test_nothing_withdraws_every_kind_including_the_review_request(): void {
		$this->book_online( 'jan@example.test' );
		Pneukarnik_Subscriptions::consent( 'jan@example.test', Pneukarnik_Subscriptions::LEGACY, 'import' );

		$response = $this->rest(
			'POST',
			'/email-settings',
			[
				'key'     => $this->key( 'jan@example.test' ),
				'nothing' => true,
			]
		);

		$this->assertSame( [ false, false ], [ $response->get_data()['reminder'], $response->get_data()['promotions'] ] );
		foreach ( [ ...Pneukarnik_Subscriptions::KINDS, Pneukarnik_Subscriptions::LEGACY ] as $purpose ) {
			$this->assertSame( 'nastaveni', $this->row( 'jan@example.test', $purpose, 'withdrawn_source' )['withdrawn_source'], $purpose );
		}
	}

	public function test_key_keeps_working_after_turning_off_and_on(): void {
		$this->book_online( 'jan@example.test' );
		$key = $this->key( 'jan@example.test' );

		$this->save( 'jan@example.test', reminder: false, promotions: false );
		$this->save( 'jan@example.test', reminder: true, promotions: true );

		$this->assertSame( 200, $this->rest( 'GET', '/email-settings', [ 'key' => $key ] )->get_status() );
		$this->assertSame( $key, $this->key( 'jan@example.test' ) );
	}

	/**
	 * @return array<string, array{0:mixed}>
	 */
	public static function invalid_keys(): array {
		return [
			'chybí'        => [ null ],
			'nesmysl'      => [ 'abc' ],
			'cizí podpis'  => [ '1.' . str_repeat( 'a', 64 ) ],
			'není řetězec' => [ [ 'key' ] ],
			'neexistující' => [ '999999.' . str_repeat( '0', 64 ) ],
		];
	}

	/**
	 * @dataProvider invalid_keys
	 */
	public function test_invalid_key_changes_nothing_and_reveals_nothing( mixed $key ): void {
		$this->book_online( 'jan@example.test' );

		$read = $this->rest( 'GET', '/email-settings', [ 'key' => $key ] );
		$save = $this->rest(
			'POST',
			'/email-settings',
			[
				'key'     => $key,
				'nothing' => true,
			]
		);

		foreach ( [ $read, $save ] as $response ) {
			$this->assertSame( 404, $response->get_status() );
			$this->assertSame( [ 'code', 'message', 'data' ], array_keys( $response->get_data() ) );
			$this->assertSame( 'unsubscribe.invalid_token', $response->get_data()['code'] );
		}
		$this->assertTrue( Pneukarnik_Subscriptions::is_active( 'jan@example.test', Pneukarnik_Subscriptions::REMINDER ) );
	}

	public function test_key_of_one_customer_cannot_be_changed_to_another(): void {
		$this->book_online( 'jan@example.test', '09:00' );
		$this->book_online( 'eva@example.test', '11:00' );
		[ , $signature ] = explode( '.', $this->key( 'jan@example.test' ) );
		$eva_id          = $this->row( 'eva@example.test', Pneukarnik_Subscriptions::REMINDER, 'id' )['id'];

		$response = $this->rest(
			'POST',
			'/email-settings',
			[
				'key'     => $eva_id . '.' . $signature,
				'nothing' => true,
			]
		);

		$this->assertSame( 404, $response->get_status() );
		$this->assertTrue( Pneukarnik_Subscriptions::is_active( 'eva@example.test', Pneukarnik_Subscriptions::REMINDER ) );
	}

	public function test_key_stops_working_once_personal_data_are_erased(): void {
		$this->book_online( 'jan@example.test' );
		$key = $this->key( 'jan@example.test' );

		Pneukarnik_Subscriptions::erase( 'jan@example.test' );

		$this->assertSame( 404, $this->rest( 'GET', '/email-settings', [ 'key' => $key ] )->get_status() );
		$this->assertSame( '', Pneukarnik_Subscriptions::settings_url( 'jan@example.test' ) );
	}

	public function test_one_click_unsubscribe_from_the_mail_withdraws_everything(): void {
		$this->book_online( 'jan@example.test' );
		$this->run_reminders_at( self::BEFORE_SPRING );
		$mail = $this->mail_to( 'jan@example.test' );
		$this->assertContains( [ 'List-Unsubscribe', '<' . Pneukarnik_Subscriptions::settings_url( 'jan@example.test' ) . '>' ], $mail['headers'] );

		// Poštovní klient pošle POST List-Unsubscribe=One-Click na adresu z hlavičky (RFC 8058).
		$this->go_to( Pneukarnik_Subscriptions::settings_url( 'jan@example.test' ) );
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = [ 'List-Unsubscribe' => 'One-Click' ];
		$page                      = Pneukarnik_Booking_Pages::email_settings();

		$this->assertSame( [ 'settings', 'nothing' ], [ $page['state'], $page['notice'] ] );
		foreach ( Pneukarnik_Subscriptions::KINDS as $purpose ) {
			$this->assertSame( 'jedno-kliknuti', $this->row( 'jan@example.test', $purpose, 'withdrawn_source' )['withdrawn_source'], $purpose );
		}
		$this->mails = [];
		$this->run_reminders_at( self::BEFORE_AUTUMN );
		$this->assertSame( [], $this->mails );
	}

	public function test_settings_form_saves_and_redirects_back_with_the_result(): void {
		$this->book_online( 'jan@example.test' );
		$url       = Pneukarnik_Subscriptions::settings_url( 'jan@example.test' );
		$redirects = $this->catch_redirects();

		$this->go_to( $url );
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = [
			'promotions' => '1',
			'volba'      => 'ulozit',
		];
		Pneukarnik_Booking_Pages::handle_email_settings_form();

		$this->assertSame( [ [ 303, add_query_arg( 'ulozeno', '1', $url ) ] ], $redirects->list );
		$this->assertSame( [ false, true ], [ $this->settings( 'jan@example.test' )['reminder'], $this->settings( 'jan@example.test' )['promotions'] ] );
	}

	public function test_settings_form_nothing_button_withdraws_everything(): void {
		$this->book_online( 'jan@example.test' );
		$url       = Pneukarnik_Subscriptions::settings_url( 'jan@example.test' );
		$redirects = $this->catch_redirects();

		$this->go_to( $url );
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = [
			'reminder'   => '1',
			'promotions' => '1',
			'volba'      => 'nic',
		];
		Pneukarnik_Booking_Pages::handle_email_settings_form();

		$this->assertSame( [ [ 303, add_query_arg( 'nic', '1', $url ) ] ], $redirects->list );
		$this->assertFalse( Pneukarnik_Subscriptions::is_active( 'jan@example.test', Pneukarnik_Subscriptions::REVIEW ) );
	}

	public function test_settings_page_with_invalid_key_shows_nothing(): void {
		$this->book_online( 'jan@example.test' );
		[ $id ] = explode( '.', $this->key( 'jan@example.test' ) );

		$this->go_to( home_url( '/odhlaseni/?k=' . $id . '.' . str_repeat( '0', 64 ) ) );

		$this->assertSame(
			[
				'state'    => 'invalid',
				'notice'   => '',
				'settings' => null,
			],
			Pneukarnik_Booking_Pages::email_settings()
		);
	}

	/**
	 * Online Rezervace před Připomínkami, aby Zákazník u nás už byl.
	 */
	private function book_online( string $email, string $time = '09:00' ): void {
		$response = $this->book( $this->tyres, '2027-02-10', $time, [ 'email' => $email ] );
		$this->assertSame( 201, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );
		$this->mails = [];
	}

	private function save( string $email, bool $reminder, bool $promotions ): WP_REST_Response {
		return $this->rest(
			'POST',
			'/email-settings',
			[
				'key'        => $this->key( $email ),
				'reminder'   => $reminder,
				'promotions' => $promotions,
			]
		);
	}

	/**
	 * @return array{email:string,reminder:bool,promotions:bool}
	 */
	private function settings( string $email ): array {
		$response = $this->rest( 'GET', '/email-settings', [ 'key' => $this->key( $email ) ] );
		$this->assertSame( 200, $response->get_status() );
		return $response->get_data();
	}

	/** Klíč z odkazu na stránku nastavení, který by e‑mailu poslala Připomínka přezutí. */
	private function key( string $email ): string {
		parse_str( (string) wp_parse_url( Pneukarnik_Subscriptions::settings_url( $email ), PHP_URL_QUERY ), $query );
		return (string) $query['k'];
	}

	/**
	 * @param array{html:string} $mail
	 */
	private function key_from( array $mail ): string {
		$this->assertSame( 1, preg_match( '~"http[^"]*/odhlaseni/\?k=([0-9]+\.[0-9a-f]{64})"~', $mail['html'], $m ), 'Odkaz na nastavení e‑mailů' );
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

	/**
	 * Přesměrování zachytí bez odeslání hlaviček a exit.
	 *
	 * @return object{list:list<array{0:int,1:string}>}
	 */
	private function catch_redirects(): object {
		$redirects = new class() {
			/** @var list<array{0:int,1:string}> */
			public array $list = [];
		};
		add_filter(
			'wp_redirect',
			static function ( string $location, int $status ) use ( $redirects ): bool {
				$redirects->list[] = [ $status, $location ];
				return false;
			},
			10,
			2
		);
		return $redirects;
	}

	private function run_reminders_at( string $now ): void {
		Pneukarnik_Clock::freeze( $now );
		do_action( Pneukarnik_Reminder::CRON_HOOK );
	}
}
