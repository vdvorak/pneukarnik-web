<?php
/**
 * „Teď“ pluginu: nastavitelné z testů a vždy v časové zóně Europe/Prague,
 * bez ohledu na nastavení časové zóny WordPressu.
 */

declare(strict_types=1);

class ClockTest extends Pneukarnik_REST_Test_Case {

	private int $service_id;

	public function set_up(): void {
		parent::set_up();
		$this->service_id = $this->create_service( 60 );
		$this->set_booking_rules( 60 );
		$this->set_working_hours_every_day(
			[
				[
					'from' => '08:00',
					'to'   => '12:00',
				],
			]
		);
	}

	public function test_future_day_offers_whole_working_hours(): void {
		Pneukarnik_Clock::freeze( '2027-03-01 06:00' );

		$this->assertSame( [ '08:00', '09:00', '10:00', '11:00' ], $this->free_starts( $this->service_id, '2027-03-02' ) );
	}

	public function test_today_offers_only_starts_after_now(): void {
		Pneukarnik_Clock::freeze( '2027-03-01 08:30' );

		$this->assertSame( [ '09:00', '10:00', '11:00' ], $this->free_starts( $this->service_id, '2027-03-01' ) );
	}

	public function test_now_is_prague_time_in_winter_even_if_wordpress_timezone_differs(): void {
		update_option( 'timezone_string', 'UTC' );
		Pneukarnik_Clock::freeze( new DateTimeImmutable( '2027-01-15T07:30:00Z' ) ); // 08:30 SEČ

		$this->assertSame( [ '09:00', '10:00', '11:00' ], $this->free_starts( $this->service_id, '2027-01-15' ) );
	}

	public function test_now_is_prague_time_in_summer(): void {
		update_option( 'timezone_string', 'UTC' );
		Pneukarnik_Clock::freeze( new DateTimeImmutable( '2027-07-15T06:30:00Z' ) ); // 08:30 SELČ

		$this->assertSame( [ '09:00', '10:00', '11:00' ], $this->free_starts( $this->service_id, '2027-07-15' ) );
	}
}
