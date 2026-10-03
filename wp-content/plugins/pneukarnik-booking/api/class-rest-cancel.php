<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GET  /wp-json/pneukarnik/v1/cancellation?token=   detail Rezervace z odkazu a co odkaz udělá
 * POST /wp-json/pneukarnik/v1/cancellation {token}  Zrušení odkazem z e‑mailu
 * POST /wp-json/pneukarnik/v1/bookings/{id}/cancel  Zrušení Provozovatelem
 *
 * Kódy: cancellation.allowed, .cancelled, .too_late, .already_cancelled, .invalid_token.
 * Odpovědi bez osobních údajů Zákazníka.
 */
class Pneukarnik_Rest_Cancel {

	public function register_routes(): void {
		register_rest_route(
			PNEUKARNIK_REST_NAMESPACE,
			'/cancellation',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'preview' ],
					'permission_callback' => '__return_true',
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'cancel_by_token' ],
					'permission_callback' => '__return_true',
				],
			]
		);

		register_rest_route(
			PNEUKARNIK_REST_NAMESPACE,
			'/bookings/(?P<id>\d+)/cancel',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'cancel_by_provozovatel' ],
				'permission_callback' => static fn(): bool => current_user_can( 'manage_options' ),
				'args'                => [
					'id' => [
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					],
				],
			]
		);
	}

	public function preview( WP_REST_Request $request ): WP_REST_Response {
		$preview = Pneukarnik_Cancellation::preview( $request->get_param( 'token' ) );
		if ( null === $preview ) {
			return self::refusal( Pneukarnik_Cancellation::INVALID_TOKEN, 404 );
		}
		return self::no_store( new WP_REST_Response( $preview, 200 ) );
	}

	public function cancel_by_token( WP_REST_Request $request ): WP_REST_Response {
		/** @var mixed $body Tělo může být i jiná JSON hodnota než objekt. */
		$body = $request->get_json_params();
		return self::result( Pneukarnik_Cancellation::cancel_by_token( is_array( $body ) ? $body['token'] ?? null : null ) );
	}

	public function cancel_by_provozovatel( WP_REST_Request $request ): WP_REST_Response {
		/** @var mixed $body Tělo může být i jiná JSON hodnota než objekt. */
		$body   = $request->get_json_params();
		$reason = is_array( $body ) && is_string( $body['reason'] ?? null ) ? sanitize_text_field( $body['reason'] ) : '';
		return self::result( Pneukarnik_Cancellation::cancel_by_provozovatel( (int) $request->get_param( 'id' ), '' !== $reason ? $reason : null ) );
	}

	/**
	 * @param array{ok:true,code:string,booking:array<string,mixed>}|array{ok:false,code:string,status:int} $result
	 */
	private static function result( array $result ): WP_REST_Response {
		if ( ! $result['ok'] ) {
			return self::refusal( $result['code'], $result['status'] );
		}
		return self::no_store(
			new WP_REST_Response(
				[
					'code'    => $result['code'],
					'booking' => Pneukarnik_Cancellation::public_view( $result['booking'] ),
				],
				200
			)
		);
	}

	private static function refusal( string $code, int $status ): WP_REST_Response {
		return self::no_store(
			new WP_REST_Response(
				[
					'code'    => $code,
					'message' => $code,
					'data'    => [ 'status' => $status ],
				],
				$status
			)
		);
	}

	private static function no_store( WP_REST_Response $response ): WP_REST_Response {
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}
}
