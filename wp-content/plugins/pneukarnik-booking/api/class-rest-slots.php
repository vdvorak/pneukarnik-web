<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GET /wp-json/pneukarnik/v1/slots?service_id=&date=YYYY-MM-DD
 */
class Pneukarnik_Rest_Slots {

	public function register_routes(): void {
		register_rest_route(
			PNEUKARNIK_REST_NAMESPACE,
			'/slots',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'list_slots' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'service_id' => [
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					],
					'date'       => [
						'required'          => true,
						'type'              => 'string',
						'validate_callback' => static fn( $v ) => (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ),
					],
				],
			]
		);
	}

	public function list_slots( WP_REST_Request $request ): WP_REST_Response {
		$service_id = (int) $request->get_param( 'service_id' );
		$date       = $request->get_param( 'date' );

		$post = get_post( $service_id );
		if ( ! $post || $post->post_type !== 'pneukarnik_service' || $post->post_status !== 'publish' ) {
			return new WP_REST_Response(
				[
					'code'    => 'booking.service_not_found',
					'message' => 'Služba nenalezena',
					'data'    => [ 'status' => 404 ],
				],
				404
			);
		}

		$cache_key = 'pnk_slots_' . $service_id . '_' . $date;
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return new WP_REST_Response( $cached, 200 );
		}

		$slots = Pneukarnik_Slot_Engine::get_slots( $service_id, $date );

		$data = [
			'date'       => $date,
			'service_id' => $service_id,
			'slots'      => $slots,
		];

		set_transient( $cache_key, $data, MINUTE_IN_SECONDS );

		return new WP_REST_Response( $data, 200 );
	}

	public static function invalidate_for_service_date( int $service_id, string $date ): void {
		delete_transient( 'pnk_slots_' . $service_id . '_' . $date );
	}
}
