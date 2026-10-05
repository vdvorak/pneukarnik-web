<?php
/**
 * E‑maily k Rezervaci: potvrzení a Zrušení Zákazníkovi, upozornění Provozovateli (zapínatelné).
 * HTML s textovou alternativou, editovatelné bloky Provozovatele.
 */

declare(strict_types=1);

class BookingEmailsTest extends Pneukarnik_REST_Test_Case {

	private const MONDAY       = '2027-03-01';
	private const CUSTOMER     = 'jan@example.test';
	private const PROVOZOVATEL = 'servis@example.test';

	private int $tyres;
	private int $balancing;

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
		$this->set_booking_rules( 30 );
		update_option( 'blogname', 'Pneuservis Kárník' );
		update_option( 'pneukarnik_cancellation_hours', 24 );
		update_option( 'pneukarnik_phone', '+420 775 565 326' );
		update_option( 'pneukarnik_email', self::PROVOZOVATEL );
		update_option( 'pneukarnik_address', 'Dobšická 10, 669 02 Znojmo' );
		update_option( 'pneukarnik_email_intro', 'Dobrý den, těšíme se na vás.' );
		update_option( 'pneukarnik_email_signature', "Jan Kárník\nPneuservis a autoservis" );
		update_option( 'pneukarnik_email_bring', "Technický průkaz\nPojistný šroub" );
		$this->tyres     = $this->create_service( 60, false, 'Přezutí' );
		$this->balancing = $this->create_service( 30, false, 'Vyvážení' );
		update_post_meta( $this->tyres, '_service_bring', 'Klíč od kol' );
		$this->capture_mails();
	}

	public function test_customer_gets_confirmation_with_everything_for_the_visit(): void {
		$this->book( [ $this->tyres, $this->balancing ], self::MONDAY, '09:00' );

		$mail = $this->mail_to( self::CUSTOMER );

		$this->assertSame( 'Potvrzení rezervace na pondělí 1. 3. 2027 v 9:00', $mail['subject'] );
		foreach ( [ $mail['html'], $mail['text'] ] as $body ) {
			$this->assertStringContainsString( 'Dobrý den, těšíme se na vás.', $body );
			$this->assertStringContainsString( 'pondělí 1. 3. 2027, 9:00–10:30', $body );
			$this->assertStringContainsString( 'Přezutí', $body );
			$this->assertStringContainsString( 'Vyvážení', $body );
			$this->assertStringContainsString( '1AB2345', $body );
			$this->assertStringContainsString( 'Dobšická 10, 669 02 Znojmo', $body );
			$this->assertStringContainsString( 'Technický průkaz', $body );
			$this->assertStringContainsString( 'Pojistný šroub', $body );
			$this->assertStringContainsString( 'Klíč od kol', $body );
			$this->assertStringContainsString( 'nejpozději 28. 2. 2027 v 9:00', $body );
			$this->assertStringContainsString( '+420 775 565 326', $body );
			$this->assertStringContainsString( 'Jan Kárník', $body );
			$this->assertStringContainsString( 'Pneuservis a autoservis', $body );
		}
	}

	public function test_subject_names_the_day_in_the_right_case(): void {
		$this->book( $this->tyres, '2027-03-03', '09:00' );

		$this->assertSame( 'Potvrzení rezervace na středu 3. 3. 2027 v 9:00', $this->mail_to( self::CUSTOMER )['subject'] );
		$this->assertSame( 'Nová rezervace: středa 3. 3. 2027 v 9:00, Jan Novák', $this->mail_to( self::PROVOZOVATEL )['subject'] );
	}

	public function test_cancel_link_in_the_confirmation_works(): void {
		$this->book( $this->tyres, self::MONDAY, '09:00' );
		$mail = $this->mail_to( self::CUSTOMER );

		$token = $this->cancel_token_from( $mail );

		$this->assertStringContainsString( home_url( '/rezervace/zruseni/?r=' . $token ), $mail['text'] );
		$this->assertSame( 'cancellation.allowed', $this->cancellation( $token )->get_data()['code'] );
	}

	public function test_emails_are_html_with_a_text_alternative(): void {
		$this->book( $this->tyres, self::MONDAY, '09:00' );

		foreach ( $this->mails as $mail ) {
			$this->assertStringContainsString( '<html', $mail['html'], $mail['subject'] );
			$this->assertNotSame( '', trim( $mail['text'] ), $mail['subject'] );
			$this->assertStringNotContainsString( '<', $mail['text'], $mail['subject'] );
		}
	}

	public function test_emails_share_the_template_with_logo_button_and_signature(): void {
		$this->book( $this->tyres, self::MONDAY, '09:00' );
		$customer     = $this->mail_to( self::CUSTOMER )['html'];
		$provozovatel = $this->mail_to( self::PROVOZOVATEL )['html'];

		foreach ( [ $customer, $provozovatel ] as $html ) {
			$this->assertMatchesRegularExpression( '~<img src="' . preg_quote( home_url( '/' ), '~' ) . '[^"]*email-logo\.png"[^>]* alt="Pneuservis Kárník"~', $html, 'Logo z vlastní domény s alt textem' );
			$this->assertStringContainsString( 'width="600"', $html );
		}
		$this->assertMatchesRegularExpression( '~<td style="background:#ffffff;border:2px solid #C0392B;border-radius:999px"><a href="[^"]*/rezervace/zruseni/\?r=~', $customer, 'Hlavní tlačítko Zrušit rezervaci' );
		$this->assertMatchesRegularExpression( '~<td style="background:#1F2933;border-radius:999px"><a href="[^"]*" style="[^"]*">Otevřít v administraci</a>~', $provozovatel, 'Hlavní tlačítko pro Provozovatele' );
		$this->assertMatchesRegularExpression( '~<td style="padding:12px 32px 28px;[^"]*">Jan Kárník<br />\s*Pneuservis a autoservis<br />\s*Pneuservis a autoservis Jan Kárník<br />\s*Dobšická 10~', $customer, 'Podpis s kontakty' );
	}

	public function test_editable_blocks_are_escaped_in_html(): void {
		update_option( 'pneukarnik_email_intro', 'Ceny <b>od</b> 500 Kč & víc' );

		$this->book( $this->tyres, self::MONDAY, '09:00' );
		$mail = $this->mail_to( self::CUSTOMER );

		$this->assertStringContainsString( 'Ceny &lt;b&gt;od&lt;/b&gt; 500 Kč &amp; víc', $mail['html'] );
		$this->assertStringContainsString( 'Ceny <b>od</b> 500 Kč & víc', $mail['text'] );
	}

	public function test_customer_can_reply_to_the_provozovatel(): void {
		$this->book( $this->tyres, self::MONDAY, '09:00' );

		$mail = $this->mail_to( self::CUSTOMER );

		$this->assertSame( 'Pneuservis Kárník', $mail['from_name'] );
		$this->assertSame( [ self::PROVOZOVATEL ], $mail['reply_to'] );
	}

	public function test_provozovatel_is_notified_about_a_new_online_booking(): void {
		$this->book(
			$this->tyres,
			self::MONDAY,
			'09:00',
			[
				'company'         => 'Novák s.r.o.',
				'vehicle'         => 'Škoda Octavia',
				'note'            => 'Prosím i kontrolu tlaku.',
				'leasing'         => true,
				'leasing_company' => 'ČSOB Leasing',
			]
		);

		$mail = $this->mail_to( self::PROVOZOVATEL );

		$this->assertSame( 'Nová rezervace: pondělí 1. 3. 2027 v 9:00, Jan Novák', $mail['subject'] );
		foreach ( [ $mail['html'], $mail['text'] ] as $body ) {
			foreach ( [ 'Přezutí', 'Jan Novák', 'Novák s.r.o.', '+420 603 123 456', self::CUSTOMER, '1AB2345', 'Škoda Octavia', 'Prosím i kontrolu tlaku.', 'ČSOB Leasing' ] as $expected ) {
				$this->assertStringContainsString( $expected, $body );
			}
		}
		$this->assertSame( [ self::CUSTOMER ], $mail['reply_to'] );
	}

	public function test_provozovatel_notification_about_new_bookings_can_be_turned_off(): void {
		update_option( 'pneukarnik_notify_created', '0' );

		$this->book( $this->tyres, self::MONDAY, '09:00' );

		$this->assertSame( [ [ self::CUSTOMER ] ], array_column( $this->mails, 'to' ) );
	}

	public function test_provozovatel_is_not_notified_about_bookings_entered_in_administration(): void {
		$this->log_in_as( 'administrator' );

		$this->admin_booking( $this->admin_book( $this->tyres, self::MONDAY, '09:00', [ 'email' => self::CUSTOMER ] ) );

		$this->assertSame( [ [ self::CUSTOMER ] ], array_column( $this->mails, 'to' ) );
	}

	public function test_customer_emails_end_with_current_contact_from_settings(): void {
		update_option( 'pneukarnik_company', 'Pneuservis Kárník s.r.o.' );
		update_option( 'pneukarnik_phone', '+420 600 111 222' );
		update_option( 'pneukarnik_email', 'nova@example.test' );
		update_option( 'pneukarnik_address', 'Nová 1, 669 02 Znojmo' );
		$this->book( $this->tyres, self::MONDAY, '09:00' );
		$confirmation = $this->mail_to( self::CUSTOMER );
		$this->mails  = [];

		$this->cancel( $this->cancel_token_from( $confirmation ) );

		foreach ( [ $confirmation, $this->mail_to( self::CUSTOMER ) ] as $mail ) {
			foreach ( [ $mail['html'], $mail['text'] ] as $body ) {
				foreach ( [ 'Pneuservis Kárník s.r.o.', 'Nová 1, 669 02 Znojmo', '+420 600 111 222', 'nova@example.test' ] as $contact ) {
					$this->assertStringContainsString( $contact, $body, $mail['subject'] );
				}
			}
			$this->assertSame( [ 'nova@example.test' ], $mail['reply_to'] );
		}
	}

	public function test_customer_and_provozovatel_get_email_after_cancellation_by_link(): void {
		$this->book( $this->tyres, self::MONDAY, '09:00' );
		$token       = $this->cancel_token_from( $this->mail_to( self::CUSTOMER ) );
		$this->mails = [];

		$this->cancel( $token );

		$customer     = $this->mail_to( self::CUSTOMER );
		$provozovatel = $this->mail_to( self::PROVOZOVATEL );
		$this->assertSame( 'Rezervace na pondělí 1. 3. 2027 v 9:00 je zrušená', $customer['subject'] );
		$this->assertStringContainsString( 'Přezutí', $customer['text'] );
		$this->assertStringContainsString( home_url( '/rezervace/' ), $customer['html'] );
		$this->assertStringContainsString( 'Jan Kárník', $customer['text'] );
		$this->assertSame( 'Zrušená rezervace: pondělí 1. 3. 2027 v 9:00, Jan Novák', $provozovatel['subject'] );
		$this->assertStringContainsString( '+420 603 123 456', $provozovatel['text'] );
	}

	public function test_provozovatel_notification_about_cancellations_can_be_turned_off(): void {
		update_option( 'pneukarnik_notify_cancelled', '0' );
		$this->book( $this->tyres, self::MONDAY, '09:00' );
		$token       = $this->cancel_token_from( $this->mail_to( self::CUSTOMER ) );
		$this->mails = [];

		$this->cancel( $token );

		$this->assertSame( [ [ self::CUSTOMER ] ], array_column( $this->mails, 'to' ) );
	}

	public function test_without_provozovatel_email_the_site_admin_is_notified(): void {
		update_option( 'pneukarnik_email', '' );
		update_option( 'admin_email', 'admin@example.test' );

		$this->book( $this->tyres, self::MONDAY, '09:00' );

		$this->assertSame( 'Nová rezervace: pondělí 1. 3. 2027 v 9:00, Jan Novák', $this->mail_to( 'admin@example.test' )['subject'] );
	}
}
