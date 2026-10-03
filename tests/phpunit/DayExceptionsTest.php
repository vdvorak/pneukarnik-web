<?php
/**
 * Efektivní Pracovní doba dne: Výjimka (jednorázová, rozsahová, opakovaná), státní svátky ČR,
 * jinak Pracovní doba dne v týdnu. Ověřuje se přes nabídku Termínů.
 */

declare(strict_types=1);

class DayExceptionsTest extends Pneukarnik_REST_Test_Case {

	private const ALL_DAY = [ '08:00', '09:00', '10:00', '11:00' ];

	private int $service;

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
		$this->set_booking_rules( 60, 0, 3650 );
		$this->service = $this->create_service( 60 );
	}

	public function test_day_without_exception_follows_working_hours_of_the_weekday(): void {
		Pneukarnik_Working_Hours::save(
			[
				'mon' => [
					[
						'from' => '08:00',
						'to'   => '10:00',
					],
				],
			]
		);

		$this->assertSame( [ '08:00', '09:00' ], $this->free_starts( $this->service, '2027-03-01' ) );
		$this->assertSame( [], $this->free_starts( $this->service, '2027-03-02' ) );
	}

	public function test_one_day_exception_closes_only_that_day(): void {
		$this->add_exception( '2027-03-03', '2027-03-03', false, null );

		$this->assertSame( [], $this->free_starts( $this->service, '2027-03-03' ) );
		$this->assertSame( self::ALL_DAY, $this->free_starts( $this->service, '2027-03-04' ) );
		$this->assertSame( self::ALL_DAY, $this->free_starts( $this->service, '2028-03-03' ) );
	}

	public function test_range_exception_closes_every_day_of_the_range(): void {
		$this->add_exception( '2027-07-12', '2027-07-23', false, null, 'Dovolená' );

		$this->assertSame( self::ALL_DAY, $this->free_starts( $this->service, '2027-07-11' ) );
		foreach ( [ '2027-07-12', '2027-07-17', '2027-07-23' ] as $date ) {
			$this->assertSame( [], $this->free_starts( $this->service, $date ), $date );
		}
		$this->assertSame( self::ALL_DAY, $this->free_starts( $this->service, '2027-07-24' ) );
	}

	public function test_shortened_day_offers_only_its_own_hours(): void {
		$this->add_exception(
			'2027-03-05',
			'2027-03-05',
			false,
			[
				[
					'from' => '08:00',
					'to'   => '10:00',
				],
			]
		);

		$this->assertSame( [ '08:00', '09:00' ], $this->free_starts( $this->service, '2027-03-05' ) );
	}

	public function test_yearly_exception_repeats_on_the_same_day_and_month(): void {
		$this->add_exception( '2027-08-16', '2027-08-17', true, null );

		foreach ( [ '2027-08-16', '2027-08-17', '2028-08-16', '2031-08-17' ] as $date ) {
			$this->assertSame( [], $this->free_starts( $this->service, $date ), $date );
		}
		$this->assertSame( self::ALL_DAY, $this->free_starts( $this->service, '2028-08-18' ) );
	}

	public function test_yearly_range_over_new_year_repeats_on_both_sides_of_it(): void {
		$this->add_exception( '2027-12-29', '2028-01-04', true, null );

		foreach ( [ '2027-12-29', '2027-12-31', '2028-01-03', '2028-01-04', '2028-12-30', '2029-01-02' ] as $date ) {
			$this->assertSame( [], $this->free_starts( $this->service, $date ), $date );
		}
		foreach ( [ '2027-12-28', '2028-01-05', '2028-06-15' ] as $date ) {
			$this->assertSame( self::ALL_DAY, $this->free_starts( $this->service, $date ), $date );
		}
	}

	public function test_one_off_exception_wins_over_yearly_one(): void {
		$this->add_exception( '2027-04-30', '2027-04-30', true, null );
		$this->add_exception(
			'2028-04-30',
			'2028-04-30',
			false,
			[
				[
					'from' => '10:00',
					'to'   => '12:00',
				],
			]
		);

		$this->assertSame( [], $this->free_starts( $this->service, '2027-04-30' ) );
		$this->assertSame( [ '10:00', '11:00' ], $this->free_starts( $this->service, '2028-04-30' ) );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function czech_holidays(): array {
		return [
			'Velký pátek 2027'          => [ '2027-03-26' ],
			'Velikonoční pondělí 2027'  => [ '2027-03-29' ],
			'Svátek práce 2027'         => [ '2027-05-01' ],
			'Den vítězství 2027'        => [ '2027-05-08' ],
			'Cyril a Metoděj 2027'      => [ '2027-07-05' ],
			'Jan Hus 2027'              => [ '2027-07-06' ],
			'Den české státnosti 2027'  => [ '2027-09-28' ],
			'Vznik Československa 2027' => [ '2027-10-28' ],
			'17. listopad 2027'         => [ '2027-11-17' ],
			'Štědrý den 2027'           => [ '2027-12-24' ],
			'1. svátek vánoční 2027'    => [ '2027-12-25' ],
			'2. svátek vánoční 2027'    => [ '2027-12-26' ],
			'Nový rok 2028'             => [ '2028-01-01' ],
			'Velký pátek 2028'          => [ '2028-04-14' ],
			'Velikonoční pondělí 2028'  => [ '2028-04-17' ],
			'Velký pátek 2029'          => [ '2029-03-30' ],
			'Velikonoční pondělí 2029'  => [ '2029-04-02' ],
			'Velikonoční pondělí 2030'  => [ '2030-04-22' ],
			'Velikonoční pondělí 2035'  => [ '2035-03-26' ],
		];
	}

	/**
	 * @dataProvider czech_holidays
	 */
	public function test_czech_public_holiday_is_closed( string $date ): void {
		$this->assertSame( [], $this->free_starts( $this->service, $date ) );
	}

	public function test_days_around_easter_are_not_holidays(): void {
		foreach ( [ '2027-03-25', '2027-03-30', '2028-04-13', '2028-04-18' ] as $date ) {
			$this->assertSame( self::ALL_DAY, $this->free_starts( $this->service, $date ), $date );
		}
	}

	public function test_holiday_can_be_switched_off_individually(): void {
		Pneukarnik_Holidays::save_disabled( [ 'christmas_eve', 'easter_monday' ] );

		$this->assertSame( self::ALL_DAY, $this->free_starts( $this->service, '2027-12-24' ) );
		$this->assertSame( self::ALL_DAY, $this->free_starts( $this->service, '2028-04-17' ) );
		$this->assertSame( [], $this->free_starts( $this->service, '2027-12-25' ) );
		$this->assertSame( [], $this->free_starts( $this->service, '2028-04-14' ) );
	}

	public function test_exception_wins_over_holiday(): void {
		$this->add_exception(
			'2027-12-24',
			'2027-12-24',
			false,
			[
				[
					'from' => '08:00',
					'to'   => '10:00',
				],
			]
		);

		$this->assertSame( [ '08:00', '09:00' ], $this->free_starts( $this->service, '2027-12-24' ) );
	}

	/**
	 * @return array<string, array{string, string, bool, mixed, string}>
	 */
	public static function invalid_exceptions(): array {
		$block = static fn( string $from, string $to ): array => [
			'from' => $from,
			'to'   => $to,
		];
		return [
			'nemožné datum'       => [ '2027-02-30', '2027-03-01', false, null, 'invalid_date' ],
			'konec před začátkem' => [ '2027-03-02', '2027-03-01', false, null, 'invalid_range' ],
			'opakovaná přes rok'  => [ '2027-03-01', '2028-03-01', true, null, 'invalid_range' ],
			'konec bloku dřív'    => [ '2027-03-01', '2027-03-01', false, [ $block( '10:00', '09:00' ) ], 'invalid_hours' ],
			'překryv bloků'       => [ '2027-03-01', '2027-03-01', false, [ $block( '08:00', '10:00' ), $block( '09:00', '11:00' ) ], 'invalid_hours' ],
			'tři bloky'           => [ '2027-03-01', '2027-03-01', false, [ $block( '08:00', '09:00' ), $block( '10:00', '11:00' ), $block( '12:00', '13:00' ) ], 'invalid_hours' ],
		];
	}

	/**
	 * @dataProvider invalid_exceptions
	 */
	public function test_invalid_exception_is_not_saved( string $from, string $to, bool $yearly, mixed $hours, string $code ): void {
		$this->assertSame( $code, Pneukarnik_Day_Exceptions::add( $from, $to, $yearly, $hours, null ) );
		$this->assertSame( self::ALL_DAY, $this->free_starts( $this->service, '2027-03-01' ) );
	}

	/**
	 * @param list<array{from:string,to:string}>|null $hours null = zavřeno
	 */
	private function add_exception( string $from, string $to, bool $yearly, ?array $hours, ?string $note = null ): void {
		$this->assertNull( Pneukarnik_Day_Exceptions::add( $from, $to, $yearly, $hours, $note ) );
	}
}
