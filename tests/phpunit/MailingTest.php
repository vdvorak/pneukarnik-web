<?php
/**
 * Rozesílka: Akce platné teď nebo začínající do 14 dní s úvodní větou e‑mailům, které smějí dostávat
 * Akce (i ze starého souhlasu „informace o slevách“, podrobně OffersTest), každému jednou, po dávkách.
 * Odeslat jde až po zkušebním e‑mailu. Vytvoření a zkušební e‑mail z formuláře ověřuje Playwright
 * (emaily-zakaznikum.spec.ts).
 */

declare(strict_types=1);

class MailingTest extends Pneukarnik_REST_Test_Case {

	private const PROVOZOVATEL = 'servis@example.test';

	private const PHONE = '+420 775 565 326';

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
		update_option( 'pneukarnik_phone', self::PHONE );
		$this->tyres = $this->create_service( 60, false, 'Přezutí' );
		$this->capture_mails();
	}

	public function test_mailing_goes_once_to_everyone_who_may_get_promotions_including_old_discount_consents(): void {
		$this->book_online( 'byl@example.test', '2027-02-10' );
		$this->book_online( 'jeste-nebyl@example.test', '2027-02-25' );
		$this->book_online( 'odmitl@example.test', '2027-02-10', '11:00', refuse: true );
		Pneukarnik_Subscriptions::consent( 'jen-pripominka@example.test', Pneukarnik_Subscriptions::REMINDER, 'test' );
		Pneukarnik_Subscriptions::import_legacy( 'stary@example.test', '2020-01-01 00:00:00' );
		Pneukarnik_Subscriptions::import_legacy( 'byl@example.test', '2020-01-01 00:00:00' );
		Pneukarnik_Clock::freeze( '2027-02-20 12:00' );

		$this->send_now( $this->mailing() );

		$this->assertEqualsCanonicalizing( [ [ 'byl@example.test' ], [ 'stary@example.test' ] ], array_column( $this->mails, 'to' ) );
	}

	public function test_switching_off_promotions_on_the_settings_page_leaves_out_of_all_further_mailings(): void {
		Pneukarnik_Subscriptions::consent( 'jan@example.test', Pneukarnik_Subscriptions::PROMOTIONS, 'test' );
		Pneukarnik_Subscriptions::import_legacy( 'stary@example.test', '2020-01-01 00:00:00' );
		$this->send_now( $this->mailing() );
		$this->assertCount( 2, $this->mails );

		foreach ( [ 'jan@example.test', 'stary@example.test' ] as $email ) {
			$this->assertSame( Pneukarnik_Subscriptions::DONE, Pneukarnik_Subscriptions::save_settings( $this->settings_key( $email ), true, false ) );
		}
		$this->mails = [];
		$this->send_now( $this->mailing() );

		$this->assertSame( [], $this->mails );
	}

	public function test_mailing_cannot_be_sent_without_a_test_email(): void {
		Pneukarnik_Subscriptions::consent( 'jan@example.test', Pneukarnik_Subscriptions::PROMOTIONS, 'test' );
		$id = $this->mailing();

		$this->assertSame( Pneukarnik_Mailing::NOT_TESTED, Pneukarnik_Mailing::send( $id ) );
		do_action( Pneukarnik_Mailing::CRON_HOOK );
		$this->assertSame( [], $this->mails );
		$this->assertSame( Pneukarnik_Mailing::DRAFT, Pneukarnik_Mailing::find( $id )['status'] );

		$this->assertSame( self::PROVOZOVATEL, Pneukarnik_Mailing::send_test( $id ) );
		$this->assertSame( Pneukarnik_Mailing::DONE, Pneukarnik_Mailing::send( $id ) );
	}

	public function test_changing_the_content_after_the_test_needs_another_test(): void {
		$promotion = $this->promotion( 'Zaváděcí cena', '2027-01-15', '2027-03-31' );
		$id        = $this->mailing( [ $promotion ], 'Úvod' );
		Pneukarnik_Mailing::send_test( $id );

		$this->assertSame( $id, Pneukarnik_Mailing::save( $id, 'Úvod', [ $promotion ] ) );
		$this->assertNotNull( Pneukarnik_Mailing::find( $id )['test_sent_at'], 'Uložení beze změny test nezruší' );

		Pneukarnik_Mailing::save( $id, 'Jiný úvod', [ $promotion ] );
		$this->assertSame( Pneukarnik_Mailing::NOT_TESTED, Pneukarnik_Mailing::send( $id ) );
	}

	public function test_email_has_the_intro_and_each_promotion_with_validity_and_a_booking_link(): void {
		$geometry = $this->create_service( 60, false, 'Geometrie' );
		$phone    = $this->create_service( 60, false, 'Oprava skel' );
		update_post_meta( $geometry, '_service_price', '1500' );
		update_post_meta( $geometry, '_service_price_from', '1' );
		update_post_meta( $phone, '_service_bookable', '' );
		$priced = $this->promotion( 'Zaváděcí cena', '2027-01-15', '2027-03-31', $geometry, 990, 'Jen pro osobní auta.' );
		$free   = $this->promotion( 'Kontrola skla zdarma', '2027-02-10', '2028-01-10', $phone, 0 );
		Pneukarnik_Subscriptions::consent( 'jan@example.test', Pneukarnik_Subscriptions::PROMOTIONS, 'test' );

		$this->send_now( $this->mailing( [ $priced, $free ], 'Máme pro vás jarní akce.' ) );

		$mail = $this->mail_to( 'jan@example.test' );
		$this->assertSame( 'Akce: Geometrie, Oprava skel', $mail['subject'] );
		$this->assertStringContainsString( 'Máme pro vás jarní akce.', $mail['text'] );
		$this->assertStringContainsString( "Služba: Geometrie\nAkce: Zaváděcí cena\nAkční cena: 990\u{00A0}Kč, běžně od 1\u{00A0}500\u{00A0}Kč\nPlatí: 15. 1. – 31. 3. 2027", $mail['text'] );
		$this->assertStringContainsString( 'Jen pro osobní auta.', $mail['text'] );
		$this->assertStringContainsString( 'Rezervovat: ' . home_url( '/rezervace/?sluzba=geometrie' ), $mail['text'] );
		$this->assertStringContainsString( "Služba: Oprava skel\nAkce: Kontrola skla zdarma\nPlatí: 10. 2. 2027 – 10. 1. 2028", $mail['text'] );
		$this->assertStringContainsString( 'Tuto službu objednáváme jen telefonicky: ' . self::PHONE . '.', $mail['text'] );
		$this->assertSame( 1, substr_count( $mail['text'], 'Rezervovat:' ) );
		$this->assertStringContainsString( 'souhlasili', $mail['text'] );
		$this->assertStringContainsString( esc_url( Pneukarnik_Subscriptions::settings_url( 'jan@example.test' ) ), $mail['html'] );
		$this->assertContains( [ 'List-Unsubscribe', '<' . Pneukarnik_Subscriptions::settings_url( 'jan@example.test' ) . '>' ], $mail['headers'] );
		$this->assertContains( [ 'List-Unsubscribe-Post', 'List-Unsubscribe=One-Click' ], $mail['headers'] );
		$this->assertSame( [ self::PROVOZOVATEL ], $mail['reply_to'] );
	}

	public function test_booking_link_preselects_the_service_in_the_booking_form(): void {
		$geometry = $this->create_service( 60, false, 'Geometrie' );
		Pneukarnik_Subscriptions::consent( 'jan@example.test', Pneukarnik_Subscriptions::PROMOTIONS, 'test' );
		$this->send_now( $this->mailing( [ $this->promotion( 'Zaváděcí cena', '2027-01-15', '2027-03-31', $geometry ) ] ) );
		$this->assertSame( 1, preg_match( '~Rezervovat: (\S+)~', $this->mail_to( 'jan@example.test' )['text'], $m ) );

		parse_str( (string) wp_parse_url( $m[1], PHP_URL_QUERY ), $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- odkaz z e‑mailu jako požadavek na formulář.
		try {
			$this->assertSame( $geometry, Pneukarnik_Booking_Pages::form_config()['selected'] );
		} finally {
			$_GET = [];
		}
	}

	public function test_customer_who_did_not_refuse_gets_the_footer_about_the_visit(): void {
		$this->book_online( 'byl@example.test', '2027-02-10' );
		Pneukarnik_Clock::freeze( '2027-02-20 12:00' );

		$this->send_now( $this->mailing() );

		$this->assertStringContainsString( 'neodmítli', $this->mail_to( 'byl@example.test' )['text'] );
	}

	public function test_mailing_goes_out_in_batches_and_is_sent_when_nobody_is_left(): void {
		for ( $i = 1; $i <= 55; $i++ ) {
			Pneukarnik_Subscriptions::consent( "zakaznik{$i}@example.test", Pneukarnik_Subscriptions::PROMOTIONS, 'test' );
		}
		$id = $this->mailing();
		Pneukarnik_Mailing::send_test( $id );
		$this->mails = [];
		Pneukarnik_Mailing::send( $id );

		do_action( Pneukarnik_Mailing::CRON_HOOK );
		$this->assertCount( 50, $this->mails );
		$this->assertSame( Pneukarnik_Mailing::SENDING, Pneukarnik_Mailing::find( $id )['status'] );

		do_action( Pneukarnik_Mailing::CRON_HOOK );
		do_action( Pneukarnik_Mailing::CRON_HOOK );
		$this->assertCount( 55, $this->mails );
		$this->assertCount( 55, array_unique( array_merge( ...array_column( $this->mails, 'to' ) ) ) );
		$mailing = Pneukarnik_Mailing::find( $id );
		$this->assertSame( Pneukarnik_Mailing::SENT, $mailing['status'] );
		$this->assertSame( 55, $mailing['sent_count'] );
	}

	public function test_mailing_is_sent_only_once_even_when_the_job_runs_again_or_concurrently(): void {
		Pneukarnik_Subscriptions::consent( 'jan@example.test', Pneukarnik_Subscriptions::PROMOTIONS, 'test' );
		Pneukarnik_Subscriptions::consent( 'eva@example.test', Pneukarnik_Subscriptions::PROMOTIONS, 'test' );
		$nested = false;
		add_action(
			'phpmailer_init',
			static function () use ( &$nested ): void {
				if ( ! $nested ) {
					$nested = true;
					do_action( Pneukarnik_Mailing::CRON_HOOK ); // Souběžné spuštění uprostřed odesílání.
				}
			}
		);

		$this->send_now( $this->mailing() );
		do_action( Pneukarnik_Mailing::CRON_HOOK );

		$this->assertTrue( $nested );
		$this->assertEqualsCanonicalizing( [ [ 'jan@example.test' ], [ 'eva@example.test' ] ], array_column( $this->mails, 'to' ) );
	}

	public function test_failed_sending_is_tried_again_next_time(): void {
		Pneukarnik_Subscriptions::consent( 'jan@example.test', Pneukarnik_Subscriptions::PROMOTIONS, 'test' );
		$id = $this->mailing();
		Pneukarnik_Mailing::send_test( $id );
		$this->mails = [];
		Pneukarnik_Mailing::send( $id );
		$fail = static fn(): bool => false;
		add_filter( 'pre_wp_mail', $fail );
		do_action( Pneukarnik_Mailing::CRON_HOOK );
		remove_filter( 'pre_wp_mail', $fail );
		$this->assertSame( Pneukarnik_Mailing::SENDING, Pneukarnik_Mailing::find( $id )['status'] );

		do_action( Pneukarnik_Mailing::CRON_HOOK );

		$this->assertSame( [ [ 'jan@example.test' ] ], array_column( $this->mails, 'to' ) );
		$this->assertSame( 1, Pneukarnik_Mailing::find( $id )['sent_count'] );
	}

	public function test_only_promotions_valid_now_or_starting_within_14_days_can_be_chosen(): void {
		Pneukarnik_Clock::freeze( '2027-03-01 12:00' );
		$hidden_service = $this->create_service( 60, false, 'Koncept Služby' );
		wp_update_post(
			[
				'ID'          => $hidden_service,
				'post_status' => 'draft',
			]
		);
		$valid       = $this->promotion( 'Platí', '2027-02-01', '2027-03-01' );
		$soon        = $this->promotion( 'Za 14 dní', '2027-03-15', '2027-03-31' );
		$later       = $this->promotion( 'Za 15 dní', '2027-03-16', '2027-03-31' );
		$ended       = $this->promotion( 'Skončila', '2027-02-01', '2027-02-28' );
		$draft       = $this->promotion( 'Koncept', '2027-02-01', '2027-03-31', status: 'draft' );
		$unpublished = $this->promotion( 'Nezveřejněná Služba', '2027-02-01', '2027-03-31', $hidden_service );
		$choosable   = array_map( static fn( Pneukarnik_Promotion $p ): int => $p->id, Pneukarnik_Mailing::promotions() );

		$this->assertSame( [ $valid, $soon ], $choosable );
		$this->assertSame( Pneukarnik_Mailing::NO_PROMOTIONS, Pneukarnik_Mailing::save( null, 'Úvod', [ $later, $ended, $draft, $unpublished ] ) );
		$id = Pneukarnik_Mailing::save( null, 'Úvod', [ $soon, $later ] );
		$this->assertSame( [ $soon ], Pneukarnik_Mailing::find( (int) $id )['promotion_ids'] );
	}

	public function test_sent_mailing_cannot_be_changed_deleted_or_sent_again(): void {
		$promotion = $this->promotion( 'Zaváděcí cena', '2027-01-15', '2027-03-31' );
		$id        = $this->mailing( [ $promotion ] );
		$this->send_now( $id );

		$this->assertSame( Pneukarnik_Mailing::NOT_DRAFT, Pneukarnik_Mailing::save( $id, 'Jiný úvod', [ $promotion ] ) );
		$this->assertFalse( Pneukarnik_Mailing::delete( $id ) );
		$this->assertSame( Pneukarnik_Mailing::NOT_DRAFT, Pneukarnik_Mailing::send( $id ) );
		$this->assertNull( Pneukarnik_Mailing::send_test( $id ) );
	}

	public function test_draft_can_be_deleted(): void {
		$id = $this->mailing();

		$this->assertTrue( Pneukarnik_Mailing::delete( $id ) );
		$this->assertNull( Pneukarnik_Mailing::find( $id ) );
	}

	public function test_test_email_goes_only_to_the_provozovatel(): void {
		Pneukarnik_Subscriptions::consent( 'jan@example.test', Pneukarnik_Subscriptions::PROMOTIONS, 'test' );
		$id = $this->mailing( [ $this->promotion( 'Zaváděcí cena', '2027-01-15', '2027-03-31' ) ] );

		$this->assertSame( self::PROVOZOVATEL, Pneukarnik_Mailing::send_test( $id ) );
		do_action( Pneukarnik_Mailing::CRON_HOOK );

		$mail = $this->mail_to( self::PROVOZOVATEL );
		$this->assertSame( '[Zkouška] Akce: Přezutí', $mail['subject'] );
		$this->assertStringContainsString( 'Toto je zkušební Rozesílka', $mail['text'] );
		$this->assertCount( 1, $this->mails );
	}

	public function test_without_provozovatel_email_there_is_no_test_and_no_sending(): void {
		update_option( 'pneukarnik_email', '' );
		$id = $this->mailing();

		$this->assertNull( Pneukarnik_Mailing::send_test( $id ) );
		$this->assertSame( Pneukarnik_Mailing::NOT_TESTED, Pneukarnik_Mailing::send( $id ) );
	}

	public function test_erasing_personal_data_forgets_whom_mailings_went_to(): void {
		global $wpdb;
		Pneukarnik_Subscriptions::consent( 'jan@example.test', Pneukarnik_Subscriptions::PROMOTIONS, 'test' );
		$this->send_now( $this->mailing() );
		$eraser = apply_filters( 'wp_privacy_personal_data_erasers', [] )['pneukarnik-bookings']['callback'];

		$eraser( 'jan@example.test', 1 );

		$this->assertSame( '0', $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE email = %s', Pneukarnik_DB::mailing_recipients_table(), 'jan@example.test' ) ) );
	}

	public function test_admin_page_lists_mailings_with_their_state(): void {
		Pneukarnik_Subscriptions::consent( 'jan@example.test', Pneukarnik_Subscriptions::PROMOTIONS, 'test' );
		Pneukarnik_Subscriptions::consent( 'eva@example.test', Pneukarnik_Subscriptions::PROMOTIONS, 'test' );
		$this->send_now( $this->mailing( [ $this->promotion( 'Zaváděcí cena', '2027-01-15', '2027-03-31' ) ] ) );
		$id = $this->mailing( [ $this->promotion( 'Druhá', '2027-01-15', '2027-03-31' ) ] );
		Pneukarnik_Mailing::send_test( $id );
		Pneukarnik_Mailing::send( $id );
		$this->mailing( [ $this->promotion( 'Třetí', '2027-01-15', '2027-03-31' ) ] );

		$page = $this->render();

		$this->assertStringContainsString( 'Přezutí: Zaváděcí cena odeslaná: 2 (1. 2. 2027)', $page );
		$this->assertStringContainsString( 'Přezutí: Druhá odesílá se (zatím odesláno: 0)', $page );
		$this->assertStringContainsString( 'Přezutí: Třetí rozepsaná', $page );
	}

	public function test_mailing_page_has_the_promotions_recipients_preview_and_send_only_after_test(): void {
		Pneukarnik_Subscriptions::consent( 'jan@example.test', Pneukarnik_Subscriptions::PROMOTIONS, 'test' );
		$promotion = $this->promotion( 'Zaváděcí cena', '2027-01-15', '2027-03-31' );
		$id        = $this->mailing( [ $promotion ], 'Vlastní úvodní věta' );

		$page = $this->render( false, (string) $id );

		$this->assertMatchesRegularExpression( '/value="' . $promotion . '"\s+checked/', $page );
		$this->assertStringContainsString( 'Přezutí: Zaváděcí cena (15. 1. – 31. 3. 2027)', $page );
		$this->assertStringContainsString( 'Vlastní úvodní věta</textarea>', $page );
		$this->assertStringContainsString( 'Počet příjemců: 1', $page );
		$this->assertStringContainsString( 'Uložit a poslat zkušební e‑mail na ' . self::PROVOZOVATEL, $page );
		$this->assertStringContainsString( 'srcdoc="', $page );
		$this->assertMatchesRegularExpression( '/value="send"[^>]*disabled/', $page );

		Pneukarnik_Mailing::send_test( $id );

		$this->assertDoesNotMatchRegularExpression( '/value="send"[^>]*disabled/', $this->render( false, (string) $id ) );
	}

	public function test_mailings_are_scheduled(): void {
		$this->assertNotFalse( wp_next_scheduled( Pneukarnik_Mailing::CRON_HOOK ) );
	}

	/**
	 * Rozepsaná Rozesílka, bez Akcí s jednou platnou Akcí Přezutí.
	 *
	 * @param list<int> $promotions
	 */
	private function mailing( array $promotions = [], string $intro = 'Úvodní věta Rozesílky' ): int {
		$id = Pneukarnik_Mailing::save( null, $intro, $promotions ?: [ $this->promotion( 'Akce přezutí', '2027-01-15', '2027-03-31' ) ] );
		$this->assertIsInt( $id );
		return $id;
	}

	/**
	 * Zkušební e‑mail, odeslání hned a dávka plánované úlohy. Zkušební e‑mail se zahodí.
	 */
	private function send_now( int $id ): void {
		$before = count( $this->mails );
		$this->assertSame( self::PROVOZOVATEL, Pneukarnik_Mailing::send_test( $id ) );
		$this->mails = array_slice( $this->mails, 0, $before );
		$this->assertSame( Pneukarnik_Mailing::DONE, Pneukarnik_Mailing::send( $id ) );
		do_action( Pneukarnik_Mailing::CRON_HOOK );
	}

	private function promotion( string $title, string $from, string $to, ?int $service = null, int $price = 990, string $description = '', string $status = 'publish' ): int {
		$id = wp_insert_post(
			[
				'post_type'   => Pneukarnik_Promotion::POST_TYPE,
				'post_status' => $status,
				'post_title'  => $title,
				'meta_input'  => [
					'_promotion_service_id'  => (string) ( $service ?? $this->tyres ),
					'_promotion_price'       => (string) $price,
					'_promotion_description' => $description,
					'_promotion_valid_from'  => $from,
					'_promotion_valid_to'    => $to,
				],
			],
			true
		);
		$this->assertIsInt( $id );
		return $id;
	}

	private function settings_key( string $email ): string {
		return (string) wp_parse_args( (string) wp_parse_url( Pneukarnik_Subscriptions::settings_url( $email ), PHP_URL_QUERY ) )['k'];
	}

	/**
	 * Stránka E‑maily Zákazníkům (nebo Rozesílky) jako administrátor: text bez značek s jednoduchými
	 * mezerami, nebo HTML.
	 */
	private function render( bool $text = true, ?string $mailing = null ): string {
		$this->log_in_as( 'administrator' );
		if ( null !== $mailing ) {
			$_GET['mailing'] = $mailing;
		}
		ob_start();
		try {
			Pneukarnik_Admin_Customer_Emails::render_page();
		} finally {
			$html = (string) ob_get_clean();
			unset( $_GET['mailing'] );
		}
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
