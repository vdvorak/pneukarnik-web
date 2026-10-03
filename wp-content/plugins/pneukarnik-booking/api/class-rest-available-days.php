<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GET /wp-json/pneukarnik/v1/available-days?service_ids[]=&month=YYYY-MM
 * Dny měsíce, které mají pro Rezervaci daných Služeb alespoň jeden volný Termín.
 * Kalendář rezervačního formuláře podle nich zašedí ostatní dny.
 */
class Pneukarnik_Rest_Available_Days {

	public function register_routes(): void {
		register_rest_route(
			PNEUKARNIK_REST_NAMESPACE,
			'/available-days',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'list_days' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'service_ids' => Pneukarnik_Rest_Slots::service_ids_arg(),
					'month'       => [
						'required'          => true,
						'type'              => 'string',
						'validate_callback' => static fn( $v ): bool => is_string( $v ) && (bool) preg_match( '/^\d{4}-(0[1-9]|1[0-2])$/', $v ),
					],
				],
			]
		);
	}

	public function list_days( WP_REST_Request $request ): WP_REST_Response {
		/** @var list<int> $service_ids */
		$service_ids = $request->get_param( 'service_ids' );
		$month       = (string) $request->get_param( 'month' );

		$resolved = Pneukarnik_Booking::resolve_services( $service_ids );
		if ( ! $resolved['ok'] ) {
			return Pneukarnik_Rest_Slots::refusal( $resolved );
		}

		$response = new WP_REST_Response(
			[
				'month'       => $month,
				'service_ids' => $service_ids,
				'days'        => Pneukarnik_Slot_Engine::days_with_free_termin( $resolved['duration'], $month ),
			],
			200
		);
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}
}
