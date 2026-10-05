<?php
/**
 * Adresy Služeb: stránka Služby /sluzby/, rozcestník Kategorie a detail /{kategorie}/{služba}/.
 */

declare(strict_types=1);

class ServiceRoutingTest extends Pneukarnik_REST_Test_Case {

	public function set_up(): void {
		parent::set_up();
		$this->set_permalink_structure( '/%postname%/' );
	}

	public function test_category_lists_its_published_services_in_set_order(): void {
		$second = $this->service( 'Geometrie', 'pneuservis', 2 );
		$first  = $this->service( 'Přezutí', 'pneuservis', 1 );
		$this->service( 'Výměna oleje', 'autoservis', 0 );
		$this->service( 'Koncept', 'pneuservis', 0, 'draft' );

		$this->go_to( home_url( '/pneuservis/' ) );

		$this->assertFalse( is_404() );
		$this->assertSame( [ $first, $second ], wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' ) );
	}

	public function test_services_page_lists_published_services_of_both_categories_in_set_order(): void {
		$tyres = $this->service( 'Přezutí', 'pneuservis', 2 );
		$oil   = $this->service( 'Výměna oleje', 'autoservis', 1 );
		$this->service( 'Koncept', 'pneuservis', 0, 'draft' );
		$this->service( 'Soukromá', 'autoservis', 0, 'private' );
		$this->log_in_as( 'administrator' ); // Ani přihlášený Provozovatel nevidí nezveřejněné.

		$this->go_to( home_url( '/sluzby/' ) );

		$this->assertFalse( is_404() );
		$this->assertTrue( is_post_type_archive( 'pneukarnik_service' ) );
		$this->assertNull( Pneukarnik_Service_Type::shown_category() );
		$this->assertSame( 'Služby', post_type_archive_title( '', false ) );
		$this->assertSame( [ $oil, $tyres ], wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' ) );
	}

	public function test_category_is_the_services_page_with_one_category(): void {
		$this->go_to( home_url( '/autoservis/' ) );

		$this->assertTrue( is_post_type_archive( 'pneukarnik_service' ) );
		$this->assertSame( 'autoservis', Pneukarnik_Service_Type::shown_category() );
		$this->assertSame( home_url( '/sluzby/' ), get_post_type_archive_link( 'pneukarnik_service' ) );
	}

	public function test_unknown_category_is_404(): void {
		$this->go_to( home_url( '/?post_type=pneukarnik_service&pnk_kategorie=pneu' ) );

		$this->assertTrue( is_404() );
	}

	public function test_service_permalink_contains_its_category(): void {
		$id = $this->service( 'Výměna oleje', 'autoservis', 0 );

		$this->assertSame( home_url( '/autoservis/vymena-oleje/' ), get_permalink( $id ) );
	}

	public function test_service_detail_is_found_under_its_category(): void {
		$id = $this->service( 'Výměna oleje', 'autoservis', 0 );

		$this->go_to( get_permalink( $id ) );

		$this->assertTrue( is_singular( 'pneukarnik_service' ) );
		$this->assertSame( $id, get_queried_object_id() );
	}

	public function test_service_under_other_category_is_404(): void {
		$this->service( 'Výměna oleje', 'autoservis', 0 );

		$this->go_to( home_url( '/pneuservis/vymena-oleje/' ) );

		$this->assertTrue( is_404() );
	}

	public function test_unknown_service_is_404(): void {
		$this->go_to( home_url( '/pneuservis/neexistuje/' ) );

		$this->assertTrue( is_404() );
	}

	public function test_draft_service_is_404(): void {
		$this->service( 'Koncept', 'pneuservis', 0, 'draft' );

		$this->go_to( home_url( '/pneuservis/koncept/' ) );

		$this->assertTrue( is_404() );
	}

	private function service( string $title, string $category, int $order, string $status = 'publish' ): int {
		return self::factory()->post->create(
			[
				'post_type'   => 'pneukarnik_service',
				'post_status' => $status,
				'post_title'  => $title,
				'menu_order'  => $order,
				'meta_input'  => [
					'_service_category' => $category,
					'_service_perex'    => 'Perex.',
					'_service_price'    => '500',
					'_service_duration' => '60',
				],
			]
		);
	}
}
