<?php
/**
 * Volné Termíny pro Službu a den: mřížka, bloky Pracovní doby, překryv, předstih, horizont.
 */

declare(strict_types=1);

class SlotsTest extends Pneukarnik_REST_Test_Case {

	private const MONDAY = '2027-03-01';

	public function set_up(): void {
		parent::set_up();
		Pneukarnik_Clock::freeze( '2027-02-26 12:00' ); // Pátek před testovaným týdnem.
		Pneukarnik_Working_Hours::save(
			[
				'mon' => [
					[
						'from' => '08:00',
						'to'   => '12:00',
					],
					[
						'from' => '13:00',
						'to'   => '17:00',
					],
				],
				'tue' => [
					[
						'from' => '08:15',
						'to'   => '10:15',
					],
				],
				'sun' => null,
			]
		);
		$this->set_booking_rules( 30 );
	}

	public function test_starts_lie_on_grid_from_block_start_and_fit_the_block(): void {
		$service = $this->create_service( 60 );

		$this->assertSame( [ '08:15', '08:45', '09:15' ], $this->free_starts( $service, '2027-03-02' ) );
	}

	public function test_termin_never_overflows_lunch_break_or_end_of_day(): void {
		$service = $this->create_service( 90 );

		$this->assertSame(
			[ '08:00', '08:30', '09:00', '09:30', '10:00', '10:30', '13:00', '13:30', '14:00', '14:30', '15:00', '15:30' ],
			$this->free_starts( $service, self::MONDAY )
		);
	}

	public function test_day_without_working_hours_has_no_termin(): void {
		$service = $this->create_service( 60 );

		$this->assertSame( [], $this->free_starts( $service, '2027-03-07' ) );
	}

	public function test_confirmed_booking_of_any_service_blocks_overlapping_starts(): void {
		$long  = $this->create_service( 90 );
		$short = $this->create_service( 60 );
		$this->assertSame( 201, $this->book( $long, self::MONDAY, '08:30' )->get_status() ); // 08:30–10:00

		$this->assertSame(
			[ '10:00', '10:30', '11:00', '13:00', '13:30', '14:00', '14:30', '15:00', '15:30', '16:00' ],
			$this->free_starts( $short, self::MONDAY )
		);
	}

	public function test_cancelled_booking_frees_its_time(): void {
		$service = $this->create_service( 60 );
		$this->assertSame( 201, $this->book( $service, self::MONDAY, '08:00' )->get_status() );
		$this->cancel_all_bookings();

		$this->assertContains( '08:00', $this->free_starts( $service, self::MONDAY ) );
	}

	public function test_today_starts_only_after_now_plus_lead(): void {
		$this->set_booking_rules( 30, 60 );
		Pneukarnik_Clock::freeze( self::MONDAY . ' 08:10' );
		$service = $this->create_service( 60 );

		$this->assertSame(
			[ '09:30', '10:00', '10:30', '11:00', '13:00', '13:30', '14:00', '14:30', '15:00', '15:30', '16:00' ],
			$this->free_starts( $service, self::MONDAY )
		);
	}

	public function test_termin_that_already_started_this_minute_is_not_offered(): void {
		$this->set_booking_rules( 30, 0 );
		Pneukarnik_Clock::freeze( self::MONDAY . ' 08:00:30' );
		$service = $this->create_service( 60 );

		$this->assertSame( '08:30', $this->free_starts( $service, self::MONDAY )[0] );
	}

	public function test_past_day_has_no_termin(): void {
		Pneukarnik_Clock::freeze( '2027-03-02 07:00' );
		$service = $this->create_service( 60 );

		$this->assertSame( [], $this->free_starts( $service, self::MONDAY ) );
	}

	public function test_day_beyond_horizon_has_no_termin(): void {
		$this->set_booking_rules( 30, 0, 3 ); // Pátek + 3 dny = pondělí je poslední den.
		$service = $this->create_service( 60 );

		$this->assertNotSame( [], $this->free_starts( $service, self::MONDAY ) );
		$this->assertSame( [], $this->free_starts( $service, '2027-03-02' ) );
	}

	public function test_impossible_date_is_rejected(): void {
		$service = $this->create_service( 60 );

		$response = $this->rest(
			'GET',
			'/slots',
			[
				'service_id' => $service,
				'date'       => '2027-13-01',
			]
		);

		$this->assertSame( 400, $response->get_status() );
	}

	public function test_service_not_bookable_online_has_no_termin(): void {
		$service = $this->create_service( 60 );
		update_post_meta( $service, '_service_bookable', '' );

		$response = $this->rest(
			'GET',
			'/slots',
			[
				'service_id' => $service,
				'date'       => self::MONDAY,
			]
		);

		$this->assertSame( 422, $response->get_status() );
		$this->assertSame( 'booking.service_not_bookable', $response->get_data()['code'] );
	}

	private function cancel_all_bookings(): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "UPDATE %i SET status = 'CANCELLED'", Pneukarnik_DB::bookings_table() ) );
	}
}
