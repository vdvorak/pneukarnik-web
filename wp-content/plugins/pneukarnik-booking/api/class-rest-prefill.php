<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GET /wp-json/pneukarnik/v1/prefill?token=
 * Údaje z odkazu „Objednat znovu“ pro předvyplnění formuláře (Pneukarnik_Prefill).
 */
class Pneukarnik_Rest_Prefill {

	public function register_routes(): void {
		register_rest_route(
			PNEUKARNIK_REST_NAMESPACE,
			'/prefill',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'details' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	public function details( WP_REST_Request $request ): WP_REST_Response {
		$details  = Pneukarnik_Prefill::details( $request->get_param( 'token' ) );
		$response = null === $details
			? new WP_REST_Response(
				[
					'code'    => Pneukarnik_Prefill::INVALID_TOKEN,
					'message' => Pneukarnik_Prefill::INVALID_TOKEN,
					'data'    => [ 'status' => 404 ],
				],
				404
			)
			: new WP_REST_Response( $details, 200 );
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}
}
