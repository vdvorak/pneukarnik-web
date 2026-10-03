<?php
/**
 * Vypnutí online rezervací Provozovatelem: REST nové Rezervace odmítne se zprávou Provozovatele,
 * Zrušení odkazem z e‑mailu funguje dál.
 */

declare(strict_types=1);

class OnlineBookingDisabledTest extends Pneukarnik_REST_Test_Case {

	private const MONDAY = '2027-03-01';

	private int $tyres;

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
		$this->set_booking_rules( 60 );
		$this->tyres = $this->create_service( 60 );
		$this->capture_mails();
	}

	public function test_booking_is_refused_with_the_provozovatel_message(): void {
		Pneukarnik_Booking::save_online( false, 'Do 15. 3. máme dovolenou, objednávejte se telefonicky.' );

		$response = $this->book( $this->tyres, self::MONDAY, '09:00' );

		$this->assertSame( 503, $response->get_status() );
		$this->assertSame(
			[
				'code'    => 'booking.disabled',
				'message' => 'booking.disabled',
				'data'    => [
					'status'           => 503,
					'disabled_message' => 'Do 15. 3. máme dovolenou, objednávejte se telefonicky.',
				],
			],
			$response->get_data()
		);
		$this->assertContains( '09:00', $this->free_starts( $this->tyres, self::MONDAY ) );
		$this->assertSame( [], $this->mails );
	}

	public function test_without_a_message_the_default_one_is_used(): void {
		Pneukarnik_Booking::save_online( false, '  ' );

		$response = $this->book( $this->tyres, self::MONDAY, '09:00' );

		$this->assertSame( 'Online rezervace jsou momentálně nedostupné. Kontaktujte nás telefonicky.', $response->get_data()['data']['disabled_message'] );
	}

	public function test_logged_in_provozovatel_cannot_book_online_either(): void {
		Pneukarnik_Booking::save_online( false, 'Zavřeno.' );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$this->assertSame( 'booking.disabled', $this->book( $this->tyres, self::MONDAY, '09:00' )->get_data()['code'] );
	}

	public function test_booking_works_again_once_enabled(): void {
		Pneukarnik_Booking::save_online( false, 'Zavřeno.' );
		Pneukarnik_Booking::save_online( true, 'Zavřeno.' );

		$this->created_booking( $this->book( $this->tyres, self::MONDAY, '09:00' ) );
	}

	public function test_cancellation_by_link_works_while_disabled(): void {
		$this->created_booking( $this->book( $this->tyres, self::MONDAY, '09:00' ) );
		$token = $this->cancel_token_from( $this->mail_to( 'jan@example.test' ) );
		Pneukarnik_Booking::save_online( false, 'Zavřeno.' );

		$this->assertSame( 'cancellation.allowed', $this->cancellation( $token )->get_data()['code'] );
		$response = $this->cancel( $token );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'cancellation.cancelled', $response->get_data()['code'] );
	}
}
