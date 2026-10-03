<?php
/**
 * Rezervace Provozovatelem přes REST /admin/…: telefonická objednávka, úprava a Zrušení.
 * Překryv se odmítne vždy, mimo Pracovní dobu jen s vědomým potvrzením.
 */

declare(strict_types=1);

class AdminBookingsTest extends Pneukarnik_REST_Test_Case {

	private const MONDAY = '2027-03-01';
	private const SUNDAY = '2027-03-07';

	private int $tyres;

	private int $balance;

	public function set_up(): void {
		parent::set_up();
		Pneukarnik_Clock::freeze( '2027-02-20 12:00' );
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
		Pneukarnik_Working_Hours::save( array_merge( Pneukarnik_Working_Hours::get_all(), [ 'sun' => null ] ) );
		$this->set_booking_rules( 60, 60, 30 );
		update_option( 'pneukarnik_cancellation_hours', 24 );
		update_option( 'pneukarnik_email', 'servis@example.test' );
		$this->tyres   = $this->create_service( 60, false, 'Přezutí' );
		$this->balance = $this->create_service( 30, false, 'Vyvážení' );
		$this->capture_mails();
		$this->log_in_as( 'pneukarnik_manager' );
	}

	public function test_phone_booking_needs_only_services_termin_name_and_phone(): void {
		$booking = $this->admin_booking( $this->admin_book( $this->tyres, self::MONDAY, '09:00' ) );

		$this->assertSame( 'provozovatel', $booking['source'] );
		$this->assertSame( 'CONFIRMED', $booking['status'] );
		$this->assertSame( [ self::MONDAY, '09:00', '10:00' ], [ $booking['date'], $booking['time_start'], $booking['time_end'] ] );
		$this->assertSame( [ '', '' ], [ $booking['email'], $booking['plate'] ] );
		$this->assertNotContains( '09:00', $this->free_starts( $this->tyres, self::MONDAY ), 'čas zmizel z online nabídky' );
		$this->assertSame( [], $this->mails, 'bez e‑mailu nikomu nic nepřijde' );
	}

	public function test_name_and_phone_are_required_and_given_fields_are_checked(): void {
		$response = $this->rest( 'POST', '/admin/bookings', [ 'service_ids' => [ $this->tyres ] ] );

		$this->assertSame( 422, $response->get_status() );
		$this->assertSame( 'booking.invalid_fields', $response->get_data()['code'] );
		$this->assertSame(
			[
				'date'  => 'required',
				'time'  => 'required',
				'name'  => 'required',
				'phone' => 'required',
			],
			$response->get_data()['data']['errors']
		);

		$response = $this->admin_book( $this->tyres, self::MONDAY, '09:00', [ 'email' => 'není e-mail' ] );
		$this->assertSame( [ 'email' => 'invalid' ], $response->get_data()['data']['errors'] );
	}

	public function test_customer_with_email_gets_the_confirmation(): void {
		$this->admin_booking( $this->admin_book( $this->tyres, self::MONDAY, '09:00', [ 'email' => 'jan@example.test' ] ) );

		$mail = $this->mail_to( 'jan@example.test' );
		$this->assertSame( 'Potvrzení rezervace na pondělí 1. 3. 2027 v 9:00', $mail['subject'] );
		$this->assertCount( 1, $this->mails, 'Provozovatel o své Rezervaci e‑mail nedostane' );
	}

	public function test_all_details_are_stored_and_shown(): void {
		$booking = $this->admin_booking(
			$this->admin_book(
				[ $this->tyres, $this->balance ],
				self::MONDAY,
				'09:00',
				[
					'company'         => 'Firma s.r.o.',
					'email'           => 'Firma@Example.test',
					'plate'           => '1ab 2345',
					'vehicle'         => 'Škoda Octavia',
					'note'            => "Volat předem\nna mobil",
					'leasing'         => true,
					'leasing_company' => 'ČSOB Leasing',
					'stored_wheels'   => true,
				]
			)
		);

		$this->assertSame( $booking, $this->admin_booking( $this->rest( 'GET', "/admin/bookings/{$booking['id']}" ), 200 ) );
		$this->assertSame( [ 'Přezutí', 'Vyvážení' ], array_column( $booking['services'], 'name' ) );
		$this->assertSame( '10:30', $booking['time_end'] );
		$this->assertSame(
			[ 'Firma s.r.o.', 'firma@example.test', '1AB2345', 'Škoda Octavia', "Volat předem\nna mobil", true, 'ČSOB Leasing', true ],
			[ $booking['company'], $booking['email'], $booking['plate'], $booking['vehicle'], $booking['note'], $booking['leasing'], $booking['leasing_company'], $booking['stored_wheels'] ]
		);
	}

