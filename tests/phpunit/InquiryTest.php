<?php
/**
 * Poptávka pneumatik (POST /inquiries): rozměr, letní/zimní, počet kusů a kontakt odejdou e‑mailem
 * Provozovateli s Reply-To na Zákazníka. Na webu se neukládá, Zákazník e‑mail nedostane.
 */

declare(strict_types=1);

class InquiryTest extends Pneukarnik_REST_Test_Case {

	private const CUSTOMER     = 'jan@example.test';
	private const PROVOZOVATEL = 'servis@example.test';

	public function set_up(): void {
		parent::set_up();
		Pneukarnik_Clock::freeze( '2027-02-20 12:00' );
		update_option( 'pneukarnik_email', self::PROVOZOVATEL );
		$this->create_inquiry_service();
		$_SERVER['REMOTE_ADDR'] = '198.51.100.7';
		$this->capture_mails();
	}

	public function tear_down(): void {
		unset( $_SERVER['REMOTE_ADDR'] );
		parent::tear_down();
	}

	public function test_inquiry_is_emailed_to_provozovatel_with_reply_to_the_customer(): void {
		$response = $this->inquire( [ 'note' => 'Nejlépe Barum, zátěžový index 91.' ] );

		$this->assertSame( 201, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );
		$this->assertNull( $response->get_data(), 'Odpověď neobsahuje osobní údaje' );
		$this->assertCount( 1, $this->mails, 'Zákazník e‑mail nedostane' );
		$mail = $this->mail_to( self::PROVOZOVATEL );
		$this->assertSame( 'Poptávka pneumatik: 205/55 R16, zimní, 4 ks, Jan Novák', $mail['subject'] );
		$this->assertSame( [ self::CUSTOMER ], $mail['reply_to'] );
		foreach ( [ 'Rozměr', '205/55 R16', 'Zimní', 'Počet kusů', 'Jan Novák', '+420 603 123 456', self::CUSTOMER, 'Nejlépe Barum, zátěžový index 91.', 'odpověď dojde Zákazníkovi' ] as $text ) {
			$this->assertStringContainsString( $text, $mail['text'] );
		}
		$this->assertStringContainsString( 'href="mailto:' . self::CUSTOMER . '?subject=', $mail['html'] );
		$this->assertStringContainsString( 'href="tel:+420603123456"', $mail['html'] );
	}

	public function test_customer_may_leave_only_a_phone(): void {
		$response = $this->inquire(
			[
				'email'  => '',
				'season' => 'summer',
				'pieces' => '2',
			]
		);

		$this->assertSame( 201, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );
		$mail = $this->mail_to( self::PROVOZOVATEL );
		$this->assertSame( 'Poptávka pneumatik: 205/55 R16, letní, 2 ks, Jan Novák', $mail['subject'] );
		$this->assertSame( [], $mail['reply_to'] );
		$this->assertStringContainsString( 'ozvěte se mu prosím telefonicky', $mail['text'] );
		$this->assertStringNotContainsString( 'mailto:', $mail['html'] );
	}

	public function test_customer_may_leave_only_an_email(): void {
		$response = $this->inquire( [ 'phone' => '' ] );

		$this->assertSame( 201, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );
		$this->assertStringNotContainsString( 'tel:', $this->mail_to( self::PROVOZOVATEL )['html'] );
	}

	public function test_missing_fields_are_refused_per_field(): void {
		$response = $this->rest( 'POST', '/inquiries', [] );

		$this->assertInvalid(
			[
				'size'   => 'required',
				'season' => 'required',
				'pieces' => 'required',
				'name'   => 'required',
				'phone'  => 'contact_required',
				'email'  => 'contact_required',
			],
			$response
		);
		$this->assertSame( [], $this->mails );
	}

	/**
	 * @dataProvider invalid_fields
	 * @param array<string, mixed>  $overrides
	 * @param array<string, string> $errors
	 */
	public function test_invalid_fields_are_refused( array $overrides, array $errors ): void {
		$this->assertInvalid( $errors, $this->inquire( $overrides ) );
		$this->assertSame( [], $this->mails );
	}

