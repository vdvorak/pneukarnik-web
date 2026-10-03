<?php
/**
 * Části starého pluginu, které nový web nepotřebuje (pozůstatky headless pokusu),
 * nesmí být dostupné přes REST.
 */

declare(strict_types=1);

class RemovedEndpointsTest extends Pneukarnik_REST_Test_Case {

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function removed_routes(): array {
		return [
			'nastavení webu' => [ 'GET', '/settings' ],
			'setup'          => [ 'POST', '/setup' ],
			'kontakt'        => [ 'POST', '/contact' ],
			'sitemap'        => [ 'GET', '/sitemap-slugs' ],
			'import galerie' => [ 'POST', '/gallery-import' ],
		];
	}

	/**
	 * @dataProvider removed_routes
	 */
	public function test_route_is_gone( string $method, string $route ): void {
		$response = $this->rest( $method, $route );

		$this->assertSame( 404, $response->get_status() );
		$this->assertSame( 'rest_no_route', $response->get_data()['code'] );
	}

	public function test_gallery_post_type_is_gone(): void {
		$this->assertFalse( post_type_exists( 'pneukarnik_gallery' ) );
	}
}
