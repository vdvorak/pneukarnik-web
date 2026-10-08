<?php
/**
 * Stránka administrace E‑maily Zákazníkům: Připomínka Termínu, kolika Zákazníkům může který druh
 * Nabídek a připomínek dnes přijít, Připomínka přezutí přesunutá z Nastavení, Žádost o hodnocení
 * (i upozornění na chybějící Place ID), oprávnění jako Nastavení.
 * Ukládání a zkušební odeslání z formuláře ověřuje Playwright (emaily-zakaznikum.spec.ts).
 */

declare(strict_types=1);

class CustomerEmailsPageTest extends Pneukarnik_REST_Test_Case {

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
		update_option( 'pneukarnik_email', 'servis@example.test' );
		$this->tyres = $this->create_service( 60, true, 'Přezutí' );
		$this->capture_mails();
	}

	public function test_page_shows_how_many_customers_each_kind_can_reach_today(): void {
		$this->book_online( 'byl@example.test', '2027-02-10' );
		$this->book_online( 'jeste-nebyl@example.test', '2027-02-25' );
		$this->book_online( 'odmitl@example.test', '2027-02-10', '11:00', refuse: true );
		Pneukarnik_Subscriptions::consent( 'jen-pripominka@example.test', Pneukarnik_Subscriptions::REMINDER, 'test' );
		Pneukarnik_Subscriptions::import_legacy( 'stary@example.test', '2020-01-01 00:00:00' );
		Pneukarnik_Subscriptions::import_legacy( 'byl@example.test', '2020-01-01 00:00:00' ); // Akce jen jednou.
		Pneukarnik_Clock::freeze( '2027-02-20 12:00' );

		$page = $this->render();

		$this->assertMatchesRegularExpression( '/Připomínka přezutí 2 /u', $page );
		$this->assertMatchesRegularExpression( '/Akce \(Rozesílky\) 2 /u', $page );
		$this->assertMatchesRegularExpression( '/Žádost o hodnocení 1 /u', $page );
	}

	public function test_page_has_the_reminder_with_its_preview_and_intro(): void {
		update_option( Pneukarnik_Reminder::OPTION_INTRO, 'Vlastní úvod Připomínky' );
		$this->book_online( 'byl@example.test', '2027-02-10' );
		Pneukarnik_Clock::freeze( '2027-02-20 12:00' );

		$page = $this->render( false );

		$this->assertStringContainsString( 'name="reminder_days" value="14"', $page );
		$this->assertStringContainsString( 'Vlastní úvod Připomínky</textarea>', $page );
		$this->assertStringContainsString( 'Jarní Sezóna začíná 15. 3. 2027, Připomínky se začnou posílat 1. 3. 2027.', $page );
		$this->assertStringContainsString( 'Počet příjemců: 1', $page );
		$this->assertStringContainsString( 'Po uložení poslat zkušební Připomínku přezutí na servis@example.test', $page );
	}

	public function test_page_has_the_termin_reminder_switch_and_hour(): void {
		$page = $this->render( false );

		$this->assertMatchesRegularExpression( '/name="termin_reminder_enabled" value="1"\s+checked/', $page );
		$this->assertMatchesRegularExpression( '/<option value="16"\s+selected/', $page );
		$this->assertStringContainsString( 'Po uložení poslat zkušební Připomínku Termínu na servis@example.test', $page );

		Pneukarnik_Termin_Reminder::save( false, 9 );
		$page = $this->render( false );

		$this->assertDoesNotMatchRegularExpression( '/name="termin_reminder_enabled" value="1"\s+checked/', $page );
		$this->assertMatchesRegularExpression( '/<option value="9"\s+selected/', $page );
	}

	public function test_page_has_the_review_request_switch_intro_and_test(): void {
		update_option( 'pneukarnik_reviews_place_id', 'ChIJ-test' );
		update_option( Pneukarnik_Review_Request::OPTION_INTRO, 'Vlastní úvod Žádosti' );

		$page = $this->render( false );

		$this->assertMatchesRegularExpression( '/name="review_request_enabled" value="1"\s+checked/', $page );
		$this->assertStringContainsString( 'Vlastní úvod Žádosti</textarea>', $page );
		$this->assertStringContainsString( 'Po uložení poslat zkušební Žádost o hodnocení na servis@example.test', $page );
		$this->assertStringNotContainsString( 'chybí ID místa', $page );

		Pneukarnik_Review_Request::save( false, 'Vlastní úvod Žádosti' );

		$this->assertDoesNotMatchRegularExpression( '/name="review_request_enabled" value="1"\s+checked/', $this->render( false ) );
	}

	public function test_page_warns_that_without_place_id_no_review_request_is_sent(): void {
		update_option( 'pneukarnik_reviews_place_id', '' );

		$page = $this->render();

		$this->assertStringContainsString( 'Žádost o hodnocení se neposílá: chybí ID místa (Place ID) v Nastavení', $page );
		$this->assertStringNotContainsString( 'poslat zkušební Žádost o hodnocení', $page );
	}

	public function test_review_request_audience_leaves_out_those_who_got_it(): void {
		update_option( 'pneukarnik_reviews_place_id', 'ChIJ-test' );
		$this->book_online( 'dostal@example.test', '2027-02-10' );
		$this->book_online( 'nedostal@example.test', '2027-02-12' );
		Pneukarnik_Clock::freeze( '2027-02-11 10:00' );
		do_action( Pneukarnik_Review_Request::CRON_HOOK );
		Pneukarnik_Clock::freeze( '2027-02-20 12:00' );

		$page = $this->render();

		$this->assertMatchesRegularExpression( '/Žádost o hodnocení 1 /u', $page );
		$this->assertStringContainsString( 'Už ji dostalo: 1', $page );
	}

	public function test_reminder_uses_the_intro_from_the_page(): void {
		update_option( Pneukarnik_Reminder::OPTION_INTRO, 'Vlastní úvod Připomínky' );
		$this->book_online( 'byl@example.test', '2027-02-10' );

		Pneukarnik_Clock::freeze( '2027-03-05 10:00' );
		do_action( Pneukarnik_Reminder::CRON_HOOK );

		$this->assertStringContainsString( 'Vlastní úvod Připomínky', $this->mail_to( 'byl@example.test' )['text'] );
	}

	public function test_settings_no_longer_have_the_reminder(): void {
		$this->log_in_as( 'administrator' );

		ob_start();
		Pneukarnik_Admin_Settings::render_page();
		$settings = (string) ob_get_clean();

		$this->assertStringNotContainsString( 'reminder_days', $settings );
		$this->assertStringNotContainsString( 'reminder_test', $settings );
		$this->assertStringNotContainsString( 'email_text[reminder]', $settings );
		$this->assertStringContainsString( 'page=' . Pneukarnik_Admin_Customer_Emails::PAGE, $settings );
	}

	/**
	 * @return array<string, array{0:string}>
	 */
	public static function without_permission(): array {
		return [
			'předplatitel'     => [ 'subscriber' ],
			'jen prohlížení'   => [ 'pneukarnik_viewer' ],
			'správa Rezervací' => [ 'pneukarnik_manager' ],
		];
	}

	/**
	 * @dataProvider without_permission
	 */
	public function test_page_does_not_open_without_permission_to_settings( string $role ): void {
		$this->log_in_as( $role );

		$this->expectException( WPDieException::class );
		ob_start();
		try {
			Pneukarnik_Admin_Customer_Emails::render_page();
		} finally {
			ob_end_clean();
		}
	}

	/**
	 * Stránka jako administrátor: text bez značek s jednoduchými mezerami, nebo HTML.
	 */
	private function render( bool $text = true ): string {
		$this->log_in_as( 'administrator' );
		ob_start();
		Pneukarnik_Admin_Customer_Emails::render_page();
		$html = (string) ob_get_clean();
		return $text ? trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $html ) ) ) . ' ' : $html;
	}

	/**
	 * Online Rezervace s/bez odmítnutí Nabídek a připomínek.
	 */
	private function book_online( string $email, string $date, string $time = '09:00', bool $refuse = false ): void {
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
		$this->mails = []; // Potvrzení Rezervace nás tu nezajímá.
	}
}