	/**
	 * @return array<string, array{0:array<string,mixed>,1:array<string,string>}>
	 */
	public static function invalid_fields(): array {
		return [
			'rozměr bez čísel'       => [ [ 'size' => 'nevím' ], [ 'size' => 'invalid' ] ],
			'dlouhý rozměr'          => [ [ 'size' => str_repeat( '205/55 R16 ', 10 ) ], [ 'size' => 'too_long' ] ],
			'celoroční'              => [ [ 'season' => 'all' ], [ 'season' => 'invalid' ] ],
			'nula kusů'              => [ [ 'pieces' => 0 ], [ 'pieces' => 'invalid' ] ],
			'moc kusů'               => [ [ 'pieces' => 21 ], [ 'pieces' => 'invalid' ] ],
			'kusy textem'            => [ [ 'pieces' => 'čtyři' ], [ 'pieces' => 'invalid' ] ],
			'desetinné kusy'         => [ [ 'pieces' => 2.5 ], [ 'pieces' => 'invalid' ] ],
			'jméno chybí'            => [ [ 'name' => '  ' ], [ 'name' => 'required' ] ],
			'neplatný telefon'       => [ [ 'phone' => '123' ], [ 'phone' => 'invalid' ] ],
			'neplatný e‑mail'        => [ [ 'email' => 'jan@' ], [ 'email' => 'invalid' ] ],
			'chybí telefon i e‑mail' => [
				[
					'phone' => '',
					'email' => '',
				],
				[
					'phone' => 'contact_required',
					'email' => 'contact_required',
				],
			],
			'pole jako seznam'       => [ [ 'name' => [ 'Jan' ] ], [ 'name' => 'invalid' ] ],
			'dlouhá poznámka'        => [ [ 'note' => str_repeat( 'a', 1001 ) ], [ 'note' => 'too_long' ] ],
		];
	}

	public function test_filled_honeypot_looks_sent_but_sends_nothing(): void {
		Pneukarnik_Rate_Limit::save_limit( Pneukarnik_Rate_Limit::INQUIRY, 1 );

		$response = $this->inquire( [ Pneukarnik_Inquiry::HONEYPOT => 'https://spam.example' ] );

		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( [], $this->mails );
		$this->assertSame( 201, $this->inquire()->get_status(), 'Robot limit nevyčerpá' );
	}

	public function test_inquiries_over_the_limit_are_refused_with_429(): void {
		Pneukarnik_Rate_Limit::save_limit( Pneukarnik_Rate_Limit::INQUIRY, 2 );
		$this->assertSame( 422, $this->inquire( [ 'size' => '' ] )->get_status(), 'Nevalidní se nepočítá' );
		foreach ( [ 1, 2 ] as $attempt ) {
			$this->assertSame( 201, $this->inquire()->get_status(), "Poptávka {$attempt}" );
		}

		$response = $this->inquire();

		$this->assertSame( 429, $response->get_status() );
		$this->assertSame( 'inquiry.rate_limited', $response->get_data()['code'] );
		$this->assertCount( 2, $this->mails );

		Pneukarnik_Clock::freeze( '2027-02-20 13:00' );
		$this->assertSame( 201, $this->inquire()->get_status(), 'Po hodině limit skončí' );
	}

	public function test_inquiry_is_refused_without_a_published_service_offering_it(): void {
		foreach ( get_posts( [ 'post_type' => Pneukarnik_Service::POST_TYPE ] ) as $post ) {
			wp_update_post(
				[
					'ID'          => $post->ID,
					'post_status' => 'draft',
				]
			);
		}
		$this->create_service( 60 );

		$response = $this->inquire();

		$this->assertSame( 503, $response->get_status() );
		$this->assertSame( 'inquiry.disabled', $response->get_data()['code'] );
		$this->assertSame( [], $this->mails );
	}

	public function test_inquiry_goes_to_the_wordpress_admin_without_a_contact_email(): void {
		update_option( 'pneukarnik_email', '' );
		update_option( 'admin_email', 'spravce@example.test' );

		$this->assertSame( 201, $this->inquire()->get_status() );

		$this->mail_to( 'spravce@example.test' );
	}

	public function test_service_offering_the_inquiry_links_to_its_form(): void {
		$service = Pneukarnik_Service::for_inquiry();

		$this->assertNotNull( $service );
		$this->assertSame( 'Prodej pneumatik', $service->title );
		$this->assertSame( $service->url() . '#poptavka', $service->inquiry_url() );
	}

	/**
	 * Platná Poptávka, jednotlivá pole jde přepsat.
	 *
	 * @param array<string, mixed> $overrides
	 */
	private function inquire( array $overrides = [] ): WP_REST_Response {
		return $this->rest(
			'POST',
			'/inquiries',
			$overrides + [
				'size'   => '205/55 R16',
				'season' => 'winter',
				'pieces' => 4,
				'name'   => 'Jan Novák',
				'phone'  => '+420 603 123 456',
				'email'  => self::CUSTOMER,
			]
		);
	}

	private function create_inquiry_service(): int {
		$id = $this->create_service( 30, false, 'Prodej pneumatik' );
		update_post_meta( $id, '_service_bookable', '' );
		update_post_meta( $id, '_service_inquiry', '1' );
		return $id;
	}

	/**
	 * @param array<string, string> $errors
	 */
	private function assertInvalid( array $errors, WP_REST_Response $response ): void {
		$this->assertSame( 422, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );
		$this->assertSame( 'inquiry.invalid_fields', $response->get_data()['code'] );
		$this->assertSame( $errors, $response->get_data()['data']['errors'] );
	}
}
