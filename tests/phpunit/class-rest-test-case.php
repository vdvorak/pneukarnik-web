<?php
/**
 * Základ testů pluginu: požadavky jdou přes interní REST server WordPressu,
 * „teď“ se nastavuje přes Pneukarnik_Clock.
 */

declare(strict_types=1);

abstract class Pneukarnik_REST_Test_Case extends WP_UnitTestCase {

	public function tear_down(): void {
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
	protected function create_service( int $duration_minutes, bool $seasonal = false ): int {
		return self::factory()->post->create(
			[
				'post_type'   => 'pneukarnik_service',
				'post_status' => 'publish',
				'post_title'  => 'Přezutí',
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
	 * @return list<string> Začátky nabízených volných Termínů (HH:MM).
	 */
	protected function free_starts( int $service_id, string $date ): array {
		$response = $this->rest(
			'GET',
			'/slots',
			[
				'service_id' => $service_id,
				'date'       => $date,
			]
		);
		$this->assertSame( 200, $response->get_status() );
		$slots = array_filter( $response->get_data()['slots'], static fn( array $slot ): bool => $slot['available'] );
		return array_values( array_map( static fn( array $slot ): string => $slot['time_start'], $slots ) );
	}
}
