<?php
/**
 * Oprávnění chráněného REST (regrese bezpečnostních děr starého webu): každá akce mimo
 * veřejné rozhraní bez oprávnění selže, „prohlížet rezervace“ jen čte, „spravovat“ i mění.
 */

declare(strict_types=1);

class AdminPermissionsTest extends Pneukarnik_REST_Test_Case {

	/**
	 * Veřejné rozhraní webu. Všechno ostatní v namespace musí chtít oprávnění.
	 * Nová veřejná cesta se sem musí přidat vědomě.
	 */
	private const PUBLIC_ROUTES = [
		'GET /pneukarnik/v1',
		'GET /pneukarnik/v1/services',
		'GET /pneukarnik/v1/services/(?P<slug>[a-z0-9-]+)',
		'GET /pneukarnik/v1/slots',
		'GET /pneukarnik/v1/available-days',
		'POST /pneukarnik/v1/bookings',
		'GET /pneukarnik/v1/cancellation',
		'POST /pneukarnik/v1/cancellation',
		'GET /pneukarnik/v1/prefill',
		'GET /pneukarnik/v1/calendar', // iCal, chráněný tajným tokenem (CalendarFeedTest).
	];

	private const MONDAY = '2027-03-01';

	private int $tyres;

	private int $booking_id;

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
		$this->set_booking_rules( 60 );
		$this->tyres      = $this->create_service( 60 );
		$this->booking_id = (int) $this->created_booking( $this->book( $this->tyres, self::MONDAY, '09:00' ) )['id'];
		$this->capture_mails();
		$this->mails = [];
	}

	public function test_every_protected_action_refuses_anonymous_and_unauthorised_users(): void {
		$protected = $this->protected_actions();
		$this->assertNotEmpty( $protected );

		foreach ( [
			'nepřihlášený' => [ null, 401 ],
			'odběratel'    => [ 'subscriber', 403 ],
			'autor'        => [ 'author', 403 ],
		] as $who => [ $role, $status ] ) {
			wp_set_current_user( 0 );
			if ( null !== $role ) {
				$this->log_in_as( $role );
			}
			foreach ( $protected as [ $method, $route ] ) {
				$response = $this->rest( $method, $route, [] );
				$this->assertSame( $status, $response->get_status(), "{$who}: {$method} {$route}" );
				$this->assertSame( 'rest_forbidden', $response->get_data()['code'], "{$who}: {$method} {$route}" );
			}
		}
		$this->assertBookingUnchanged();
	}

	public function test_public_routes_listed_here_exist(): void {
		$registered = [];
		foreach ( rest_get_server()->get_routes( 'pneukarnik/v1' ) as $route => $handlers ) {
			foreach ( $handlers as $handler ) {
				foreach ( array_keys( $handler['methods'] ) as $method ) {
					$registered[] = "{$method} {$route}";
				}
			}
		}

		$this->assertSame( [], array_values( array_diff( self::PUBLIC_ROUTES, $registered ) ) );
	}

	public function test_viewer_can_read_but_not_change(): void {
		$this->log_in_as( 'pneukarnik_viewer' );

		$this->assertSame(
			200,
			$this->rest(
				'GET',
				'/admin/calendar',
				[
					'from' => self::MONDAY,
					'to'   => self::MONDAY,
				]
			)->get_status()
		);
		$this->assertSame( 200, $this->rest( 'GET', '/admin/bookings' )->get_status() );
		$this->assertSame( 200, $this->rest( 'GET', "/admin/bookings/{$this->booking_id}" )->get_status() );

		$this->assertSame( 403, $this->admin_book( $this->tyres, self::MONDAY, '10:00' )->get_status() );
		$this->assertSame( 403, $this->rest( 'PATCH', "/admin/bookings/{$this->booking_id}", [ 'note' => 'změna' ] )->get_status() );
		$this->assertSame( 403, $this->rest( 'POST', "/admin/bookings/{$this->booking_id}/cancel" )->get_status() );
		$this->assertBookingUnchanged();
		$this->assertContains( '10:00', $this->free_starts( $this->tyres, self::MONDAY ) );
	}

	public function test_manager_can_create_change_and_cancel(): void {
		$this->log_in_as( 'pneukarnik_manager' );

		$this->admin_booking( $this->admin_book( $this->tyres, self::MONDAY, '10:00' ) );
		$this->admin_booking( $this->rest( 'PATCH', "/admin/bookings/{$this->booking_id}", [ 'note' => 'změna' ] ), 200 );
		$this->assertSame( 200, $this->rest( 'POST', "/admin/bookings/{$this->booking_id}/cancel" )->get_status() );
	}

	public function test_administrator_has_both_capabilities(): void {
		$this->log_in_as( 'administrator' );

		$this->assertTrue( current_user_can( Pneukarnik_Access::VIEW ) );
		$this->assertTrue( current_user_can( Pneukarnik_Access::MANAGE ) );
	}

	/**
	 * Metody a cesty mimo veřejné rozhraní, s id existující Rezervace místo parametru.
	 *
	 * @return list<array{0:string,1:string}>
	 */
	private function protected_actions(): array {
		$actions = [];
		foreach ( rest_get_server()->get_routes( 'pneukarnik/v1' ) as $route => $handlers ) {
			foreach ( $handlers as $handler ) {
				foreach ( array_keys( $handler['methods'] ) as $method ) {
					if ( in_array( "{$method} {$route}", self::PUBLIC_ROUTES, true ) ) {
						continue;
					}
					$path      = (string) preg_replace( '/\(\?P<id>[^)]*\)/', (string) $this->booking_id, substr( $route, strlen( '/pneukarnik/v1' ) ) );
					$actions[] = [ $method, $path ];
				}
			}
		}
		return $actions;
	}

	private function assertBookingUnchanged(): void {
		$booking = Pneukarnik_Booking::get_by_id( $this->booking_id );
		$this->assertNotNull( $booking );
		$this->assertSame( 'CONFIRMED', $booking['status'] );
		$this->assertSame( '09:00', $booking['time_start'] );
		$this->assertNull( $booking['customer_note'] );
		$this->assertSame( [], $this->mails );
	}
}
