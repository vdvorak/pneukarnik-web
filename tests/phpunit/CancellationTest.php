<?php
/**
 * Zrušení odkazem z e‑mailu: detail Rezervace podle tokenu, Zrušení nejpozději ve Lhůtě zrušení
 * před Termínem, stabilní kódy výsledku a okamžité uvolnění času.
 */

declare(strict_types=1);

class CancellationTest extends Pneukarnik_REST_Test_Case {

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
		update_option( 'pneukarnik_cancellation_hours', 24 );
		$this->tyres = $this->create_service( 60, false, 'Přezutí' );
		$this->capture_mails();
	}

	public function test_link_shows_the_booking_before_cancelling(): void {
		$token = $this->booked( self::MONDAY, '09:00' );

		$response = $this->cancellation( $token );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			[
				'code'         => 'cancellation.allowed',
				'booking'      => [
					'date'       => self::MONDAY,
					'time_start' => '09:00',
					'time_end'   => '10:00',
					'services'   => [ 'Přezutí' ],
					'plate'      => '1AB2345',
					'status'     => 'CONFIRMED',
				],
				'cancel_until' => '2027-02-28 09:00',
			],
			$response->get_data()
		);
	}

	public function test_customer_cancels_by_link_and_the_time_is_free_again(): void {
		$token = $this->booked( self::MONDAY, '09:00' );
		$this->assertNotContains( '09:00', $this->free_starts( $this->tyres, self::MONDAY ) );

		$response = $this->cancel( $token );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'cancellation.cancelled', $response->get_data()['code'] );
		$this->assertSame( 'CANCELLED', $response->get_data()['booking']['status'] );
		$this->assertContains( '09:00', $this->free_starts( $this->tyres, self::MONDAY ) );
		$this->assertSame( 201, $this->book( $this->tyres, self::MONDAY, '09:00', [ 'email' => 'jina@example.test' ] )->get_status() );
	}

	public function test_link_can_cancel_only_once(): void {
		$token = $this->booked( self::MONDAY, '09:00' );
		$this->cancel( $token );

		$again  = $this->cancel( $token );
		$detail = $this->cancellation( $token );

		$this->assertSame( 409, $again->get_status() );
		$this->assertSame( 'cancellation.already_cancelled', $again->get_data()['code'] );
		$this->assertSame( 200, $detail->get_status() );
		$this->assertSame( 'cancellation.already_cancelled', $detail->get_data()['code'] );
		$this->assertSame( 'CANCELLED', $detail->get_data()['booking']['status'] );
	}

	/**
	 * @return array<string, array{mixed}>
	 */
	public static function invalid_tokens(): array {
		return [
			'neznámý'       => [ str_repeat( 'ab', 32 ) ],
			'krátký'        => [ 'abc' ],
			'velká písmena' => [ str_repeat( 'AB', 32 ) ],
			'prázdný'       => [ '' ],
			'číslo'         => [ 123 ],
		];
	}

	/**
	 * @dataProvider invalid_tokens
	 */
	public function test_invalid_link_is_refused( mixed $token ): void {
		$this->booked( self::MONDAY, '09:00' );

		foreach ( [ 'GET', 'POST' ] as $method ) {
			$response = $this->rest( $method, '/cancellation', [ 'token' => $token ] );
			$this->assertSame( 404, $response->get_status(), $method );
			$this->assertSame( 'cancellation.invalid_token', $response->get_data()['code'], $method );
		}
		$this->assertNotContains( '09:00', $this->free_starts( $this->tyres, self::MONDAY ) );
	}

	public function test_link_cancels_only_its_own_booking(): void {
		$first  = $this->booked( self::MONDAY, '08:00' );
		$second = $this->booked( self::MONDAY, '10:00', 'druhy@example.test' );

		$this->cancel( $second );

		$this->assertSame( 'cancellation.allowed', $this->cancellation( $first )->get_data()['code'] );
		$this->assertSame( [ '10:00', '11:00' ], array_slice( $this->free_starts( $this->tyres, self::MONDAY ), 1 ) );
	}

	/**
	 * Termín, Lhůta zrušení v hodinách, poslední okamžik pro Zrušení a minuta po něm.
	 * Starý web počítal Lhůtu ve dnech a přes přelom měsíce chyboval.
	 *
	 * @return array<string, array{string, string, int, string, string}>
	 */
	public static function deadlines(): array {
		return [
			'přes přelom měsíce'           => [ '2027-03-01', '08:00', 24, '2027-02-28 08:00', '2027-02-28 08:01' ],
			'přes přelom roku'             => [ '2028-01-03', '08:00', 48, '2028-01-01 08:00', '2028-01-01 08:01' ],
			'přes přelom měsíce, 31 dní'   => [ '2027-04-01', '09:00', 24, '2027-03-31 09:00', '2027-03-31 09:01' ],
			'Lhůta delší než měsíc'        => [ '2027-03-01', '08:00', 24 * 40, '2027-01-20 08:00', '2027-01-20 08:01' ],
			'bez Lhůty do začátku Termínu' => [ '2027-03-01', '08:00', 0, '2027-03-01 08:00', '2027-03-01 08:01' ],
		];
	}

	/**
	 * @dataProvider deadlines
	 */
	public function test_cancellation_deadline_counts_hours_before_termin( string $date, string $time, int $hours, string $last_moment, string $too_late ): void {
		Pneukarnik_Clock::freeze( '2027-01-10 12:00' );
		update_option( 'pneukarnik_cancellation_hours', $hours );
		$token = $this->booked( $date, $time );

		Pneukarnik_Clock::freeze( $last_moment );
		$this->assertSame( 'cancellation.allowed', $this->cancellation( $token )->get_data()['code'] );
		$this->assertSame( $last_moment, $this->cancellation( $token )->get_data()['cancel_until'] );

		Pneukarnik_Clock::freeze( $too_late );
		$response = $this->cancel( $token );
		$this->assertSame( 0 === $hours ? 404 : 422, $response->get_status() );
		$this->assertSame( 0 === $hours ? 'cancellation.invalid_token' : 'cancellation.too_late', $response->get_data()['code'] );

		Pneukarnik_Clock::freeze( $last_moment );
		$this->assertSame( 200, $this->cancel( $token )->get_status() );
	}

	public function test_too_late_detail_still_shows_the_booking(): void {
		$token = $this->booked( self::MONDAY, '09:00' );
		Pneukarnik_Clock::freeze( '2027-02-28 12:00' );

		$response = $this->cancellation( $token );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'cancellation.too_late', $response->get_data()['code'] );
		$this->assertSame( self::MONDAY, $response->get_data()['booking']['date'] );
	}

	public function test_link_is_valid_until_the_termin(): void {
		$token = $this->booked( self::MONDAY, '09:00' );
		Pneukarnik_Clock::freeze( '2027-03-01 09:00' );
		$this->assertSame( 'cancellation.too_late', $this->cancellation( $token )->get_data()['code'] );

		Pneukarnik_Clock::freeze( '2027-03-01 09:01' );
		$response = $this->cancellation( $token );

		$this->assertSame( 404, $response->get_status() );
		$this->assertSame( 'cancellation.invalid_token', $response->get_data()['code'] );
	}

	public function test_used_link_is_valid_until_the_termin_too(): void {
		$token = $this->booked( self::MONDAY, '09:00' );
		$this->cancel( $token );

		Pneukarnik_Clock::freeze( '2027-03-01 09:01' );
		$response = $this->cancellation( $token );

		$this->assertSame( 404, $response->get_status() );
		$this->assertSame( 'cancellation.invalid_token', $response->get_data()['code'] );
	}

	public function test_old_cancel_endpoint_is_gone(): void {
		$this->assertSame( 404, $this->rest( 'POST', '/cancel', [ 'token' => 'x' ] )->get_status() );
	}

	/**
	 * Vytvoří Rezervaci a vrátí token z odkazu pro Zrušení v potvrzovacím e‑mailu.
	 */
	private function booked( string $date, string $time, string $email = 'jan@example.test' ): string {
		$this->created_booking( $this->book( $this->tyres, $date, $time, [ 'email' => $email ] ) );
		return $this->cancel_token_from( $this->mail_to( $email ) );
	}
}
