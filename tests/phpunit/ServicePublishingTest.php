<?php
/**
 * Služba jde zveřejnit jen s názvem, perexem, cenou (nebo „cena dle vozu“), Délkou a Kategorií.
 */

declare(strict_types=1);

class ServicePublishingTest extends Pneukarnik_REST_Test_Case {

	public function test_complete_service_is_published(): void {
		$id = $this->publish_service( $this->complete_service() );

		$this->assertSame( 'publish', get_post_status( $id ) );
	}

	public function test_price_by_vehicle_replaces_price(): void {
		$service                                 = $this->complete_service();
		$service['meta_input']['_service_price'] = '';
		$service['meta_input']['_service_price_by_vehicle'] = '1';

		$id = $this->publish_service( $service );

		$this->assertSame( 'publish', get_post_status( $id ) );
	}

	/**
	 * @return array<string, array{callable(array<string, mixed>): array<string, mixed>}>
	 */
	public static function incomplete_services(): array {
		return [
			'bez názvu'     => [ static fn( array $s ): array => [ 'post_title' => '' ] + $s ],
			'bez perexu'    => [ static fn( array $s ): array => self::with_meta( $s, '_service_perex', '  ' ) ],
			'bez ceny'      => [ static fn( array $s ): array => self::with_meta( $s, '_service_price', '' ) ],
			'nulová cena'   => [ static fn( array $s ): array => self::with_meta( $s, '_service_price', '0' ) ],
			'bez Délky'     => [ static fn( array $s ): array => self::with_meta( $s, '_service_duration', '0' ) ],
			'bez Kategorie' => [ static fn( array $s ): array => self::with_meta( $s, '_service_category', 'jina' ) ],
		];
	}

	/**
	 * @dataProvider incomplete_services
	 * @param callable(array<string, mixed>): array<string, mixed> $make_incomplete
	 */
	public function test_incomplete_service_stays_draft( callable $make_incomplete ): void {
		$id = $this->publish_service( $make_incomplete( $this->complete_service() ) );

		$this->assertSame( 'draft', get_post_status( $id ) );
	}

	public function test_repeated_publish_of_incomplete_service_stays_draft(): void {
		$service                                 = $this->complete_service();
		$service['meta_input']['_service_perex'] = '';
		$id                                      = $this->publish_service( $service );

		wp_update_post(
			[
				'ID'          => $id,
				'post_status' => 'publish',
			]
		);

		$this->assertSame( 'draft', get_post_status( $id ) );
	}

	public function test_published_service_returns_to_draft_when_required_part_is_removed(): void {
		$id = $this->publish_service( $this->complete_service() );

		wp_update_post(
			[
				'ID'         => $id,
				'meta_input' => [ '_service_perex' => '' ],
			]
		);

		$this->assertSame( 'draft', get_post_status( $id ) );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function complete_service(): array {
		return [
			'post_type'   => 'pneukarnik_service',
			'post_status' => 'publish',
			'post_title'  => 'Přezutí pneu',
			'meta_input'  => [
				'_service_category' => 'pneuservis',
				'_service_perex'    => 'Sezónní přezutí včetně vyvážení.',
				'_service_price'    => '600',
				'_service_duration' => '60',
			],
		];
	}

	/**
	 * @param array<string, mixed> $service
	 * @return array<string, mixed>
	 */
	private static function with_meta( array $service, string $key, string $value ): array {
		$service['meta_input'][ $key ] = $value;
		return $service;
	}

	/**
	 * @param array<string, mixed> $service
	 */
	private function publish_service( array $service ): int {
		$id = wp_insert_post( $service, true );
		$this->assertIsInt( $id );
		return $id;
	}
}
