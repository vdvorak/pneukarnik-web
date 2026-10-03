<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * POST /wp-json/pneukarnik/v1/bookings/{id}/cancel  (admin or token)
 * POST /wp-json/pneukarnik/v1/cancel                (customer web form)
 */
class Pneukarnik_Rest_Cancel {

	public function register_routes(): void {
		register_rest_route(
			PNEUKARNIK_REST_NAMESPACE,
			'/bookings/(?P<id>\d+)/cancel',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'cancel_booking' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'id' => [
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		register_rest_route(
			PNEUKARNIK_REST_NAMESPACE,
			'/cancel',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'cancel_by_token' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	/**
	 * Admin or token cancel on /bookings/{id}/cancel.
	 * Admin = WP user with manage_options. Otherwise token required.
	 */
	public function cancel_booking( WP_REST_Request $request ): WP_REST_Response {
		$booking_id = (int) $request->get_param( 'id' );
		$body       = $request->get_json_params() ?: [];
		$reason     = isset( $body['reason'] ) ? sanitize_text_field( $body['reason'] ) : null;

		if ( current_user_can( 'manage_options' ) ) {
			$result = Pneukarnik_Cancellation::cancel_by_admin( $booking_id, $reason );
		} else {
			$token = $body['token'] ?? '';
			$email = $body['email'] ?? '';
			if ( ! $token || ! $email ) {
				return new WP_REST_Response(
					[
						'code'    => 'cancellation.invalid_token',
						'message' => 'Token a email jsou povinné',
						'data'    => [ 'status' => 403 ],
					],
					403
				);
			}
			$result = Pneukarnik_Cancellation::cancel_by_token( $booking_id, $email, $token );
		}

		return $this->result_response( $result );
	}

	/**
	 * Customer web form on /cancel — always requires booking_id + email + token.
	 */
	public function cancel_by_token( WP_REST_Request $request ): WP_REST_Response {
		$body = $request->get_json_params() ?: [];

		$booking_id = isset( $body['booking_id'] ) ? (int) $body['booking_id'] : 0;
		$email      = $body['email'] ?? '';
		$token      = $body['token'] ?? '';

		if ( ! $booking_id || ! $email || ! $token ) {
			return new WP_REST_Response(
				[
					'code'    => 'validation.required',
					'message' => 'booking_id, email a token jsou povinné',
					'data'    => [ 'status' => 422 ],
				],
				422
			);
		}

		$result = Pneukarnik_Cancellation::cancel_by_token( $booking_id, $email, $token );
		return $this->result_response( $result );
	}

	private function result_response( array|WP_Error $result ): WP_REST_Response {
		if ( is_wp_error( $result ) ) {
			$status = (int) ( $result->get_error_data()['status'] ?? 500 );
			return new WP_REST_Response(
				[
					'code'    => $result->get_error_code(),
					'message' => $result->get_error_message(),
					'data'    => [ 'status' => $status ],
				],
				$status
			);
		}
		if ( ! $result['ok'] ) {
			$status = $result['status'] ?? 422;
			return new WP_REST_Response(
				[
					'code'    => $result['code'],
					'message' => $result['code'],
					'data'    => [ 'status' => $status ],
				],
				$status
			);
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
			200
		);
	}
}
