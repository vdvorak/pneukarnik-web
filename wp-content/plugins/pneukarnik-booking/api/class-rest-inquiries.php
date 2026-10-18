<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * POST /wp-json/pneukarnik/v1/inquiries
 *
 * Poptávka (Pneukarnik_Inquiry). 201 bez těla, když odešla. Bez Služby s formulářem 503
 * inquiry.disabled, po vyčerpání limitu IP 429 inquiry.rate_limited (viz Pneukarnik_Rate_Limit),
 * nevalidní pole 422 inquiry.invalid_fields s chybami po polích.
 */
class Pneukarnik_Rest_Inquiries {

	public function register_routes(): void {
		register_rest_route(
			PNEUKARNIK_REST_NAMESPACE,
			'/inquiries',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_inquiry' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	public function create_inquiry( WP_REST_Request $request ): WP_REST_Response {
		if ( ! Pneukarnik_Inquiry::enabled() ) {
			return self::error( 'inquiry.disabled', 503 );
		}
		if ( Pneukarnik_Rate_Limit::exceeded( Pneukarnik_Rate_Limit::INQUIRY ) ) {
			return self::error( 'inquiry.rate_limited', 429 );
		}

		/** @var mixed $data Tělo může být i jiná JSON hodnota než objekt; pak chybí všechna pole. */
		$data = $request->get_json_params();
		if ( ! is_array( $data ) ) {
			$data = [];
		}

		$result = Pneukarnik_Inquiry::submit( $data );
		if ( ! $result['ok'] ) {
			return self::error( $result['code'], $result['status'], $result['errors'] ?? null );
		}
		if ( $result['sent'] ) {
			Pneukarnik_Rate_Limit::hit( Pneukarnik_Rate_Limit::INQUIRY );
		}
		return new WP_REST_Response( null, 201 );
	}

	/**
	 * @param array<string,string>|null $errors Chyby po polích.
	 */
	private static function error( string $code, int $status, ?array $errors = null ): WP_REST_Response {
		$data = [ 'status' => $status ];
		if ( null !== $errors ) {
			$data['errors'] = $errors;
		}
		return new WP_REST_Response(
			[
				'code'    => $code,
				'message' => $code,
				'data'    => $data,
			],
			$status
		);
	}
}
