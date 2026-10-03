<?php
/**
 * Základ testů pluginu: požadavky jdou přes interní REST server WordPressu,
 * „teď“ se nastavuje přes Pneukarnik_Clock.
 */

declare(strict_types=1);

abstract class Pneukarnik_REST_Test_Case extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		// WP test case běží v transakci; plugin pak místo vlastní transakce použije savepoint.
		Pneukarnik_DB::use_savepoints( true );
	}

	public function tear_down(): void {
		Pneukarnik_DB::use_savepoints( false );
		Pneukarnik_Clock::reset();
		parent::tear_down();
	}

	/**
	 * @param array<string, mixed> $params Query parametry (GET) nebo JSON tělo (ostatní metody).
	 */
	protected function rest( string $method, string $route, array $params = [] ): WP_REST_Response {
		$request = new WP_REST_Request( $method, '/pneukarnik/v1' . $route );
		if ( 'GET' === $method ) {
			$request->set_query_params( $params );
		} else {
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( (string) wp_json_encode( $params ) );
		}
		return rest_do_request( $request );
	}

	/**
	 * Zveřejněná Služba se všemi povinnými částmi.
	 */
	protected function create_service( int $duration_minutes, bool $seasonal = false, string $title = 'Přezutí' ): int {
		return self::factory()->post->create(
			[
				'post_type'   => 'pneukarnik_service',
				'post_status' => 'publish',
				'post_title'  => $title,
				'meta_input'  => [
					'_service_category'    => 'pneuservis',
					'_service_perex'       => 'Sezónní přezutí.',
					'_service_price'       => '600',
					'_service_duration'    => $duration_minutes,
					'_service_bookable'    => '1',
					'_service_is_seasonal' => $seasonal ? '1' : '',
				],
			]
		);
	}

	/**
	 * Stejná Pracovní doba pro všechny dny v týdnu.
	 *
	 * @param list<array{from:string,to:string}> $blocks
	 */
	protected function set_working_hours_every_day( array $blocks ): void {
		Pneukarnik_Working_Hours::save( array_fill_keys( [ 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' ], $blocks ) );
	}

	/**
	 * Pravidla nabídky Termínů: krok mřížky, předstih pro dnešek (minuty), horizont (dny).
	 */
	protected function set_booking_rules( int $grid_step, int $lead_minutes = 0, int $horizon_days = 365 ): void {
		update_option( 'pneukarnik_grid_step', $grid_step );
		update_option( 'pneukarnik_lead_minutes', $lead_minutes );
		update_option( 'pneukarnik_horizon_days', $horizon_days );
	}

	/**
	 * @param int|list<int> $service_ids Jedna Služba nebo víc Služeb jedné Rezervace.
	 */
	protected function slots( int|array $service_ids, string $date ): WP_REST_Response {
		return $this->rest(
			'GET',
			'/slots',
			[
				'service_ids' => (array) $service_ids,
				'date'        => $date,
			]
		);
	}

	/**
	 * @param int|list<int> $service_ids
	 * @return list<string> Začátky nabízených volných Termínů (HH:MM).
	 */
	protected function free_starts( int|array $service_ids, string $date ): array {
		$response = $this->slots( $service_ids, $date );
		$this->assertSame( 200, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );
		return array_map( static fn( array $slot ): string => $slot['time_start'], $response->get_data()['slots'] );
	}

	/**
	 * Platný požadavek na vytvoření Rezervace, jednotlivá pole jde přepsat.
	 *
	 * @param int|list<int>        $service_ids
	 * @param array<string, mixed> $overrides
	 */
	protected function book( int|array $service_ids, string $date, string $time, array $overrides = [] ): WP_REST_Response {
		return $this->rest(
			'POST',
			'/bookings',
			$overrides + [
				'service_ids'  => (array) $service_ids,
				'date'         => $date,
				'time'         => $time,
				'name'         => 'Jan Novák',
				'phone'        => '+420 603 123 456',
				'email'        => 'jan@example.test',
				'plate'        => '1AB 2345',
				'consent_gdpr' => true,
			]
		);
	}
}
