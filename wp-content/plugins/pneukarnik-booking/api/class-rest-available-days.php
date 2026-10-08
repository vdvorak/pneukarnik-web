<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GET /wp-json/pneukarnik/v1/available-days?service_ids[]=&month=YYYY-MM&leasing=1
 * Dny měsíce, které mají pro Rezervaci daných Služeb alespoň jeden volný Termín.
 * Kalendář rezervačního formuláře podle nich zašedí ostatní dny. restrictions říkají, které
 * Sezóny (jen sezónní Služby, leasingové datum) v měsíci vyřadily jinak volné dny. first_day je
 * nejbližší volný den v celém horizontu (null = žádný), kalendář podle něj přeskočí plné měsíce.
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
					'leasing'     => Pneukarnik_Rest_Slots::leasing_arg(),
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

		$leasing   = (bool) $request->get_param( 'leasing' );
		$available = Pneukarnik_Booking::available_days( $resolved['services'], $resolved['duration'], $leasing, $month );
		$response  = new WP_REST_Response(
			[
				'month'        => $month,
				'service_ids'  => $service_ids,
				'days'         => $available['days'],
				'restrictions' => $available['restrictions'],
				'first_day'    => Pneukarnik_Booking::first_available_day( $resolved['services'], $resolved['duration'], $leasing ),
			],
			200
		);
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}
}
