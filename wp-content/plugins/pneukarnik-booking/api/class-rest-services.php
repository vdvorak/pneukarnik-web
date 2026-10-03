<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GET /wp-json/pneukarnik/v1/services
 * GET /wp-json/pneukarnik/v1/services/{slug}
 */
class Pneukarnik_Rest_Services {

	public function register_routes(): void {
		register_rest_route(
			PNEUKARNIK_REST_NAMESPACE,
			'/services',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'list_services' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'bookable'      => [
						'type'    => 'boolean',
						'default' => false,
					],
					'seasonal_only' => [
						'type'    => 'boolean',
						'default' => false,
					],
				],
			]
		);

		register_rest_route(
			PNEUKARNIK_REST_NAMESPACE,
			'/services/(?P<slug>[a-z0-9-]+)',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_service' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	public function list_services( WP_REST_Request $request ): WP_REST_Response {
		$bookable_filter = $request->get_param( 'bookable' );
		$seasonal_filter = $request->get_param( 'seasonal_only' );
		$season_active   = Pneukarnik_Season::is_active();

		// Cache only the unfiltered base list; filters are applied after
		$all = get_transient( 'pneukarnik_services_v2' );
		if ( false === $all ) {
			$all = array_map( [ $this, 'hydrate' ], Pneukarnik_Service::published() );
			set_transient( 'pneukarnik_services_v2', $all, 5 * MINUTE_IN_SECONDS );
		}

		$services = [];
		foreach ( $all as $service ) {
			if ( $season_active && ! $service['is_seasonal'] ) {
				continue;
			}
			if ( $bookable_filter && ! $service['bookable'] ) {
				continue;
			}
			if ( $seasonal_filter && ! $service['is_seasonal'] ) {
				continue;
			}
			$services[] = $service;
		}

		$response = new WP_REST_Response( $services, 200 );
		$response->header( 'Cache-Control', 'public, max-age=300, stale-while-revalidate=60' );
		return $response;
	}

	public static function invalidate_cache(): void {
		delete_transient( 'pneukarnik_services_v2' );
	}

	public function get_service( WP_REST_Request $request ): WP_REST_Response {
		$slug = $request->get_param( 'slug' );
		$post = get_page_by_path( $slug, OBJECT, Pneukarnik_Service::POST_TYPE );

		if ( ! $post || $post->post_status !== 'publish' ) {
			return new WP_REST_Response(
				[
					'code'    => 'booking.service_not_found',
					'message' => 'Služba nenalezena',
				],
				404
			);
		}

		return new WP_REST_Response( $this->hydrate( Pneukarnik_Service::from_post( $post ) ), 200 );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function hydrate( Pneukarnik_Service $service ): array {
		return [
			'id'               => $service->id,
			'slug'             => $service->slug,
			'name'             => $service->title,
			'category'         => $service->category,
			'url'              => $service->url(),
			'duration'         => $service->duration,
			'price'            => $service->price,
			'price_from'       => $service->price_from,
			'price_by_vehicle' => $service->price_by_vehicle,
			'bookable'         => $service->bookable,
			'is_seasonal'      => $service->seasonal,
		];
	}
}
