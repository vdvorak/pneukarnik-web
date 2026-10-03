<?php
/**
 * Ochrana veřejné rezervace: z jedné IP jde za hodinu vytvořit jen nastavený počet Rezervací
 * a zkusit jen nastavený počet Zrušení. Přihlášený Provozovatel limit nemá.
 */

declare(strict_types=1);

class RateLimitTest extends Pneukarnik_REST_Test_Case {

	private const MONDAY = '2027-03-01';

	private int $tyres;

	public function set_up(): void {
		parent::set_up();
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
		$this->tyres = $this->create_service( 60 );
		$this->from_ip( '198.51.100.7' );
		$this->capture_mails();
	}

	public function tear_down(): void {
		unset( $_SERVER['REMOTE_ADDR'] );
		parent::tear_down();
	}

	public function test_bookings_over_the_limit_are_refused_with_429(): void {
		Pneukarnik_Rate_Limit::save_limit( Pneukarnik_Rate_Limit::CREATE, 2 );
		$this->created_booking( $this->book( $this->tyres, self::MONDAY, '08:00' ) );
		$this->created_booking( $this->book( $this->tyres, self::MONDAY, '09:00' ) );

		$response = $this->book( $this->tyres, self::MONDAY, '10:00' );

		$this->assertRefused( 429, 'booking.rate_limited', $response );
		$this->assertContains( '10:00', $this->free_starts( $this->tyres, self::MONDAY ) );
	}

	public function test_refused_requests_do_not_count_as_bookings(): void {
		Pneukarnik_Rate_Limit::save_limit( Pneukarnik_Rate_Limit::CREATE, 1 );
		$this->assertSame( 'booking.invalid_fields', $this->book( $this->tyres, self::MONDAY, '08:00', [ 'phone' => '' ] )->get_data()['code'] );
		$this->assertSame( 'booking.slot_unavailable', $this->book( $this->tyres, self::MONDAY, '08:30' )->get_data()['code'] );

		$this->created_booking( $this->book( $this->tyres, self::MONDAY, '08:00' ) );
	}

	public function test_limit_counts_each_ip_separately(): void {
		Pneukarnik_Rate_Limit::save_limit( Pneukarnik_Rate_Limit::CREATE, 1 );
		$this->created_booking( $this->book( $this->tyres, self::MONDAY, '08:00' ) );

		$this->from_ip( '203.0.113.9' );

		$this->created_booking( $this->book( $this->tyres, self::MONDAY, '09:00' ) );
	}

	public function test_ipv6_addresses_of_one_connection_share_the_limit(): void {
		Pneukarnik_Rate_Limit::save_limit( Pneukarnik_Rate_Limit::CREATE, 1 );
		$this->from_ip( '2001:db8:1:2::1' );
		$this->created_booking( $this->book( $this->tyres, self::MONDAY, '08:00' ) );

		$this->from_ip( '2001:db8:1:2:ffff::2' );
		$this->assertRefused( 429, 'booking.rate_limited', $this->book( $this->tyres, self::MONDAY, '09:00' ) );

		$this->from_ip( '2001:db8:1:3::1' );
		$this->created_booking( $this->book( $this->tyres, self::MONDAY, '09:00' ) );
	}

	public function test_limit_is_lifted_an_hour_after_the_first_booking(): void {
		Pneukarnik_Rate_Limit::save_limit( Pneukarnik_Rate_Limit::CREATE, 1 );
		$this->created_booking( $this->book( $this->tyres, self::MONDAY, '08:00' ) );

		Pneukarnik_Clock::freeze( '2027-02-20 12:59' );
		$this->assertRefused( 429, 'booking.rate_limited', $this->book( $this->tyres, self::MONDAY, '09:00' ) );

		Pneukarnik_Clock::freeze( '2027-02-20 13:00' );
		$this->created_booking( $this->book( $this->tyres, self::MONDAY, '09:00' ) );
	}

