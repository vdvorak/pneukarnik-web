<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * POST /wp-json/pneukarnik/v1/bookings
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
		if ( ! (bool) get_option( 'pneukarnik_booking_enabled', '1' ) ) {
			return new WP_REST_Response(
				[
					'code'    => 'booking.disabled',
					'message' => 'Online rezervace jsou momentálně nedostupné.',
					'data'    => [ 'status' => 503 ],
				],
				503
			);
		}

		$ip_key = null;
		$count  = 0;
		if ( ! is_user_logged_in() ) {
			$ip       = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
			$ip_key   = 'pnk_rl_' . md5( $ip );
			$count    = (int) get_transient( $ip_key );
			$rl_limit = (int) get_option( 'pneukarnik_rate_limit', '10' );

			if ( $count >= $rl_limit ) {
				return new WP_REST_Response(
					[
						'code'    => 'booking.rate_limited',
						'message' => 'Příliš mnoho rezervací. Zkuste to za hodinu.',
						'data'    => [ 'status' => 429 ],
					],
					429
				);
			}
		}

		/** @var mixed $data Tělo může být i jiná JSON hodnota než objekt (řetězec, číslo). */
		$data = $request->get_json_params();
		if ( ! is_array( $data ) || [] === $data ) {
			return new WP_REST_Response(
				[
					'code'    => 'validation.required',
					'message' => 'Invalid JSON body',
					'data'    => [ 'status' => 422 ],
				],
				422
			);
		}

		$result = Pneukarnik_Booking::create( $data );

		if ( $result['ok'] && null !== $ip_key ) {
			set_transient( $ip_key, $count + 1, HOUR_IN_SECONDS );
		}

		if ( ! $result['ok'] ) {
			$status = $result['status'];
			$resp   = [
				'code'    => $result['code'],
				'message' => $result['code'],
				'data'    => [ 'status' => $status ],
			];
			if ( isset( $result['field'] ) ) {
				$resp['data']['field'] = $result['field'];
			}
			return new WP_REST_Response( $resp, $status );
		}

		// Return only non-PII fields — customer PII must not appear in public response.
		$b = $result['booking'];
		return new WP_REST_Response(
			[
				'id'           => $b['id'],
				'booking_date' => $b['booking_date'],
				'time_start'   => $b['time_start'],
				'time_end'     => $b['time_end'],
				'service_name' => $b['service_name'],
				'status'       => $b['status'],
			],
			201
		);
	}
}
