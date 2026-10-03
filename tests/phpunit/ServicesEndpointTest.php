<?php
/**
 * GET /services: zveřejněné Služby pro rezervační formulář.
 */

declare(strict_types=1);

class ServicesEndpointTest extends Pneukarnik_REST_Test_Case {

	public function test_lists_published_services_in_set_order(): void {
		$this->set_permalink_structure( '/%postname%/' );
		$oil   = $this->service(
			'Výměna oleje',
			'autoservis',
			2,
			[
				'_service_price'      => '900',
				'_service_price_from' => '1',
			]
		);
		$tyres = $this->service( 'Přezutí', 'pneuservis', 1, [ '_service_bookable' => '1' ] );
		$this->service( 'Koncept', 'pneuservis', 0, [], 'draft' );

		$response = $this->rest( 'GET', '/services' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			[
				[
					'id'               => $tyres,
					'slug'             => 'prezuti',
					'name'             => 'Přezutí',
					'category'         => 'pneuservis',
					'url'              => home_url( '/pneuservis/prezuti/' ),
					'duration'         => 60,
					'price'            => 500,
					'price_from'       => false,
					'price_by_vehicle' => false,
					'bookable'         => true,
					'is_seasonal'      => false,
				],
				[
					'id'               => $oil,
					'slug'             => 'vymena-oleje',
					'name'             => 'Výměna oleje',
					'category'         => 'autoservis',
					'url'              => home_url( '/autoservis/vymena-oleje/' ),
					'duration'         => 60,
					'price'            => 900,
					'price_from'       => true,
					'price_by_vehicle' => false,
					'bookable'         => false,
					'is_seasonal'      => false,
				],
			],
			$response->get_data()
		);
	}

	/**
	 * @param array<string, string> $meta
	 */
	private function service( string $title, string $category, int $order, array $meta = [], string $status = 'publish' ): int {
		return self::factory()->post->create(
			[
				'post_type'   => 'pneukarnik_service',
				'post_status' => $status,
				'post_title'  => $title,
				'menu_order'  => $order,
				'meta_input'  => $meta + [
					'_service_category' => $category,
					'_service_perex'    => 'Perex.',
					'_service_price'    => '500',
					'_service_duration' => '60',
				],
			]
		);
	}
}
