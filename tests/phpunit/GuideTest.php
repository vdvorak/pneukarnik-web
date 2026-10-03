<?php
/**
 * Průvodci: adresa /pruvodce/{průvodce}/, povinné části ke zveřejnění, pořadí v patičce
 * a Služba, na jejíž rezervaci Průvodce odkazuje.
 */

declare(strict_types=1);

class GuideTest extends Pneukarnik_REST_Test_Case {

	public function set_up(): void {
		parent::set_up();
		$this->set_permalink_structure( '/%postname%/' );
		// WordPress přidá adresy typu obsahu jen při registraci se zapnutými pěknými adresami,
		// test suite ale typy registruje bez nich. Na webu jsou pěkné adresy zapnuté vždy.
		Pneukarnik_Guide_Type::register();
		flush_rewrite_rules( false );
	}

	public function test_published_guide_has_its_own_address(): void {
		$id = $this->guide( 'Kdy přezout', $this->create_service( 60 ) );

		$this->assertSame( home_url( '/pruvodce/kdy-prezout/' ), get_permalink( $id ) );
		$this->go_to( get_permalink( $id ) );
		$this->assertTrue( is_singular( 'pneukarnik_guide' ) );
		$this->assertSame( $id, get_queried_object_id() );
	}

	public function test_draft_guide_is_404(): void {
		$this->guide( 'Koncept', $this->create_service( 60 ), status: 'draft' );

		$this->go_to( home_url( '/pruvodce/koncept/' ) );

		$this->assertTrue( is_404() );
	}

	public function test_guide_links_to_its_service_while_the_service_is_published(): void {
		$service = $this->create_service( 60, title: 'Přezutí' );
		$guide   = Pneukarnik_Guide::find( $this->guide( 'Kdy přezout', $service ) );

		$this->assertNotNull( $guide );
		$this->assertSame( $service, $guide->service()?->id );

		wp_update_post(
			[
				'ID'          => $service,
				'post_status' => 'draft',
			]
		);

		$this->assertNull( $guide->service() );
	}

	public function test_published_lists_only_published_guides_in_set_order(): void {
		$service = $this->create_service( 60 );
		$third   = $this->guide( 'Kdy pneumatiky vyměnit', $service, order: 3 );
		$first   = $this->guide( 'Kdy přezout', $service, order: 1 );
		$second  = $this->guide( 'Uskladnění pneumatik', $service, order: 2 );
		$this->guide( 'Koncept', $service, order: 0, status: 'draft' );

		$this->assertSame( [ $first, $second, $third ], array_map( static fn( Pneukarnik_Guide $guide ): int => $guide->id, Pneukarnik_Guide::published() ) );
	}

	/**
	 * @return array<string, array{0: array<string, mixed>}>
	 */
	public static function incomplete_guides(): array {
		return [
			'bez názvu'      => [ [ 'post_title' => '' ] ],
			'bez perexu'     => [ [ '_guide_perex' => '' ] ],
			'bez textu'      => [ [ 'post_content' => '<p> </p>' ] ],
			'bez Služby'     => [ [ '_guide_service_id' => '' ] ],
			'smazaná Služba' => [ [ '_guide_service_id' => 999999 ] ],
		];
	}

	/**
	 * @dataProvider incomplete_guides
	 * @param array<string, mixed> $override Pole příspěvku (post_title, post_content) nebo meta Průvodce.
	 */
	public function test_incomplete_guide_stays_draft( array $override ): void {
		$post = $this->guide_post( 'Kdy přezout', $this->create_service( 60 ) );
		foreach ( $override as $key => $value ) {
			if ( str_starts_with( $key, '_' ) ) {
				$post['meta_input'][ $key ] = $value;
			} else {
				$post[ $key ] = $value;
			}
		}

		$id = wp_insert_post( $post, true );

		$this->assertIsInt( $id );
		$this->assertSame( 'draft', get_post_status( $id ) );
	}

	private function guide( string $title, int $service_id, int $order = 0, string $status = 'publish' ): int {
		$id = wp_insert_post( $this->guide_post( $title, $service_id, $order, $status ), true );
		$this->assertIsInt( $id );
		$this->assertSame( $status, get_post_status( $id ) );
		return $id;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function guide_post( string $title, int $service_id, int $order = 0, string $status = 'publish' ): array {
		return [
			'post_type'    => 'pneukarnik_guide',
			'post_status'  => $status,
			'post_title'   => $title,
			'post_content' => '<p>Text Průvodce.</p>',
			'menu_order'   => $order,
			'meta_input'   => [
				'_guide_perex'      => 'Perex.',
				'_guide_service_id' => $service_id,
			],
		];
	}
}