	public function test_online_booking_has_source_web(): void {
		$this->log_in_as( 'pneukarnik_viewer' );
		$id = $this->created_booking( $this->book( $this->tyres, self::MONDAY, '09:00' ) )['id'];

		$this->assertSame( 'web', $this->admin_booking( $this->rest( 'GET', "/admin/bookings/{$id}" ), 200 )['source'] );
	}

	public function test_phone_only_service_off_the_grid_and_beyond_the_horizon_can_be_entered(): void {
		$phone_only = $this->create_service( 45, false, 'Geometrie' );
		update_post_meta( $phone_only, '_service_bookable', '' );

		$booking = $this->admin_booking( $this->admin_book( $phone_only, '2027-06-01', '09:10' ) );

		$this->assertSame( [ '09:10', '09:55' ], [ $booking['time_start'], $booking['time_end'] ] );
	}

	public function test_overlap_is_refused_even_when_outside_hours_is_confirmed(): void {
		$this->created_booking( $this->book( $this->tyres, self::MONDAY, '09:00' ) );

		foreach ( [ '08:30', '09:00', '09:30' ] as $time ) {
			$response = $this->admin_book( $this->tyres, self::MONDAY, $time, [ 'outside_working_hours' => true ] );
			$this->assertSame( 409, $response->get_status(), $time );
			$this->assertSame( 'booking.slot_taken', $response->get_data()['code'] );
		}
		$this->admin_booking( $this->admin_book( $this->tyres, self::MONDAY, '10:00' ) );
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function outside_working_hours(): array {
		return [
			'před otevřením'            => [ self::MONDAY, '07:30' ],
			'přes polední pauzu'        => [ self::MONDAY, '11:30' ],
			'po zavírací době'          => [ self::MONDAY, '16:30' ],
			'zavřený den v týdnu'       => [ self::SUNDAY, '09:00' ],
			'státní svátek (Pá 26. 3.)' => [ '2027-03-26', '09:00' ],
		];
	}

	/**
	 * @dataProvider outside_working_hours
	 */
	public function test_outside_working_hours_needs_a_deliberate_confirmation( string $date, string $time ): void {
		$response = $this->admin_book( $this->tyres, $date, $time );
		$this->assertSame( 422, $response->get_status() );
		$this->assertSame( 'booking.outside_working_hours', $response->get_data()['code'] );

		$booking = $this->admin_booking( $this->admin_book( $this->tyres, $date, $time, [ 'outside_working_hours' => true ] ) );

		$this->assertSame( [ $date, $time ], [ $booking['date'], $booking['time_start'] ] );
	}

	public function test_booking_must_end_by_midnight(): void {
		$response = $this->admin_book( $this->tyres, self::MONDAY, '23:30', [ 'outside_working_hours' => true ] );

		$this->assertSame( 'booking.past_midnight', $response->get_data()['code'] );
	}

	public function test_contact_and_note_can_be_changed_without_moving_the_termin(): void {
		$booking     = $this->admin_booking( $this->admin_book( $this->tyres, self::MONDAY, '09:00', [ 'email' => 'jan@example.test' ] ) );
		$this->mails = [];

		$changed = $this->admin_booking(
			$this->rest(
				'PATCH',
				"/admin/bookings/{$booking['id']}",
				[
					'name'  => 'Jana Nováková',
					'phone' => '777 000 111',
					'note'  => 'Přiveze vlastní kola',
				]
			),
			200
		);

		$this->assertSame( [ 'Jana Nováková', '777 000 111', 'Přiveze vlastní kola' ], [ $changed['name'], $changed['phone'], $changed['note'] ] );
		$this->assertSame( array_diff_key( $booking, array_flip( [ 'name', 'phone', 'note' ] ) ), array_diff_key( $changed, array_flip( [ 'name', 'phone', 'note' ] ) ) );
		$this->assertSame( [], $this->mails );
	}

	public function test_moving_the_termin_frees_the_old_time(): void {
		$booking = $this->admin_booking( $this->admin_book( $this->tyres, self::MONDAY, '09:00' ) );

		$moved = $this->admin_booking(
			$this->rest(
				'PATCH',
				"/admin/bookings/{$booking['id']}",
				[
					'date' => '2027-03-02',
					'time' => '14:00',
				]
			),
			200
		);

		$this->assertSame( [ '2027-03-02', '14:00', '15:00' ], [ $moved['date'], $moved['time_start'], $moved['time_end'] ] );
		$this->assertContains( '09:00', $this->free_starts( $this->tyres, self::MONDAY ) );
		$this->assertNotContains( '14:00', $this->free_starts( $this->tyres, '2027-03-02' ) );
	}

	public function test_booking_can_move_over_its_own_time(): void {
		$booking = $this->admin_booking( $this->admin_book( $this->tyres, self::MONDAY, '09:00' ) );

		$moved = $this->admin_booking( $this->rest( 'PATCH', "/admin/bookings/{$booking['id']}", [ 'time' => '09:30' ] ), 200 );

		$this->assertSame( '10:30', $moved['time_end'] );
	}

	public function test_moving_onto_another_booking_is_refused(): void {
		$this->created_booking( $this->book( $this->tyres, self::MONDAY, '10:00' ) );
		$booking = $this->admin_booking( $this->admin_book( $this->tyres, self::MONDAY, '08:00' ) );

		$response = $this->rest( 'PATCH', "/admin/bookings/{$booking['id']}", [ 'time' => '09:30' ] );

		$this->assertSame( 409, $response->get_status() );
		$this->assertSame( 'booking.slot_taken', $response->get_data()['code'] );
		$this->assertSame( '08:00', $this->admin_booking( $this->rest( 'GET', "/admin/bookings/{$booking['id']}" ), 200 )['time_start'] );
	}

	public function test_adding_a_service_extends_the_booking_and_checks_overlap(): void {
		$this->created_booking( $this->book( $this->tyres, self::MONDAY, '10:00' ) );
		$booking = $this->admin_booking( $this->admin_book( $this->tyres, self::MONDAY, '08:30' ) );

		$changed = $this->admin_booking( $this->rest( 'PATCH', "/admin/bookings/{$booking['id']}", [ 'service_ids' => [ $this->tyres, $this->balance ] ] ), 200 );
		$this->assertSame( '10:00', $changed['time_end'] );

		$response = $this->rest( 'PATCH', "/admin/bookings/{$booking['id']}", [ 'service_ids' => [ $this->tyres, $this->balance, $this->create_service( 15, false, 'Ventilky' ) ] ] );
		$this->assertSame( 'booking.slot_taken', $response->get_data()['code'] );
	}

	public function test_moving_outside_working_hours_needs_a_deliberate_confirmation(): void {
		$booking = $this->admin_booking( $this->admin_book( $this->tyres, self::MONDAY, '09:00' ) );

		$this->assertSame( 'booking.outside_working_hours', $this->rest( 'PATCH', "/admin/bookings/{$booking['id']}", [ 'time' => '17:00' ] )->get_data()['code'] );

		$moved = $this->admin_booking(
			$this->rest(
				'PATCH',
				"/admin/bookings/{$booking['id']}",
				[
					'time'                  => '17:00',
					'outside_working_hours' => true,
				]
			),
			200
		);
		$this->assertSame( '17:00', $moved['time_start'] );
	}

	public function test_booking_outside_working_hours_keeps_its_time_when_only_the_note_changes(): void {
		$booking = $this->admin_booking( $this->admin_book( $this->tyres, self::SUNDAY, '09:00', [ 'outside_working_hours' => true ] ) );

		$changed = $this->admin_booking( $this->rest( 'PATCH', "/admin/bookings/{$booking['id']}", [ 'note' => 'Výjimečně v neděli' ] ), 200 );

		$this->assertSame( 'Výjimečně v neděli', $changed['note'] );
	}

	public function test_unchanged_services_keep_the_price_from_the_time_of_booking(): void {
		$booking = $this->admin_booking( $this->admin_book( $this->tyres, self::MONDAY, '09:00' ) );
		update_post_meta( $this->tyres, '_service_price', '900' );
		wp_update_post(
			[
				'ID'         => $this->tyres,
				'post_title' => 'Přezutí nově',
			]
		);

		$changed = $this->admin_booking( $this->rest( 'PATCH', "/admin/bookings/{$booking['id']}", [ 'time' => '10:00' ] ), 200 );

		$this->assertSame( $booking['services'], $changed['services'] );
	}

	public function test_cancelled_or_missing_booking_cannot_be_changed(): void {
		$booking = $this->admin_booking( $this->admin_book( $this->tyres, self::MONDAY, '09:00' ) );
		$this->rest( 'POST', "/admin/bookings/{$booking['id']}/cancel" );

		$this->assertSame( 'booking.cancelled', $this->rest( 'PATCH', "/admin/bookings/{$booking['id']}", [ 'note' => 'x' ] )->get_data()['code'] );
		$this->assertSame( 404, $this->rest( 'PATCH', '/admin/bookings/999999', [ 'note' => 'x' ] )->get_status() );
		$this->assertSame( 404, $this->rest( 'GET', '/admin/bookings/999999' )->get_status() );
	}

	public function test_provozovatel_cancels_any_time_and_the_customer_gets_an_email_with_the_reason(): void {
		Pneukarnik_Clock::freeze( '2027-02-28 12:00' );
		$id    = (int) $this->created_booking( $this->book( $this->tyres, self::MONDAY, '09:00' ) )['id'];
		$token = $this->cancel_token_from( $this->mail_to( 'jan@example.test' ) );
		Pneukarnik_Clock::freeze( '2027-03-01 08:30' );
		$this->assertSame( 'cancellation.too_late', $this->cancellation( $token )->get_data()['code'], 'Zákazník už zrušit nemůže' );
		$this->mails = [];

		$response = $this->rest( 'POST', "/admin/bookings/{$id}/cancel", [ 'reason' => 'Porucha zvedáku' ] );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'cancellation.cancelled', $response->get_data()['code'] );
		$this->assertSame( [ 'CANCELLED', 'Porucha zvedáku' ], [ $response->get_data()['booking']['status'], $response->get_data()['booking']['cancel_reason'] ] );
		$mail = $this->mail_to( 'jan@example.test' );
		$this->assertStringContainsString( 'Důvod: Porucha zvedáku', $mail['text'] );
		$this->assertCount( 1, $this->mails, 'Provozovatel o svém Zrušení e‑mail nedostane' );
	}

	public function test_cancellation_frees_the_time_and_works_once(): void {
		$booking = $this->admin_booking( $this->admin_book( $this->tyres, self::MONDAY, '09:00' ) );

		$this->assertSame( 200, $this->rest( 'POST', "/admin/bookings/{$booking['id']}/cancel" )->get_status() );
		$this->assertContains( '09:00', $this->free_starts( $this->tyres, self::MONDAY ) );
		$this->assertSame( [], $this->mails, 'Zákazník bez e‑mailu' );

		$again = $this->rest( 'POST', "/admin/bookings/{$booking['id']}/cancel" );
		$this->assertSame( 409, $again->get_status() );
		$this->assertSame( 'cancellation.already_cancelled', $again->get_data()['code'] );
		$this->assertSame( 404, $this->rest( 'POST', '/admin/bookings/999999/cancel' )->get_status() );
	}
}
