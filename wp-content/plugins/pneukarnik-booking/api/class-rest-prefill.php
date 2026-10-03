<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GET /wp-json/pneukarnik/v1/prefill?token=
 * Kontaktní údaje z odkazu „Objednat znovu“ pro předvyplnění formuláře (Pneukarnik_Prefill).
 */
class Pneukarnik_Rest_Prefill {

	public function register_routes(): void {
		register_rest_route(
			PNEUKARNIK_REST_NAMESPACE,
			'/prefill',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'contact' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	public function contact( WP_REST_Request $request ): WP_REST_Response {
		$contact  = Pneukarnik_Prefill::contact( $request->get_param( 'token' ) );
		$response = null === $contact
			? new WP_REST_Response(
				[
					'code'    => Pneukarnik_Prefill::INVALID_TOKEN,
					'message' => Pneukarnik_Prefill::INVALID_TOKEN,
					'data'    => [ 'status' => 404 ],
				],
				404
			)
			: new WP_REST_Response( $contact, 200 );
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}
}
