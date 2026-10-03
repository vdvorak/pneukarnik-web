<?php
/**
 * Leasingový zákazník: v Sezóně jen Termíny od leasingového data dané Sezóny,
 * leasingová společnost je povinná.
 */

declare(strict_types=1);

class LeasingTest extends Pneukarnik_REST_Test_Case {

	private int $tyres;

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
		$this->set_seasons( [ '03-10', '04-20', '04-05' ], [ '10-01', '11-15', '10-20' ] );
		$this->tyres = $this->create_service( 60, true );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function before_leasing_date(): array {
		return [
			'první den jarní'             => [ '2027-03-10' ],
			'den před leasingem jarní'    => [ '2027-04-04' ],
			'první den podzimní'          => [ '2027-10-01' ],
			'den před leasingem podzimní' => [ '2027-10-19' ],
		];
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function leasing_allowed(): array {
		return [
			'leasingové datum jarní'    => [ '2027-04-05' ],
			'poslední den jarní'        => [ '2027-04-20' ],
			'leasingové datum podzimní' => [ '2027-10-20' ],
			'mimo Sezónu'               => [ '2027-03-09' ],
			'léto'                      => [ '2027-07-14' ],
		];
	}

	/**
	 * @dataProvider before_leasing_date
	 */
	public function test_leasing_customer_has_no_termins_before_leasing_date( string $date ): void {
		$slots   = $this->slots( $this->tyres, $date, true );
		$booking = $this->book( $this->tyres, $date, '08:00', $this->leasing() );

		$this->assertSame( 422, $slots->get_status() );
		$this->assertSame( 'booking.leasing_date', $slots->get_data()['code'] );
		$this->assertSame( 422, $booking->get_status() );
		$this->assertSame( 'booking.leasing_date', $booking->get_data()['code'] );
	}

	/**
	 * @dataProvider before_leasing_date
	 */
	public function test_other_customers_have_termins_before_leasing_date( string $date ): void {
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

	public function test_refusal_names_the_leasing_date_of_the_season(): void {
		$response = $this->slots( $this->tyres, '2027-04-04', true );

		$this->assertSame( '2027-04-05', $response->get_data()['data']['season']['leasing_from'] );
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
					],
				],
			],
			$data['restrictions']
		);
	}

	public function test_available_days_for_other_customers_are_not_restricted(): void {
		$data = $this->available_days_response( $this->tyres, '2027-04' );

		$this->assertCount( 30, $data['days'] );
		$this->assertSame( [], $data['restrictions'] );
	}

	public function test_season_without_leasing_date_does_not_restrict_leasing(): void {
		$this->set_seasons( [ '03-10', '04-20' ] );

		$this->assertSame( [ '08:00', '09:00' ], $this->free_starts( $this->tyres, '2027-03-10', true ) );
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
