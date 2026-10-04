<?php
/**
 * Pravidla přesměrování starých adres (Pneukarnik_Old_Urls). Celý seznam z docs/stare-url.md
 * projde Playwright, tady jsou případy podle obsahu webu a adresy, které zůstat musí.
 */

declare(strict_types=1);

class OldUrlsTest extends Pneukarnik_REST_Test_Case {

	public function test_decarbonisation_posts_lead_to_autoservis_until_the_service_is_published(): void {
		$service = $this->create_service( 60, false, 'Dekarbonizace' );
		wp_update_post(
			[
				'ID'          => $service,
				'post_name'   => 'dekarbonizace',
				'post_status' => 'draft',
			]
		);
		update_post_meta( $service, '_service_category', 'autoservis' );

		$this->assertSame( Pneukarnik_Service::category_url( 'autoservis' ), Pneukarnik_Old_Urls::target( '/2023/12/14/dekarbonizace-motoru/' ) );

		wp_publish_post( $service );

		$this->assertSame( Pneukarnik_Service::find( $service )?->url(), Pneukarnik_Old_Urls::target( '/2023/12/14/dekarbonizace-motoru/' ) );
		$this->assertSame( home_url( '/' ), Pneukarnik_Old_Urls::target( '/2021/01/08/black-friday/' ) );
	}

	public function test_old_service_link_finds_the_published_service_with_the_same_slug(): void {
		$service = $this->create_service( 60, false, 'Přezutí' );
		wp_update_post(
			[
				'ID'        => $service,
				'post_name' => 'prezuti-pneu',
			]
		);

		$this->assertSame( Pneukarnik_Service::find( $service )?->url(), Pneukarnik_Old_Urls::target( '/service/prezuti-pneu/' ) );
		$this->assertSame( home_url( '/' ), Pneukarnik_Old_Urls::target( '/service/neexistuje/' ) );
	}

	public function test_old_service_link_finds_the_service_converted_from_it_when_the_slug_differs(): void {
		// Starý web: Služba „Dezinfekce vozidla ozonem“ se slugem 780, nová Služba má slug dezinfekce-ozonem.
		$old     = self::factory()->post->create(
			[
				'post_type'  => 'service',
				'post_title' => 'Dezinfekce vozidla ozonem',
				'post_name'  => '780',
			]
		);
		$service = $this->create_service( 60, false, 'Dezinfekce vozidla ozonem' );
		update_post_meta( $service, Pneukarnik_Legacy_Import::SERVICE_META, $old );

		$this->assertSame( Pneukarnik_Service::find( $service )?->url(), Pneukarnik_Old_Urls::target( '/service/780/' ) );

		wp_update_post(
			[
				'ID'          => $service,
				'post_status' => 'draft',
			]
		);

		$this->assertSame( home_url( '/' ), Pneukarnik_Old_Urls::target( '/service/780/' ), 'nezveřejněná Služba' );
	}

	/**
	 * @return array<string, array{0:string}>
	 */
	public static function new_paths(): array {
		return [
			'Úvod'            => [ '/' ],
			'Kategorie'       => [ '/pneuservis/' ],
			'detail Služby'   => [ '/autoservis/dekarbonizace/' ],
			'rezervace'       => [ '/rezervace/' ],
			'Zrušení'         => [ '/rezervace/zruseni/' ],
			'O nás'           => [ '/o-nas/' ],
			'Průvodce'        => [ '/pruvodce/kdy-prezout/' ],
			'odhlášení'       => [ '/odhlaseni/' ],
			'sitemapa'        => [ '/wp-sitemap.xml' ],
			'administrace'    => [ '/wp-admin/' ],
			'podobná stránka' => [ '/servis-2023/' ],
		];
	}

	/**
	 * @dataProvider new_paths
	 */
	public function test_new_addresses_are_left_alone( string $path ): void {
		$this->assertNull( Pneukarnik_Old_Urls::target( $path ) );
	}
}
