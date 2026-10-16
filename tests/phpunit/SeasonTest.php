<?php
/**
 * Sezóny: Termín patří do jarní nebo podzimní Sezóny podle svého data (hraniční dny včetně).
 * Online rezervaci Služeb Sezóna neomezuje, leasingová období viz LeasingTest.
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
		$this->set_seasons( [ '03-10', '04-20', '04-05' ], [ '10-01', '11-15', '', '10-31' ] );
		$this->tyres = $this->create_service( 60, true, 'Přezutí' );
		$this->oil   = $this->create_service( 60, false, 'Výměna oleje' );
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function days_in_season(): array {
		return [
			'první den jarní'       => [ '2027-03-10', 'spring' ],
			'poslední den jarní'    => [ '2027-04-20', 'spring' ],
			'první den podzimní'    => [ '2027-10-01', 'autumn' ],
			'uprostřed podzimní'    => [ '2027-10-25', 'autumn' ],
			'poslední den podzimní' => [ '2027-11-15', 'autumn' ],
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
	public function test_termin_belongs_to_the_season_of_its_date( string $date, string $name ): void {
		$this->assertSame( $name, Pneukarnik_Season::for_date( $date )['name'] ?? null );
	}

	/**
	 * @dataProvider days_outside_season
	 */
	public function test_termin_outside_seasons_has_no_season( string $date ): void {
		$this->assertNull( Pneukarnik_Season::for_date( $date ) );
	}

	public function test_season_carries_its_dates_in_the_year_of_the_termin(): void {
		$this->assertSame(
			[
				'name'         => 'autumn',
				'from'         => '2028-10-01',
				'to'           => '2028-11-15',
				'leasing_from' => null,
				'leasing_to'   => '2028-10-31',
			],
			Pneukarnik_Season::for_date( '2028-10-25' )
		);
	}

	/**
	 * @dataProvider days_in_season
	 */
	public function test_any_service_has_termins_in_season( string $date ): void {
		$this->assertSame( [ '08:00', '09:00' ], $this->free_starts( $this->tyres, $date ) );
		$this->assertSame( [ '08:00', '09:00' ], $this->free_starts( $this->oil, $date ) );
	}

	public function test_booking_with_a_non_seasonal_service_in_season_is_created(): void {
		$this->assertSame( 201, $this->book( [ $this->tyres, $this->oil ], '2027-04-20', '08:00' )->get_status() );
	}

	public function test_available_days_in_season_have_no_restrictions(): void {
		$data = $this->available_days_response( [ $this->tyres, $this->oil ], '2027-04' );

		$this->assertCount( 30, $data['days'] ); // Velikonoce 2027 jsou v březnu.
		$this->assertSame( [], $data['restrictions'] );
	}

	public function test_seasons_are_saved_with_leasing_period(): void {
		$this->assertSame(
			[
				'spring' => [
					'from'         => '03-10',
					'to'           => '04-20',
					'leasing_from' => '04-05',
					'leasing_to'   => '',
				],
				'autumn' => [
					'from'         => '10-01',
					'to'           => '11-15',
					'leasing_from' => '',
					'leasing_to'   => '10-31',
				],
			],
			Pneukarnik_Season::get_all()
		);
	}

	/**
	 * @return array<string, array{array{0:string,1:string,2?:string,3?:string}|null, array{0:string,1:string,2?:string,3?:string}|null}>
	 */
	public static function invalid_seasons(): array {
		return [
			'od po do'                  => [ [ '04-20', '03-10' ], null ],
			'jen od'                    => [ [ '03-10', '' ], null ],
			'neplatné datum'            => [ [ '02-30', '04-20' ], null ],
			'29. 2.'                    => [ [ '02-29', '04-20' ], null ],
			'špatný formát'             => [ [ '3-10', '04-20' ], null ],
			'leasing od před Sezónou'   => [ [ '03-10', '04-20', '03-01' ], null ],
			'leasing od po Sezóně'      => [ [ '03-10', '04-20', '04-21' ], null ],
			'leasing od bez Sezóny'     => [ [ '', '', '04-01' ], null ],
			'leasing do před Sezónou'   => [ [ '03-10', '04-20', '', '03-01' ], null ],
			'leasing do po Sezóně'      => [ [ '03-10', '04-20', '', '04-21' ], null ],
			'leasing do bez Sezóny'     => [ [ '', '', '', '04-01' ], null ],
			'leasing do neplatné datum' => [ [ '03-10', '04-20', '', '04-31' ], null ],
			'leasing od po leasing do'  => [ [ '03-10', '04-20', '04-10', '04-05' ], null ],
			'Sezóny se překrývají'      => [ [ '03-10', '10-05' ], [ '10-01', '11-15' ] ],
		];
	}

	/**
	 * @dataProvider invalid_seasons
	 * @param array{0:string,1:string,2?:string,3?:string}|null $spring
	 * @param array{0:string,1:string,2?:string,3?:string}|null $autumn
	 */
	public function test_invalid_seasons_are_not_saved( ?array $spring, ?array $autumn ): void {
		$saved = Pneukarnik_Season::get_all();

		$this->assertFalse( Pneukarnik_Season::save( self::seasons( $spring, $autumn ) ) );
		$this->assertSame( $saved, Pneukarnik_Season::get_all() ); // Platí původní Sezóny.
	}
}
