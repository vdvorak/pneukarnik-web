<?php
/**
 * Sezóny: Termín v jarní nebo podzimní Sezóně (podle data Termínu, hraniční dny včetně)
 * jde online rezervovat jen se sezónními Službami.
 */

declare(strict_types=1);

class SeasonTest extends Pneukarnik_REST_Test_Case {

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
		$this->set_seasons( [ '03-10', '04-20', '04-05' ], [ '10-01', '11-15', '10-20' ] );
		$this->tyres = $this->create_service( 60, true, 'Přezutí' );
		$this->oil   = $this->create_service( 60, false, 'Výměna oleje' );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function days_in_season(): array {
		return [
			'první den jarní'       => [ '2027-03-10' ],
			'poslední den jarní'    => [ '2027-04-20' ],
			'první den podzimní'    => [ '2027-10-01' ],
			'uprostřed podzimní'    => [ '2027-10-25' ],
			'poslední den podzimní' => [ '2027-11-15' ],
		];
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function days_outside_season(): array {
		return [
			'den před jarní'    => [ '2027-03-09' ],
			'den po jarní'      => [ '2027-04-21' ],
			'léto'              => [ '2027-07-14' ],
			'den před podzimní' => [ '2027-09-30' ],
			'den po podzimní'   => [ '2027-11-16' ],
		];
	}

	/**
	 * @dataProvider days_in_season
	 */
	public function test_non_seasonal_service_has_no_termins_in_season( string $date ): void {
		$response = $this->slots( $this->oil, $date );

		$this->assertSame( 422, $response->get_status() );
		$this->assertSame( 'booking.seasonal_only', $response->get_data()['code'] );
	}

	/**
	 * @dataProvider days_in_season
	 */
	public function test_seasonal_service_has_termins_in_season( string $date ): void {
		$this->assertSame( [ '08:00', '09:00' ], $this->free_starts( $this->tyres, $date ) );
	}

	/**
	 * @dataProvider days_outside_season
	 */
	public function test_non_seasonal_service_has_termins_outside_season( string $date ): void {
		$this->assertSame( [ '08:00', '09:00' ], $this->free_starts( $this->oil, $date ) );
	}

	public function test_refusal_names_the_season_of_the_termin(): void {
		$response = $this->slots( $this->oil, '2027-03-10' );

		$this->assertSame(
			[
				'name'         => 'spring',
				'from'         => '2027-03-10',
				'to'           => '2027-04-20',
				'leasing_from' => '2027-04-05',
			],
			$response->get_data()['data']['season']
		);
	}

	public function test_season_follows_the_date_of_the_termin_not_today(): void {
		Pneukarnik_Clock::freeze( '2027-03-15 07:00' ); // Dnes je Sezóna.

		$this->assertSame( [ '08:00', '09:00' ], $this->free_starts( $this->oil, '2027-04-21' ) );
		$this->assertSame( 201, $this->book( $this->oil, '2027-04-21', '08:00' )->get_status() );
	}

	public function test_booking_with_a_non_seasonal_service_in_season_is_refused(): void {
		$response = $this->book( [ $this->tyres, $this->oil ], '2027-04-20', '08:00' );

		$this->assertSame( 422, $response->get_status() );
		$this->assertSame( 'booking.seasonal_only', $response->get_data()['code'] );
		$this->assertSame( '2027-03-10', $response->get_data()['data']['season']['from'] );
	}

	public function test_booking_with_seasonal_services_in_season_is_created(): void {
		$this->assertSame( 201, $this->book( $this->tyres, '2027-04-20', '08:00' )->get_status() );
	}

	public function test_booking_with_a_non_seasonal_service_outside_season_is_created(): void {
		$this->assertSame( 201, $this->book( [ $this->tyres, $this->oil ], '2027-04-21', '08:00' )->get_status() );
	}

	public function test_available_days_skip_season_for_non_seasonal_services_and_say_why(): void {
		$data = $this->available_days_response( [ $this->tyres, $this->oil ], '2027-03' );

		$this->assertSame( [ '2027-03-01', '2027-03-02', '2027-03-03', '2027-03-04', '2027-03-05', '2027-03-06', '2027-03-07', '2027-03-08', '2027-03-09' ], $data['days'] );
		$this->assertSame(
			[
				[
					'code'   => 'booking.seasonal_only',
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

	public function test_available_days_of_seasonal_service_include_season_without_restrictions(): void {
		$data = $this->available_days_response( $this->tyres, '2027-04' );

		$this->assertCount( 30, $data['days'] ); // Velikonoce 2027 jsou v březnu.
		$this->assertSame( [], $data['restrictions'] );
	}

	public function test_without_seasons_any_service_can_be_booked_all_year(): void {
		$this->set_seasons( null, null );

		$this->assertSame( [ '08:00', '09:00' ], $this->free_starts( $this->oil, '2027-04-01' ) );
		$this->assertSame( [], $this->available_days_response( $this->oil, '2027-04' )['restrictions'] );
	}

	public function test_provozovatel_can_enter_a_non_seasonal_service_in_season(): void {
		$result = Pneukarnik_Booking::create(
			[
				'service_ids' => [ $this->oil ],
				'date'        => '2027-03-10',
				'time'        => '08:00',
				'name'        => 'Telefonická objednávka',
				'phone'       => '603123456',
				'email'       => 'telefon@example.test',
				'plate'       => '1AB2345',
			],
			Pneukarnik_Booking::SOURCE_ADMIN
		);

		$this->assertTrue( $result['ok'], (string) wp_json_encode( $result ) );
	}

	/**
	 * @return array<string, array{array{0:string,1:string,2?:string}|null, array{0:string,1:string,2?:string}|null}>
	 */
	public static function invalid_seasons(): array {
		return [
			'od po do'             => [ [ '04-20', '03-10' ], null ],
			'jen od'               => [ [ '03-10', '' ], null ],
			'neplatné datum'       => [ [ '02-30', '04-20' ], null ],
			'29. 2.'               => [ [ '02-29', '04-20' ], null ],
			'špatný formát'        => [ [ '3-10', '04-20' ], null ],
			'leasing před Sezónou' => [ [ '03-10', '04-20', '03-01' ], null ],
			'leasing po Sezóně'    => [ [ '03-10', '04-20', '04-21' ], null ],
			'leasing bez Sezóny'   => [ [ '', '', '04-01' ], null ],
			'Sezóny se překrývají' => [ [ '03-10', '10-05' ], [ '10-01', '11-15' ] ],
		];
	}

	/**
	 * @dataProvider invalid_seasons
	 * @param array{0:string,1:string,2?:string}|null $spring
	 * @param array{0:string,1:string,2?:string}|null $autumn
	 */
	public function test_invalid_seasons_are_not_saved( ?array $spring, ?array $autumn ): void {
		$this->assertFalse( Pneukarnik_Season::save( self::seasons( $spring, $autumn ) ) );
		$this->assertSame( 422, $this->slots( $this->oil, '2027-03-10' )->get_status() ); // Platí původní Sezóny.
	}
}
