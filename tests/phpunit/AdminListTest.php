<?php
/**
 * Přehled Rezervací pro Provozovatele: seznam s filtrem podle data a stavu, hledání podle
 * jména, SPZ, telefonu a e‑mailu, a kalendář dnů s Pracovní dobou a Rezervacemi.
 */

declare(strict_types=1);

class AdminListTest extends Pneukarnik_REST_Test_Case {

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
		Pneukarnik_Working_Hours::save( array_merge( Pneukarnik_Working_Hours::get_all(), [ 'sun' => null ] ) );
		$this->set_booking_rules( 60 );
		$this->tyres = $this->create_service( 60, false, 'Přezutí' );
		$this->log_in_as( 'pneukarnik_viewer' );
	}

	public function test_list_is_ordered_by_termin_and_filtered_by_date(): void {
		$this->booked( '2027-03-02', '09:00', 'Druhý' );
		$this->booked( '2027-03-01', '10:00', 'První B' );
		$this->booked( '2027-03-01', '08:00', 'První A' );
		$this->booked( '2027-03-03', '08:00', 'Třetí' );

		$this->assertSame( [ 'První A', 'První B', 'Druhý', 'Třetí' ], $this->names() );
		$this->assertSame( [ 'První A', 'První B', 'Druhý' ], $this->names( [ 'to' => '2027-03-02' ] ) );
		$this->assertSame( [ 'Druhý', 'Třetí' ], $this->names( [ 'from' => '2027-03-02' ] ) );
	}

	public function test_list_is_filtered_by_status(): void {
		$this->booked( '2027-03-01', '08:00', 'Platná' );
		$cancelled = $this->booked( '2027-03-01', '09:00', 'Zrušená' );
		Pneukarnik_Cancellation::cancel_by_provozovatel( $cancelled, null );

		$this->assertSame( [ 'Platná', 'Zrušená' ], $this->names() );
		$this->assertSame( [ 'Platná' ], $this->names( [ 'status' => 'CONFIRMED' ] ) );
		$this->assertSame( [ 'Zrušená' ], $this->names( [ 'status' => 'CANCELLED' ] ) );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function searches(): array {
		return [
			'část jména bez ohledu na velikost' => [ 'nová' ],
			'firma'                             => [ 'pneu s.r' ],
			'SPZ s mezerou a malými písmeny'    => [ '5a8 12' ],
			'telefon bez mezer a předvolby'     => [ '603123' ],
			'telefon s mezerami'                => [ '603 123 456' ],
			'část e‑mailu'                      => [ 'novakova@' ],
		];
	}

	/**
	 * @dataProvider searches
	 */
	public function test_search_finds_by_name_plate_phone_and_email( string $search ): void {
		$this->booked(
			'2027-03-01',
			'08:00',
			'Jana Nováková',
			[
				'company' => 'Pneu s.r.o.',
				'plate'   => '5A8 1234',
				'phone'   => '+420 603 123 456',
				'email'   => 'novakova@example.test',
			]
		);
		$this->booked(
			'2027-03-01',
			'09:00',
			'Petr Svoboda',
			[
				'plate' => '1AB 2345',
				'phone' => '777 888 999',
				'email' => 'petr@example.test',
			]
		);

		$this->assertSame( [ 'Jana Nováková' ], $this->names( [ 'search' => $search ] ) );
	}

	public function test_list_is_paged_by_25(): void {
		$this->log_in_as( 'administrator' );
		for ( $i = 0; $i < 26; $i++ ) {
			$date = Pneukarnik_Clock::at( '2027-03-01' )->modify( "+{$i} days" )->format( 'Y-m-d' );
			$this->admin_booking( $this->admin_book( $this->tyres, $date, '08:00', [ 'outside_working_hours' => true ] ) );
		}

		$first  = $this->rest( 'GET', '/admin/bookings' )->get_data();
		$second = $this->rest( 'GET', '/admin/bookings', [ 'page' => 2 ] )->get_data();

		$this->assertSame( [ 26, 2, 25 ], [ $first['total'], $first['pages'], count( $first['bookings'] ) ] );
		$this->assertSame( [ '2027-03-26' ], array_column( $second['bookings'], 'date' ) );
	}

	public function test_invalid_filters_are_refused(): void {
		$response = $this->rest(
			'GET',
			'/admin/bookings',
			[
				'from'   => '2027-02-30',
				'status' => 'SMAZANA',
			]
		);

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'bookings.invalid_filters', $response->get_data()['code'] );
		$this->assertSame(
			[
				'from'   => 'invalid',
				'status' => 'invalid',
			],
			$response->get_data()['data']['errors']
		);
	}

	public function test_calendar_gives_each_day_with_working_hours_and_confirmed_bookings(): void {
		Pneukarnik_Day_Exceptions::add(
			'2027-03-03',
			'2027-03-03',
			false,
			[
				[
					'from' => '09:00',
					'to'   => '11:00',
				],
			],
			'Školení'
		);
		$this->booked(
			'2027-03-01',
			'09:00',
			'Jan Novák',
			[
				'leasing'         => true,
				'leasing_company' => 'ČSOB Leasing',
			]
		);
		$cancelled = $this->booked( '2027-03-01', '10:00', 'Zrušená' );
		Pneukarnik_Cancellation::cancel_by_provozovatel( $cancelled, null );

		$response = $this->rest(
			'GET',
			'/admin/calendar',
			[
				'from' => '2027-03-01',
				'to'   => '2027-03-07',
			]
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'no-store', $response->get_headers()['Cache-Control'] );
		$days = $response->get_data()['days'];
		$this->assertSame( [ '2027-03-01', '2027-03-02', '2027-03-03', '2027-03-04', '2027-03-05', '2027-03-06', '2027-03-07' ], array_column( $days, 'date' ) );
		$this->assertSame(
			[
				[
					'from' => '08:00',
					'to'   => '12:00',
				],
			],
			$days[0]['hours']
		);
		$this->assertSame(
			[
				[
					'from' => '09:00',
					'to'   => '11:00',
				],
			],
			$days[2]['hours']
		);
		$this->assertSame( 'Školení', $days[2]['note'] );
		$this->assertNull( $days[6]['hours'], 'neděle zavřeno' );
		$this->assertSame( [ 'Jan Novák' ], array_column( $days[0]['bookings'], 'name' ) );
		$this->assertSame( [ true, 'ČSOB Leasing', '09:00', '10:00' ], [ $days[0]['bookings'][0]['leasing'], $days[0]['bookings'][0]['leasing_company'], $days[0]['bookings'][0]['time_start'], $days[0]['bookings'][0]['time_end'] ] );
		$this->assertSame( [], $days[1]['bookings'] );
	}

	public function test_calendar_names_public_holidays(): void {
		$days = $this->rest(
			'GET',
			'/admin/calendar',
			[
				'from' => '2027-03-26',
				'to'   => '2027-03-26',
			]
		)->get_data()['days'];

		$this->assertNull( $days[0]['hours'] );
		$this->assertSame( 'Velký pátek', $days[0]['note'] );
	}

	/**
	 * @return array<string, array{array<string, string>}>
	 */
	public static function invalid_ranges(): array {
		return [
			'chybí do'        => [ [ 'from' => '2027-03-01' ] ],
			'neplatné datum'  => [
				[
					'from' => '2027-02-30',
					'to'   => '2027-03-01',
				],
			],
			'do před od'      => [
				[
					'from' => '2027-03-02',
					'to'   => '2027-03-01',
				],
			],
			'víc než 6 týdnů' => [
				[
					'from' => '2027-03-01',
					'to'   => '2027-04-12',
				],
			],
		];
	}

	/**
	 * @dataProvider invalid_ranges
	 * @param array<string, string> $params
	 */
	public function test_calendar_refuses_invalid_ranges( array $params ): void {
		$response = $this->rest( 'GET', '/admin/calendar', $params );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'calendar.invalid_range', $response->get_data()['code'] );
	}

	/**
	 * Online Rezervace se jménem a dalšími poli, vrátí její id.
	 *
	 * @param array<string, mixed> $fields
	 */
	private function booked( string $date, string $time, string $name, array $fields = [] ): int {
		return (int) $this->created_booking( $this->book( $this->tyres, $date, $time, [ 'name' => $name ] + $fields ) )['id'];
	}

	/**
	 * @param array<string, mixed> $filters
	 * @return list<string>
	 */
	private function names( array $filters = [] ): array {
		$response = $this->rest( 'GET', '/admin/bookings', $filters );
		$this->assertSame( 200, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );
		return array_column( $response->get_data()['bookings'], 'name' );
	}
}
