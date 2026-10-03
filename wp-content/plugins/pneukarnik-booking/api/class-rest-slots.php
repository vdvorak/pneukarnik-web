<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GET /wp-json/pneukarnik/v1/slots?service_ids[]=&service_ids[]=&date=YYYY-MM-DD&leasing=1
 * Volné Termíny dne pro Rezervaci jedné nebo víc Služeb (úsek = součet Délek).
 * Den, který Sezóna nebo leasingové datum online nedovolí, vrátí 422 s kódem a Sezónou.
 */
class Pneukarnik_Rest_Slots {

	public function register_routes(): void {
		register_rest_route(
			PNEUKARNIK_REST_NAMESPACE,
			'/slots',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'list_slots' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'service_ids' => self::service_ids_arg(),
					'leasing'     => self::leasing_arg(),
					'date'        => [
						'required'          => true,
						'type'              => 'string',
						'validate_callback' => static fn( $v ): bool => is_string( $v ) && (bool) preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m ) && checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ),
					],
				],
			]
		);
	}

	public function list_slots( WP_REST_Request $request ): WP_REST_Response {
		/** @var list<int> $service_ids */
		$service_ids = $request->get_param( 'service_ids' );
		$date        = (string) $request->get_param( 'date' );

		$resolved = Pneukarnik_Booking::resolve_services( $service_ids );
		if ( ! $resolved['ok'] ) {
			return self::refusal( $resolved );
		}
		$refusal = Pneukarnik_Booking::day_refusal( $resolved['services'], (bool) $request->get_param( 'leasing' ), $date );
		if ( null !== $refusal ) {
			return self::refusal( $refusal );
		}

		$response = new WP_REST_Response(
			[
				'date'        => $date,
				'service_ids' => $service_ids,
				'slots'       => Pneukarnik_Slot_Engine::free_termins( $resolved['duration'], $date ),
			],
			200
		);
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}

	/**
	 * Parametr service_ids: 1..n různých Služeb jedné Rezervace.
	 *
	 * @return array<string,mixed>
	 */
	public static function service_ids_arg(): array {
		return [
			'required'    => true,
			'type'        => 'array',
			'items'       => [ 'type' => 'integer' ],
			'minItems'    => 1,
			'maxItems'    => Pneukarnik_Booking::MAX_SERVICES,
			'uniqueItems' => true,
		];
	}

	/**
	 * Parametr leasing: Termíny pro Leasingového zákazníka.
	 *
	 * @return array<string,mixed>
	 */
	public static function leasing_arg(): array {
		return [
			'type'    => 'boolean',
			'default' => false,
		];
	}

	/**
	 * @param array{code:string,status:int,season?:array<string,mixed>} $refusal Proč Služby nejde rezervovat online.
	 */
	public static function refusal( array $refusal ): WP_REST_Response {
		$data = [ 'status' => $refusal['status'] ];
		if ( isset( $refusal['season'] ) ) {
			$data['season'] = $refusal['season'];
		}
		return new WP_REST_Response(
			[
				'code'    => $refusal['code'],
				'message' => $refusal['code'],
				'data'    => $data,
			],
			$refusal['status']
		);
	}
}
