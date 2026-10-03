<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Převod dat ze starého webu (Pneukarnik_Legacy_Import).
 *
 * POST /admin/legacy-import {send_cancel_links?: bool}   převede a vrátí report
 *
 * Kódy: legacy_import.no_source (404, stará tabulka v databázi není).
 * Jen správce webu (manage_options): převod zakládá i Služby.
 */
class Pneukarnik_Rest_Legacy_Import {

	public function register_routes(): void {
		register_rest_route(
			PNEUKARNIK_REST_NAMESPACE,
			'/admin/legacy-import',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'run' ],
				'permission_callback' => static fn(): bool => current_user_can( 'manage_options' ),
			]
		);
	}

	public function run( WP_REST_Request $request ): WP_REST_Response {
		/** @var mixed $body Tělo může být i jiná JSON hodnota než objekt. */
		$body   = $request->get_json_params();
		$send   = is_array( $body ) && true === ( $body['send_cancel_links'] ?? false );
		$result = Pneukarnik_Legacy_Import::run( $send );

		$response = $result['ok']
			? new WP_REST_Response( $result['report'], 200 )
			: new WP_REST_Response(
				[
					'code'    => $result['code'],
					'message' => $result['code'],
					'data'    => [ 'status' => $result['status'] ],
				],
				$result['status']
			);
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}
}