	public function test_logged_in_provozovatel_is_not_limited(): void {
		Pneukarnik_Rate_Limit::save_limit( Pneukarnik_Rate_Limit::CREATE, 1 );
		Pneukarnik_Rate_Limit::save_limit( Pneukarnik_Rate_Limit::CANCEL, 1 );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$this->created_booking( $this->book( $this->tyres, self::MONDAY, '08:00', [ 'email' => 'a@example.test' ] ) );
		$this->created_booking( $this->book( $this->tyres, self::MONDAY, '09:00', [ 'email' => 'b@example.test' ] ) );

		$this->assertSame( 404, $this->cancel( str_repeat( '0', 64 ) )->get_status() );
		$this->assertSame( 'cancellation.cancelled', $this->cancel( $this->cancel_token_from( $this->mail_to( 'a@example.test' ) ) )->get_data()['code'] );
	}

	public function test_cancellation_attempts_over_the_limit_are_refused_with_429(): void {
		Pneukarnik_Rate_Limit::save_limit( Pneukarnik_Rate_Limit::CANCEL, 2 );
		$token = $this->booked( '08:00' );
		$this->assertSame( 404, $this->cancel( str_repeat( 'a', 64 ) )->get_status() );
		$this->assertSame( 404, $this->cancel( str_repeat( 'b', 64 ) )->get_status() );

		$response = $this->cancel( $token );

		$this->assertRefused( 429, 'cancellation.rate_limited', $response );
		$this->assertSame( 'no-store', $response->get_headers()['Cache-Control'] );
		$this->assertNotContains( '08:00', $this->free_starts( $this->tyres, self::MONDAY ), 'Rezervace zůstala' );
	}

	public function test_cancellation_limit_is_lifted_after_an_hour(): void {
		Pneukarnik_Rate_Limit::save_limit( Pneukarnik_Rate_Limit::CANCEL, 1 );
		$token = $this->booked( '08:00' );
		$this->cancel( str_repeat( 'a', 64 ) );
		$this->assertRefused( 429, 'cancellation.rate_limited', $this->cancel( $token ) );

		Pneukarnik_Clock::freeze( '2027-02-20 13:00' );

		$this->assertSame( 'cancellation.cancelled', $this->cancel( $token )->get_data()['code'] );
	}

	public function test_cancellation_and_booking_have_separate_limits(): void {
		Pneukarnik_Rate_Limit::save_limit( Pneukarnik_Rate_Limit::CREATE, 1 );
		Pneukarnik_Rate_Limit::save_limit( Pneukarnik_Rate_Limit::CANCEL, 1 );
		$token = $this->booked( '08:00' );

		$this->assertSame( 'cancellation.cancelled', $this->cancel( $token )->get_data()['code'] );
	}

	public function test_limits_are_kept_within_bounds(): void {
		Pneukarnik_Rate_Limit::save_limit( Pneukarnik_Rate_Limit::CREATE, 0 );
		Pneukarnik_Rate_Limit::save_limit( Pneukarnik_Rate_Limit::CANCEL, 5000 );

		$this->assertSame( 1, Pneukarnik_Rate_Limit::limit( Pneukarnik_Rate_Limit::CREATE ) );
		$this->assertSame( 100, Pneukarnik_Rate_Limit::limit( Pneukarnik_Rate_Limit::CANCEL ) );
	}

	private function from_ip( string $ip ): void {
		$_SERVER['REMOTE_ADDR'] = $ip;
	}

	private function booked( string $time ): string {
		$this->created_booking( $this->book( $this->tyres, self::MONDAY, $time ) );
		return $this->cancel_token_from( $this->mail_to( 'jan@example.test' ) );
	}

	private function assertRefused( int $status, string $code, WP_REST_Response $response ): void {
		$this->assertSame( $status, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );
		$this->assertSame( $code, $response->get_data()['code'] );
		$this->assertSame( $status, $response->get_data()['data']['status'] );
	}
}
