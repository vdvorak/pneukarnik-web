<?php
/**
 * Dny měsíce s alespoň jedním volným Termínem pro sadu Služeb (kalendář rezervačního formuláře).
 */

declare(strict_types=1);

class AvailableDaysTest extends Pneukarnik_REST_Test_Case {

	private int $hour;
	private int $half_hour;

	public function set_up(): void {
		parent::set_up();
		Pneukarnik_Clock::freeze( '2027-03-10 12:00' ); // Středa.
		Pneukarnik_Working_Hours::save(
			[
				'mon' => [
					[
						'from' => '08:00',
						'to'   => '10:00',
					],
				],
				'tue' => [
					[
						'from' => '08:00',
						'to'   => '10:00',
					],
				],
				'wed' => [
					[
						'from' => '08:00',
						'to'   => '10:00',
					],
				],
				'thu' => [
					[
						'from' => '08:00',
						'to'   => '10:00',
					],
				],
				'fri' => [
					[
						'from' => '08:00',
						'to'   => '10:00',
					],
				],
			]
		);
		$this->set_booking_rules( 30, 60, 30 ); // Horizont: do 9. 4. 2027 včetně.
		$this->hour      = $this->create_service( 60 );
		$this->half_hour = $this->create_service( 30 );
	}

	public function test_lists_workdays_from_tomorrow_without_weekends_and_holidays(): void {
		$this->assertSame(
			[
				'2027-03-11',
				'2027-03-12',
				'2027-03-15',
				'2027-03-16',
				'2027-03-17',
				'2027-03-18',
				'2027-03-19',
				'2027-03-22',
				'2027-03-23',
				'2027-03-24',
				'2027-03-25',
				// 26. 3. Velký pátek, 29. 3. Velikonoční pondělí.
				'2027-03-30',
				'2027-03-31',
			],
			$this->available_days( $this->hour, '2027-03' )
		);
	}

	public function test_month_ends_at_the_horizon(): void {
		$this->assertSame(
			[ '2027-04-01', '2027-04-02', '2027-04-05', '2027-04-06', '2027-04-07', '2027-04-08', '2027-04-09' ],
			$this->available_days( $this->hour, '2027-04' )
		);
		$this->assertSame( [], $this->available_days( $this->hour, '2027-05' ) );
		$this->assertSame( [], $this->available_days( $this->hour, '2027-02' ) );
	}

	public function test_closed_exception_and_fully_booked_day_are_left_out(): void {
		$this->assertNull( Pneukarnik_Day_Exceptions::add( '2027-03-15', '2027-03-16', false, null, null ) );
		$this->assertSame( 201, $this->book( [ $this->hour, $this->half_hour ], '2027-03-17', '08:00' )->get_status() ); // 08:00–09:30

		$days = $this->available_days( $this->hour, '2027-03' );

		$this->assertNotContains( '2027-03-15', $days );
		$this->assertNotContains( '2027-03-16', $days );
		$this->assertNotContains( '2027-03-17', $days );
		$this->assertContains( '2027-03-17', $this->available_days( $this->half_hour, '2027-03' ) );
	}

	public function test_day_needs_room_for_the_sum_of_durations(): void {
		$this->assertSame( 201, $this->book( $this->half_hour, '2027-03-18', '08:30' )->get_status() ); // Zbývá 08:00–08:30 a 09:00–10:00.

		$this->assertContains( '2027-03-18', $this->available_days( $this->hour, '2027-03' ) );
		$this->assertNotContains( '2027-03-18', $this->available_days( [ $this->hour, $this->half_hour ], '2027-03' ) );
	}

	public function test_today_counts_only_with_a_termin_after_the_lead_time(): void {
		Pneukarnik_Clock::freeze( '2027-03-10 07:30' );
		$this->assertContains( '2027-03-10', $this->available_days( $this->hour, '2027-03' ) );

		Pneukarnik_Clock::freeze( '2027-03-10 08:01' );
		$this->assertNotContains( '2027-03-10', $this->available_days( $this->hour, '2027-03' ) );
	}

	public function test_first_day_is_the_nearest_free_day_in_the_horizon_whatever_the_month(): void {
		$this->assertSame( '2027-03-11', $this->available_days_response( $this->hour, '2027-03' )['first_day'] );
		$this->assertSame( '2027-03-11', $this->available_days_response( $this->hour, '2027-04' )['first_day'] );
	}

	public function test_first_day_skips_a_full_month(): void {
		$this->assertNull( Pneukarnik_Day_Exceptions::add( '2027-03-11', '2027-03-31', false, null, null ) );

		$march = $this->available_days_response( $this->hour, '2027-03' );

		$this->assertSame( [], $march['days'] );
		$this->assertSame( '2027-04-01', $march['first_day'] );
	}

	public function test_first_day_follows_the_services_and_leasing(): void {
		$this->set_seasons( [ '03-01', '03-31', '03-22' ] );
		$seasonal = $this->create_service( 60, true );

		$this->assertSame( '2027-04-01', $this->available_days_response( $this->hour, '2027-03' )['first_day'], 'Mimo sezónní Služby až po Sezóně' );
		$this->assertSame( '2027-03-11', $this->available_days_response( $seasonal, '2027-03' )['first_day'] );
		$this->assertSame( '2027-03-22', $this->available_days_response( $seasonal, '2027-03', true )['first_day'], 'Leasing až od leasingového data' );
	}

	public function test_first_day_is_null_without_a_free_day_in_the_horizon(): void {
		$this->assertNull( $this->available_days_response( $this->create_service( 180 ), '2027-03' )['first_day'], 'Delší než blok Pracovní doby' );

		$this->assertNull( Pneukarnik_Day_Exceptions::add( '2027-03-10', '2027-04-09', false, null, null ) );
		$this->assertNull( $this->available_days_response( $this->hour, '2027-03' )['first_day'] );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function invalid_months(): array {
		return [
			'měsíc 13' => [ '2027-13' ],
			'den'      => [ '2027-03-01' ],
			'text'     => [ 'březen' ],
		];
	}

	/**
	 * @dataProvider invalid_months
	 */
	public function test_invalid_month_is_rejected( string $month ): void {
		$response = $this->rest(
			'GET',
			'/available-days',
			[
				'service_ids' => [ $this->hour ],
				'month'       => $month,
			]
		);

		$this->assertSame( 400, $response->get_status() );
	}

	public function test_service_not_bookable_online_is_refused(): void {
		update_post_meta( $this->half_hour, '_service_bookable', '' );

		$response = $this->rest(
			'GET',
			'/available-days',
			[
				'service_ids' => [ $this->hour, $this->half_hour ],
				'month'       => '2027-03',
			]
		);

		$this->assertSame( 422, $response->get_status() );
		$this->assertSame( 'booking.service_not_bookable', $response->get_data()['code'] );
	}
}
