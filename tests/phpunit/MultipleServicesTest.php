<?php
/**
 * Víc Služeb v jedné Rezervaci: úsek trvá součet Délek, Rezervace si pamatuje Délku a cenu
 * z okamžiku vytvoření, seznam Služeb nesmí obsahovat duplicitu ani Službu mimo online nabídku.
 */

declare(strict_types=1);

class MultipleServicesTest extends Pneukarnik_REST_Test_Case {

	private const MONDAY = '2027-03-01';

	private int $tyres;
	private int $balancing;

	public function set_up(): void {
		parent::set_up();
		Pneukarnik_Clock::freeze( '2027-02-26 12:00' );
		$this->set_working_hours_every_day(
			[
				[
					'from' => '08:00',
					'to'   => '12:00',
				],
				[
					'from' => '13:00',
					'to'   => '17:00',
				],
			]
		);
		$this->set_booking_rules( 30 );
		$this->tyres     = $this->create_service( 60, false, 'Přezutí' );
		$this->balancing = $this->create_service( 30, false, 'Vyvážení' );
	}

	public function test_termins_fit_the_sum_of_durations_inside_one_block(): void {
		$response = $this->slots( [ $this->tyres, $this->balancing ], self::MONDAY );

		$this->assertSame( 200, $response->get_status() );
		$slots = $response->get_data()['slots'];
		$this->assertSame(
			[ '08:00', '08:30', '09:00', '09:30', '10:00', '10:30', '13:00', '13:30', '14:00', '14:30', '15:00', '15:30' ],
			array_column( $slots, 'time_start' )
		);
		$this->assertSame( '09:30', $slots[0]['time_end'] );
	}

	public function test_termin_offered_for_one_service_is_not_offered_when_the_sum_overflows_the_block(): void {
		$this->assertContains( '11:00', $this->free_starts( $this->tyres, self::MONDAY ) );
		$this->assertNotContains( '11:00', $this->free_starts( [ $this->tyres, $this->balancing ], self::MONDAY ) );

		$response = $this->book( [ $this->tyres, $this->balancing ], self::MONDAY, '11:00' );

		$this->assertSame( 422, $response->get_status() );
		$this->assertSame( 'booking.slot_unavailable', $response->get_data()['code'] );
	}

	public function test_booking_occupies_the_workshop_for_the_sum_of_durations(): void {
		$response = $this->book( [ $this->tyres, $this->balancing ], self::MONDAY, '08:00' );

		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( '09:30', $response->get_data()['time_end'] );
		$this->assertSame( [ '09:30', '10:00', '10:30', '11:00' ], array_slice( $this->free_starts( $this->tyres, self::MONDAY ), 0, 4 ) );
	}

	public function test_booking_whose_sum_overlaps_another_booking_is_taken(): void {
		$this->assertSame( 201, $this->book( $this->balancing, self::MONDAY, '10:00' )->get_status() );
		$this->assertNotContains( '09:00', $this->free_starts( [ $this->tyres, $this->balancing ], self::MONDAY ) );

		$response = $this->book( [ $this->tyres, $this->balancing ], self::MONDAY, '09:00' ); // 09:00–10:30

		$this->assertSame( 409, $response->get_status() );
		$this->assertSame( 'booking.slot_taken', $response->get_data()['code'] );
	}

	public function test_booking_keeps_services_in_order_with_duration_and_price_at_creation(): void {
		update_post_meta( $this->balancing, '_service_price', '250' );
		update_post_meta( $this->balancing, '_service_price_from', '1' );
		$booking = $this->booking_from( $this->book( [ $this->balancing, $this->tyres ], self::MONDAY, '08:00' ) );

		update_post_meta( $this->tyres, '_service_duration', 120 );
		update_post_meta( $this->tyres, '_service_price', '900' );
		wp_update_post(
			[
				'ID'         => $this->tyres,
				'post_title' => 'Přezutí s mytím',
			]
		);

		$this->assertSame(
			[
				[
					'service_id' => $this->balancing,
					'name'       => 'Vyvážení',
					'duration'   => 30,
					'price'      => 250,
					'price_from' => true,
				],
				[
					'service_id' => $this->tyres,
					'name'       => 'Přezutí',
					'duration'   => 60,
					'price'      => 600,
					'price_from' => false,
				],
			],
			$this->booking_from_token( $booking )['services']
		);
		$this->assertSame( '09:30', $this->booking_from_token( $booking )['time_end'] );
	}

	public function test_price_by_vehicle_is_kept_without_amount(): void {
		update_post_meta( $this->tyres, '_service_price_by_vehicle', '1' );

		$booking = $this->booking_from_token( $this->booking_from( $this->book( $this->tyres, self::MONDAY, '08:00' ) ) );

		$this->assertNull( $booking['services'][0]['price'] );
	}

	/**
	 * @return array<string, array{mixed, string}>
	 */
	public static function invalid_service_lists(): array {
		return [
			'chybí'          => [ null, 'required' ],
			'prázdný seznam' => [ [], 'required' ],
			'text'           => [ [ 'abc' ], 'invalid' ],
			'ne seznam'      => [ 'abc', 'invalid' ],
			'příliš mnoho'   => [ range( 1, 11 ), 'too_many' ],
		];
	}

	/**
	 * @dataProvider invalid_service_lists
	 */
	public function test_invalid_service_list_has_stable_code( mixed $service_ids, string $code ): void {
		$response = $this->book( $this->tyres, self::MONDAY, '08:00', [ 'service_ids' => $service_ids ] );

		$this->assertSame( 422, $response->get_status() );
		$this->assertSame( [ 'service_ids' => $code ], $response->get_data()['data']['errors'] );
	}

	public function test_same_service_twice_is_rejected(): void {
		$response = $this->book( [ $this->tyres, $this->balancing, $this->tyres ], self::MONDAY, '08:00' );

		$this->assertSame( 422, $response->get_status() );
		$this->assertSame( [ 'service_ids' => 'duplicate' ], $response->get_data()['data']['errors'] );
		$this->assertSame( 400, $this->slots( [ $this->tyres, $this->tyres ], self::MONDAY )->get_status() );
	}

	public function test_service_not_bookable_online_cannot_be_added(): void {
		$phone_only = $this->create_service( 30, false, 'Geometrie' );
		update_post_meta( $phone_only, '_service_bookable', '' );

		$booking = $this->book( [ $this->tyres, $phone_only ], self::MONDAY, '08:00' );
		$slots   = $this->slots( [ $this->tyres, $phone_only ], self::MONDAY );

		$this->assertSame( 422, $booking->get_status() );
		$this->assertSame( 'booking.service_not_bookable', $booking->get_data()['code'] );
		$this->assertSame( 422, $slots->get_status() );
		$this->assertSame( 'booking.service_not_bookable', $slots->get_data()['code'] );
	}

	public function test_unknown_service_in_the_list_is_not_found(): void {
		$response = $this->book( [ $this->tyres, 999999 ], self::MONDAY, '08:00' );

		$this->assertSame( 404, $response->get_status() );
		$this->assertSame( 'booking.service_not_found', $response->get_data()['code'] );
	}

	/**
	 * @return string Token stránky potvrzení.
	 */
	private function booking_from( WP_REST_Response $response ): string {
		$this->assertSame( 201, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );
		parse_str( (string) wp_parse_url( $response->get_data()['confirmation_url'], PHP_URL_QUERY ), $query );
		return (string) $query['r'];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function booking_from_token( string $token ): array {
		$booking = Pneukarnik_Booking::find_by_confirmation_token( $token );
		$this->assertNotNull( $booking );
		return $booking;
	}
}
