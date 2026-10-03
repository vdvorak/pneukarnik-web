<?php
/**
 * „Kola mám uskladněná u vás“: jen u Služeb, kde se na to Provozovatel chce ptát.
 */

declare(strict_types=1);

class StoredWheelsTest extends Pneukarnik_REST_Test_Case {

	private const MONDAY = '2027-03-01';

	private int $tyres;
	private int $geometry;

	public function set_up(): void {
		parent::set_up();
		Pneukarnik_Clock::freeze( '2027-02-26 12:00' );
		$this->set_working_hours_every_day(
			[
				[
					'from' => '08:00',
					'to'   => '12:00',
				],
			]
		);
		$this->set_booking_rules( 30 );
		$this->tyres    = $this->create_service( 60, false, 'Přezutí' );
		$this->geometry = $this->create_service( 60, false, 'Geometrie' );
		update_post_meta( $this->tyres, '_service_ask_stored_wheels', '1' );
	}

	public function test_stored_wheels_are_kept_with_the_booking(): void {
		$booking = $this->created_booking( $this->book( [ $this->geometry, $this->tyres ], self::MONDAY, '08:00', [ 'stored_wheels' => true ] ) );

		$this->assertTrue( $booking['stored_wheels'] );
	}

	public function test_unchecked_stored_wheels_are_kept_as_no(): void {
		$booking = $this->created_booking( $this->book( $this->tyres, self::MONDAY, '08:00' ) );

		$this->assertFalse( $booking['stored_wheels'] );
	}

	public function test_stored_wheels_are_ignored_when_no_service_asks_for_them(): void {
		$booking = $this->created_booking( $this->book( $this->geometry, self::MONDAY, '08:00', [ 'stored_wheels' => true ] ) );

		$this->assertFalse( $booking['stored_wheels'] );
	}

	public function test_form_asks_only_for_services_that_want_to_know(): void {
		$asks = array_column( Pneukarnik_Booking_Pages::form_config()['services'], 'ask_stored_wheels', 'id' );

		$this->assertSame(
			[
				$this->geometry => false,
				$this->tyres    => true,
			],
			$asks
		);
	}
}
