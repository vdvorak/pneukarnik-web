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
		$all = get_transient( 'pneukarnik_services_v1' );
		if ( false === $all ) {
			$query = new WP_Query(
				[
					'post_type'      => 'pneukarnik_service',
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'orderby'        => 'meta_value_num',
					'meta_key'       => '_service_index',
					'order'          => 'ASC',
				]
			);

			$all = array_map( [ $this, 'hydrate' ], $query->posts );
			set_transient( 'pneukarnik_services_v1', $all, 5 * MINUTE_IN_SECONDS );
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
		delete_transient( 'pneukarnik_services_v1' );
	}

	public function get_service( WP_REST_Request $request ): WP_REST_Response {
		$slug = $request->get_param( 'slug' );
		$post = get_page_by_path( $slug, OBJECT, 'pneukarnik_service' );

		if ( ! $post || $post->post_status !== 'publish' ) {
			return new WP_REST_Response(
				[
					'code'    => 'booking.service_not_found',
					'message' => 'Služba nenalezena',
				],
				404
			);
		}

		return new WP_REST_Response( $this->hydrate( $post ), 200 );
	}

	private function hydrate( WP_Post $post ): array {
		$id         = $post->ID;
		$price_sale = get_post_meta( $id, '_service_price_sale', true );
		$sort_index = get_post_meta( $id, '_service_index', true );
		return [
			'id'             => $id,
			'slug'           => $post->post_name,
			'name'           => $post->post_title,
			'description'    => apply_filters( 'the_content', $post->post_content ),
			'icon'           => (string) get_post_meta( $id, '_service_icon', true ),
			'duration'       => (int) get_post_meta( $id, '_service_duration', true ),
			'price'          => (float) get_post_meta( $id, '_service_price', true ),
			'show_price'     => (bool) get_post_meta( $id, '_service_show_price', true ),
			'price_sale'     => $price_sale !== '' ? (float) $price_sale : null,
			'is_sale'        => (bool) get_post_meta( $id, '_service_is_sale', true ),
			'bookable'       => (bool) get_post_meta( $id, '_service_bookable', true ),
			'sort_index'     => $sort_index !== '' ? (int) $sort_index : null,
			'is_autoservice' => (bool) get_post_meta( $id, '_service_is_autoservice', true ),
			'is_seasonal'    => (bool) get_post_meta( $id, '_service_is_seasonal', true ),
		];
	}
}
