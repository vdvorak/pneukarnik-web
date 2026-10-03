<?php
/**
 * Vytvoření Rezervace přes REST: validace polí, obsazenost, potvrzení.
 */

declare(strict_types=1);

class CreateBookingTest extends Pneukarnik_REST_Test_Case {

	private const MONDAY = '2027-03-01';

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
		$this->set_booking_rules( 30 );
		$this->service = $this->create_service( 60 );
	}

	public function test_valid_booking_is_created_and_occupies_the_workshop(): void {
		$response = $this->book(
			$this->service,
			self::MONDAY,
			'09:00',
			[
				'vehicle' => 'Škoda Octavia',
				'note'    => "Řádek 1\nŘádek 2",
			]
		);

		$this->assertSame( 201, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( self::MONDAY, $data['date'] );
		$this->assertSame( '09:00', $data['time_start'] );
		$this->assertSame( '10:00', $data['time_end'] );
		$this->assertMatchesRegularExpression( '#/rezervace/potvrzeni/\?r=[0-9a-f]{64}$#', $data['confirmation_url'] );
		$this->assertStringNotContainsString( 'jan@example.test', (string) wp_json_encode( $data ) );
		$this->assertNotContains( '09:30', $this->free_starts( $this->service, self::MONDAY ) );
	}

	public function test_confirmation_token_finds_termin_service_and_plate(): void {
		$url = $this->book( $this->service, self::MONDAY, '09:00', [ 'plate' => '1ab2345' ] )->get_data()['confirmation_url'];
		parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );

		$booking = Pneukarnik_Booking::find_by_confirmation_token( (string) $query['r'] );

		$this->assertNotNull( $booking );
		$this->assertSame( self::MONDAY, $booking['booking_date'] );
		$this->assertSame( '09:00', $booking['time_start'] );
		$this->assertSame( 'Přezutí', $booking['service_name'] );
		$this->assertSame( '1AB2345', $booking['customer_plate'] );
		$this->assertNull( Pneukarnik_Booking::find_by_confirmation_token( str_repeat( 'a', 64 ) ) );
	}

	public function test_missing_fields_are_reported_each_with_its_code(): void {
		$response = $this->rest(
			'POST',
			'/bookings',
			[
				'service_id' => $this->service,
				'date'       => self::MONDAY,
				'time'       => '09:00',
			]
		);

		$this->assertSame( 422, $response->get_status() );
		$this->assertSame( 'booking.invalid_fields', $response->get_data()['code'] );
		$this->assertSame(
			[
				'name'         => 'required',
				'phone'        => 'required',
				'email'        => 'required',
				'plate'        => 'required',
				'consent_gdpr' => 'required',
			],
			$response->get_data()['data']['errors']
		);
	}

	/**
	 * @return array<string, array{string, mixed, string}>
	 */
	public static function invalid_fields(): array {
		return [
			'e‑mail'           => [ 'email', 'jan@', 'invalid' ],
			'krátký telefon'   => [ 'phone', '603 12', 'invalid' ],
			'písmena v tel.'   => [ 'phone', 'zavolejte', 'invalid' ],
			'SPZ znaky'        => [ 'plate', '1AB*2345', 'invalid' ],
			'dlouhé jméno'     => [ 'name', str_repeat( 'a', 121 ), 'too_long' ],
			'dlouhý telefon'   => [ 'phone', '603-123-456' . str_repeat( '-', 40 ), 'too_long' ],
			'dlouhý vůz'       => [ 'vehicle', str_repeat( 'a', 101 ), 'too_long' ],
			'dlouhá pozn.'     => [ 'note', str_repeat( 'a', 1001 ), 'too_long' ],
			'souhlas ne'       => [ 'consent_gdpr', false, 'required' ],
			'datum'            => [ 'date', '2027-02-30', 'invalid' ],
			'čas'              => [ 'time', '9:00', 'invalid' ],
			'pole místo textu' => [ 'name', [ 'Jan' ], 'invalid' ],
		];
	}

	/**
	 * @dataProvider invalid_fields
	 */
	public function test_invalid_field_has_stable_code( string $field, mixed $value, string $code ): void {
		$response = $this->book( $this->service, self::MONDAY, '09:00', [ $field => $value ] );

		$this->assertSame( 422, $response->get_status() );
		$this->assertSame( [ $field => $code ], $response->get_data()['data']['errors'] );
	}

	public function test_phone_sent_as_number_is_accepted(): void {
		$this->assertSame( 201, $this->book( $this->service, self::MONDAY, '09:00', [ 'phone' => 603123456 ] )->get_status() );
	}

	public function test_overlapping_booking_is_rejected_as_taken(): void {
		$this->assertSame( 201, $this->book( $this->service, self::MONDAY, '09:00' )->get_status() );

		$response = $this->book( $this->create_service( 90 ), self::MONDAY, '08:00' ); // 08:00–09:30

		$this->assertSame( 409, $response->get_status() );
		$this->assertSame( 'booking.slot_taken', $response->get_data()['code'] );
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function unavailable_termins(): array {
		return [
			'mimo mřížku'         => [ self::MONDAY, '09:10' ],
			'přes konec bloku'    => [ self::MONDAY, '11:30' ],
			'před Pracovní dobou' => [ self::MONDAY, '07:00' ],
			'minulý den'          => [ '2027-02-25', '09:00' ],
		];
	}

	/**
	 * @dataProvider unavailable_termins
	 */
	public function test_termin_outside_offer_is_unavailable( string $date, string $time ): void {
		$response = $this->book( $this->service, $date, $time );

		$this->assertSame( 422, $response->get_status() );
		$this->assertSame( 'booking.slot_unavailable', $response->get_data()['code'] );
	}

	public function test_unknown_service_is_not_found(): void {
		$response = $this->book( 999999, self::MONDAY, '09:00' );

		$this->assertSame( 404, $response->get_status() );
		$this->assertSame( 'booking.service_not_found', $response->get_data()['code'] );
	}
}
