<?php
/**
 * Vytvoření Rezervace: tělo požadavku, které není JSON objekt, je chyba validace, ne pád serveru.
 */

declare(strict_types=1);

class CreateBookingRequestTest extends Pneukarnik_REST_Test_Case {

	/**
	 * @return array<string, array{string}>
	 */
	public static function non_object_bodies(): array {
		return [
			'řetězec' => [ '"x"' ],
			'číslo'   => [ '1' ],
			'prázdné' => [ '' ],
			'pole'    => [ '[]' ],
		];
	}

	/**
	 * @dataProvider non_object_bodies
	 */
	public function test_body_that_is_not_an_object_is_rejected( string $body ): void {
		$request = new WP_REST_Request( 'POST', '/pneukarnik/v1/bookings' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( $body );

		$response = rest_do_request( $request );

		$this->assertSame( 422, $response->get_status() );
		$this->assertSame( 'validation.required', $response->get_data()['code'] );
	}
}
