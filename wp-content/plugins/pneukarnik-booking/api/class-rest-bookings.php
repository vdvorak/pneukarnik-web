<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * POST /wp-json/pneukarnik/v1/bookings
 *
 * Při vypnutých online rezervacích 503 booking.disabled se zprávou Provozovatele,
 * po vyčerpání limitu IP 429 booking.rate_limited (viz Pneukarnik_Rate_Limit).
 */
class Pneukarnik_Rest_Bookings {

	public function register_routes(): void {
		register_rest_route(
			PNEUKARNIK_REST_NAMESPACE,
			'/bookings',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_booking' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	public function create_booking( WP_REST_Request $request ): WP_REST_Response {
		if ( ! Pneukarnik_Booking::online_enabled() ) {
			return new WP_REST_Response(
				[
					'code'    => 'booking.disabled',
					'message' => 'booking.disabled',
					'data'    => [
						'status'           => 503,
						'disabled_message' => Pneukarnik_Booking::online_disabled_message(),
					],
				],
				503
			);
		}

		if ( Pneukarnik_Rate_Limit::exceeded( Pneukarnik_Rate_Limit::CREATE ) ) {
			return new WP_REST_Response(
				[
					'code'    => 'booking.rate_limited',
					'message' => 'booking.rate_limited',
					'data'    => [ 'status' => 429 ],
				],
				429
			);
		}

		/** @var mixed $data Tělo může být i jiná JSON hodnota než objekt (řetězec, číslo); pak chybí všechna pole. */
		$data = $request->get_json_params();
		if ( ! is_array( $data ) ) {
			$data = [];
		}

		$result = Pneukarnik_Booking::create( $data );

		if ( $result['ok'] ) {
			Pneukarnik_Rate_Limit::hit( Pneukarnik_Rate_Limit::CREATE );
		}

		if ( ! $result['ok'] ) {
			$body = [
				'code'    => $result['code'],
				'message' => $result['code'],
				'data'    => [ 'status' => $result['status'] ],
			];
			if ( isset( $result['errors'] ) ) {
				$body['data']['errors'] = $result['errors'];
			}
			if ( isset( $result['season'] ) ) {
				$body['data']['season'] = $result['season'];
			}
			return new WP_REST_Response( $body, $result['status'] );
		}

		// Bez osobních údajů: stránka potvrzení si Rezervaci najde podle tokenu.
		$booking = $result['booking'];
		return new WP_REST_Response(
			[
				'date'             => $booking['booking_date'],
				'time_start'       => $booking['time_start'],
				'time_end'         => $booking['time_end'],
				'services'         => array_column( $booking['services'], 'name' ),
				'confirmation_url' => Pneukarnik_Booking::confirmation_url( $result['confirmation_token'] ),
			],
			201
		);
	}
}
