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
	 * Jarní a podzimní Sezóna jako [od, do, leasing od] ve formátu MM-DD, null = Sezóna nenastavená.
	 *
	 * @param array{0:string,1:string,2?:string}|null $spring
	 * @param array{0:string,1:string,2?:string}|null $autumn
	 */
	protected function set_seasons( ?array $spring, ?array $autumn = null ): void {
		$this->assertTrue( Pneukarnik_Season::save( self::seasons( $spring, $autumn ) ) );
	}

	/**
	 * Sezóny ve tvaru pro Pneukarnik_Season::save(), parametry viz set_seasons().
	 *
	 * @param array{0:string,1:string,2?:string}|null $spring
	 * @param array{0:string,1:string,2?:string}|null $autumn
	 * @return array<string, array{from:string,to:string,leasing_from:string}>
	 */
	protected static function seasons( ?array $spring, ?array $autumn ): array {
		$season = static fn( ?array $s ): array => [
			'from'         => $s[0] ?? '',
			'to'           => $s[1] ?? '',
			'leasing_from' => $s[2] ?? '',
		];
		return [
			'spring' => $season( $spring ),
			'autumn' => $season( $autumn ),
		];
	}

	/**
	 * @param int|list<int> $service_ids Jedna Služba nebo víc Služeb jedné Rezervace.
	 */
	protected function slots( int|array $service_ids, string $date, bool $leasing = false ): WP_REST_Response {
		return $this->rest(
			'GET',
			'/slots',
			[
				'service_ids' => (array) $service_ids,
				'date'        => $date,
				'leasing'     => $leasing,
			]
		);
	}

	/**
	 * @param int|list<int> $service_ids
	 * @return list<string> Začátky nabízených volných Termínů (HH:MM).
	 */
	protected function free_starts( int|array $service_ids, string $date, bool $leasing = false ): array {
		$response = $this->slots( $service_ids, $date, $leasing );
		$this->assertSame( 200, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );
		return array_map( static fn( array $slot ): string => $slot['time_start'], $response->get_data()['slots'] );
	}

	/**
	 * @param int|list<int> $service_ids
	 * @param string        $month       YYYY-MM
	 * @return list<string> Dny měsíce s alespoň jedním volným Termínem (YYYY-MM-DD).
	 */
	protected function available_days( int|array $service_ids, string $month, bool $leasing = false ): array {
		return $this->available_days_response( $service_ids, $month, $leasing )['days'];
	}

	/**
	 * @param int|list<int> $service_ids
	 * @param string        $month       YYYY-MM
	 * @return array<string, mixed> Tělo odpovědi /available-days.
	 */
	protected function available_days_response( int|array $service_ids, string $month, bool $leasing = false ): array {
		$response = $this->rest(
			'GET',
			'/available-days',
			[
				'service_ids' => (array) $service_ids,
				'month'       => $month,
				'leasing'     => $leasing,
			]
		);
		$this->assertSame( 200, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );
		return $response->get_data();
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

	/**
	 * Vytvořená Rezervace, jak ji vidí stránka potvrzení.
	 *
	 * @return array<string, mixed>
	 */
	protected function created_booking( WP_REST_Response $response ): array {
		$this->assertSame( 201, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );
		parse_str( (string) wp_parse_url( $response->get_data()['confirmation_url'], PHP_URL_QUERY ), $query );
		$booking = Pneukarnik_Booking::find_by_confirmation_token( (string) $query['r'] );
		$this->assertNotNull( $booking );
		return $booking;
	}
}
