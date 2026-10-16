<?php
/**
 * Leasingový zákazník: v Sezóně má pro sezónní Službu (přezutí) jen Termíny v leasingovém období
 * dané Sezóny (od a do včetně), ostatní Služby bez omezení. Leasingová společnost je povinná.
 */

declare(strict_types=1);

class LeasingTest extends Pneukarnik_REST_Test_Case {

	private int $tyres;
	private int $oil;

	public function set_up(): void {
		parent::set_up();
		Pneukarnik_Clock::freeze( '2027-03-01 07:00' );
		$this->set_working_hours_every_day(
			[
				[
					'from' => '08:00',
					'to'   => '10:00',
				],
			]
		);
		$this->set_booking_rules( 60 );
		$this->set_seasons( [ '03-10', '04-20', '04-05' ], [ '10-01', '11-15', '', '10-31' ] );
		$this->tyres = $this->create_service( 60, true );
		$this->oil   = $this->create_service( 60, false );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function outside_leasing_period(): array {
		return [
			'první den jarní'          => [ '2027-03-10' ],
			'den před leasingem jarní' => [ '2027-04-04' ],
			'den po leasingu podzimní' => [ '2027-11-01' ],
			'poslední den podzimní'    => [ '2027-11-15' ],
		];
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function leasing_allowed(): array {
		return [
			'leasing od jarní'    => [ '2027-04-05' ],
			'poslední den jarní'  => [ '2027-04-20' ],
			'první den podzimní'  => [ '2027-10-01' ],
			'leasing do podzimní' => [ '2027-10-31' ],
			'mimo Sezónu'         => [ '2027-03-09' ],
			'léto'                => [ '2027-07-14' ],
		];
	}

	/**
	 * @dataProvider outside_leasing_period
	 */
	public function test_leasing_customer_has_no_tyre_change_termins_outside_leasing_period( string $date ): void {
		$slots   = $this->slots( $this->tyres, $date, true );
		$booking = $this->book( $this->tyres, $date, '08:00', $this->leasing() );

		$this->assertSame( 422, $slots->get_status() );
		$this->assertSame( 'booking.leasing_date', $slots->get_data()['code'] );
		$this->assertSame( 422, $booking->get_status() );
		$this->assertSame( 'booking.leasing_date', $booking->get_data()['code'] );
	}

	/**
	 * @dataProvider outside_leasing_period
	 */
	public function test_leasing_customer_books_other_services_outside_leasing_period( string $date ): void {
		$this->assertSame( [ '08:00', '09:00' ], $this->free_starts( $this->oil, $date, true ) );
		$this->assertSame( 201, $this->book( $this->oil, $date, '08:00', $this->leasing() )->get_status() );
	}

	public function test_leasing_rule_applies_when_the_booking_includes_a_tyre_change(): void {
		$response = $this->book( [ $this->oil, $this->tyres ], '2027-04-04', '08:00', $this->leasing() );

		$this->assertSame( 'booking.leasing_date', $response->get_data()['code'] );
	}

	/**
	 * @dataProvider outside_leasing_period
	 */
	public function test_other_customers_have_termins_outside_leasing_period( string $date ): void {
		$this->assertSame( [ '08:00', '09:00' ], $this->free_starts( $this->tyres, $date ) );
	}

	/**
	 * @dataProvider leasing_allowed
	 */
	public function test_leasing_customer_books_from_leasing_date_and_outside_season( string $date ): void {
		$this->assertSame( [ '08:00', '09:00' ], $this->free_starts( $this->tyres, $date, true ) );

		$booking = $this->created_booking( $this->book( $this->tyres, $date, '08:00', $this->leasing() ) );

		$this->assertTrue( $booking['leasing'] );
		$this->assertSame( 'ČSOB Leasing', $booking['leasing_company'] );
	}

	public function test_refusal_names_the_leasing_period_of_the_season(): void {
		$season = $this->slots( $this->tyres, '2027-11-01', true )->get_data()['data']['season'];

		$this->assertNull( $season['leasing_from'] );
		$this->assertSame( '2027-10-31', $season['leasing_to'] );
	}

	public function test_available_days_for_leasing_start_at_leasing_date_and_say_why(): void {
		$data = $this->available_days_response( $this->tyres, '2027-04', true );

		$this->assertSame( '2027-04-05', $data['days'][0] );
		$this->assertCount( 30 - 4, $data['days'] );
		$this->assertSame(
			[
				[
					'code'   => 'booking.leasing_date',
					'season' => [
						'name'         => 'spring',
						'from'         => '2027-03-10',
						'to'           => '2027-04-20',
						'leasing_from' => '2027-04-05',
						'leasing_to'   => null,
					],
				],
			],
			$data['restrictions']
		);
	}

	public function test_available_days_for_leasing_end_at_leasing_date(): void {
		$data = $this->available_days_response( $this->tyres, '2027-11', true );

		$this->assertSame( '2027-11-16', $data['days'][0] );
		$this->assertSame( [ 'booking.leasing_date' ], array_column( $data['restrictions'], 'code' ) );
	}

	public function test_available_days_of_other_services_for_leasing_are_not_restricted(): void {
		$data = $this->available_days_response( $this->oil, '2027-04', true );

		$this->assertCount( 30, $data['days'] );
		$this->assertSame( [], $data['restrictions'] );
	}

	public function test_available_days_for_other_customers_are_not_restricted(): void {
		$data = $this->available_days_response( $this->tyres, '2027-04' );

		$this->assertCount( 30, $data['days'] );
		$this->assertSame( [], $data['restrictions'] );
	}

	public function test_season_without_leasing_period_does_not_restrict_leasing(): void {
		$this->set_seasons( [ '03-10', '04-20' ] );

		$this->assertSame( [ '08:00', '09:00' ], $this->free_starts( $this->tyres, '2027-03-10', true ) );
	}

	public function test_leasing_period_with_both_dates_allows_only_days_between(): void {
		$this->set_seasons( [ '03-10', '04-20', '04-01', '04-10' ] );

		$this->assertSame( [ '08:00', '09:00' ], $this->free_starts( $this->tyres, '2027-04-10', true ) );
		$this->assertSame( 422, $this->slots( $this->tyres, '2027-04-11', true )->get_status() );
		$this->assertSame( 422, $this->slots( $this->tyres, '2027-03-31', true )->get_status() );
	}

	public function test_leasing_company_is_required_for_leasing(): void {
		$response = $this->book( $this->tyres, '2027-07-14', '08:00', [ 'leasing' => true ] );

		$this->assertSame( 422, $response->get_status() );
		$this->assertSame( [ 'leasing_company' => 'required' ], $response->get_data()['data']['errors'] );
	}

	public function test_leasing_company_too_long_is_rejected(): void {
		$response = $this->book( $this->tyres, '2027-07-14', '08:00', $this->leasing( str_repeat( 'a', 121 ) ) );

		$this->assertSame( [ 'leasing_company' => 'too_long' ], $response->get_data()['data']['errors'] );
	}

	public function test_customer_without_leasing_keeps_no_leasing_company(): void {
		$booking = $this->created_booking( $this->book( $this->tyres, '2027-04-04', '08:00', [ 'leasing_company' => 'ČSOB Leasing' ] ) );

		$this->assertFalse( $booking['leasing'] );
		$this->assertNull( $booking['leasing_company'] );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function leasing( string $company = 'ČSOB Leasing' ): array {
		return [
			'leasing'         => true,
			'leasing_company' => $company,
		];
	}
}
