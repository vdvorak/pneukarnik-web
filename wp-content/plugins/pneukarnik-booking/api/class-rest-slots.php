<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GET /wp-json/pneukarnik/v1/slots?service_id=&date=YYYY-MM-DD
 * Volné Termíny Služby pro den.
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
						'validate_callback' => static fn( $v ): bool => is_string( $v ) && (bool) preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m ) && checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ),
					],
				],
			]
		);
	}

	public function list_slots( WP_REST_Request $request ): WP_REST_Response {
		$service_id = (int) $request->get_param( 'service_id' );
		$date       = (string) $request->get_param( 'date' );
		$service    = Pneukarnik_Service::find( $service_id );

		$refusal = Pneukarnik_Booking::service_refusal( $service );
		if ( null !== $refusal || null === $service ) {
			$refusal ??= [
				'code'   => 'booking.service_not_found',
				'status' => 404,
			];
			return new WP_REST_Response(
				[
					'code'    => $refusal['code'],
					'message' => $refusal['code'],
					'data'    => [ 'status' => $refusal['status'] ],
				],
				$refusal['status']
			);
		}

		$response = new WP_REST_Response(
			[
				'date'       => $date,
				'service_id' => $service_id,
				'slots'      => Pneukarnik_Slot_Engine::free_termins( $service->duration, $date ),
			],
			200
		);
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}
}
