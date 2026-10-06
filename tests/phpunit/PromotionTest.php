<?php
/**
 * Akce: zobrazení jen v platnosti (oba dny včetně, podle hodin pluginu), souběžné Akce,
 * skrytí s nezveřejněnou Službou, akční cena nebo název Akce ve výběru Služby v rezervaci a pravidlo
 * zveřejnění (cena nepovinná).
 */

declare(strict_types=1);

class PromotionTest extends Pneukarnik_REST_Test_Case {

	private int $service;

	public function set_up(): void {
		parent::set_up();
		$this->service = $this->create_service( 60, false, 'Dekarbonizace' );
	}

	/**
	 * @return array<string, array{string, bool}>
	 */
	public static function moments(): array {
		return [
			'den před začátkem'        => [ '2027-02-28 23:59', false ],
			'první den ráno'           => [ '2027-03-01 00:00', true ],
			'uprostřed'                => [ '2027-03-15 12:00', true ],
			'poslední den večer'       => [ '2027-03-31 23:59', true ],
			'den po konci'             => [ '2027-04-01 00:00', false ],
			'stejný den o rok později' => [ '2028-03-15 12:00', false ],
		];
	}

	/**
	 * @dataProvider moments
	 */
	public function test_promotion_is_current_only_within_validity( string $now, bool $current ): void {
		$id = $this->promotion( '2027-03-01', '2027-03-31' );

		Pneukarnik_Clock::freeze( $now );

		$this->assertSame( $current ? $id : null, Pneukarnik_Promotion::current_for( $this->service )?->id );
	}

	public function test_promotion_carries_price_description_and_validity(): void {
		$this->promotion( '2027-03-01', '2027-03-31', 'Zaváděcí cena', 990, 'Jen pro osobní auta.' );
		Pneukarnik_Clock::freeze( '2027-03-10 10:00' );

		$promotion = Pneukarnik_Promotion::current_for( $this->service );

		$this->assertNotNull( $promotion );
		$this->assertSame( 'Zaváděcí cena', $promotion->title );
		$this->assertSame( 990, $promotion->price );
		$this->assertSame( 'Jen pro osobní auta.', $promotion->description );
		$this->assertSame( '2027-03-31', $promotion->valid_to );
	}

	public function test_of_concurrent_promotions_the_one_ending_soonest_is_shown(): void {
		$this->promotion( '2027-03-01', '2027-04-30' );
		$soonest = $this->promotion( '2027-03-10', '2027-03-20' );
		$this->promotion( '2027-03-01', '2027-03-31' );
		$this->promotion( '2027-03-01', '2027-03-12', 'Skončila' );

		Pneukarnik_Clock::freeze( '2027-03-15 10:00' );

		$this->assertSame( $soonest, Pneukarnik_Promotion::current_for( $this->service )?->id );
	}

	public function test_after_the_soonest_ends_the_next_concurrent_promotion_is_shown(): void {
		$this->promotion( '2027-03-10', '2027-03-20' );
		$later = $this->promotion( '2027-03-01', '2027-03-31' );

		Pneukarnik_Clock::freeze( '2027-03-21 00:00' );

		$this->assertSame( $later, Pneukarnik_Promotion::current_for( $this->service )?->id );
	}

	public function test_current_promotions_are_one_per_service(): void {
		$other     = $this->create_service( 30, false, 'Geometrie' );
		$first     = $this->promotion( '2027-03-01', '2027-03-31' );
		$other_one = $this->promotion( '2027-03-01', '2027-03-31', 'Geometrie v akci', 500, '', $other );
		Pneukarnik_Clock::freeze( '2027-03-15 10:00' );

		$current = Pneukarnik_Promotion::current();

		$this->assertSame(
			[
				$this->service => $first,
				$other         => $other_one,
			],
			array_map( static fn( Pneukarnik_Promotion $p ): int => $p->id, $current )
		);
	}

	public function test_booking_form_offers_service_with_current_promotion_price(): void {
		require_once dirname( __DIR__, 2 ) . '/wp-content/themes/pneukarnik/inc/template-tags.php';
		$this->promotion( '2027-03-01', '2027-03-31' );
		Pneukarnik_Clock::freeze( '2027-03-15 10:00' );
		$service   = Pneukarnik_Service::find( $this->service );
		$promotion = Pneukarnik_Promotion::current_for( $this->service );
		$this->assertNotNull( $service );

		$this->assertSame( "Dekarbonizace (akce 990\u{00A0}Kč, běžně 600\u{00A0}Kč)", pneukarnik_service_option_label( $service, $promotion ) );
		$this->assertSame( "Dekarbonizace (600\u{00A0}Kč)", pneukarnik_service_option_label( $service, null ) );

		update_post_meta( (int) $promotion?->id, '_promotion_price', '' );
		$free = Pneukarnik_Promotion::current_for( $this->service );
		$this->assertSame( "Dekarbonizace (600\u{00A0}Kč, akce: Zaváděcí cena)", pneukarnik_service_option_label( $service, $free ) );

		update_post_meta( $this->service, '_service_price_by_vehicle', '1' );
		$by_vehicle = Pneukarnik_Service::find( $this->service );
		$this->assertNotNull( $by_vehicle );
		$this->assertSame( "Dekarbonizace (akce 990\u{00A0}Kč)", pneukarnik_service_option_label( $by_vehicle, $promotion ) );
	}

	/**
	 * @return array<string, array{callable(int): mixed}>
	 */
	public static function hidden_services(): array {
		return [
			'koncept'          => [
				static fn( int $id ): mixed => wp_update_post(
					[
						'ID'          => $id,
						'post_status' => 'draft',
					]
				),
			],
			'v koši'           => [ static fn( int $id ): mixed => wp_trash_post( $id ) ],
			'smazaná natrvalo' => [ static fn( int $id ): mixed => wp_delete_post( $id, true ) ],
		];
	}

	/**
	 * @dataProvider hidden_services
	 * @param callable(int): mixed $hide
	 */
	public function test_promotion_of_unpublished_or_deleted_service_is_hidden( callable $hide ): void {
		$this->promotion( '2027-03-01', '2027-03-31' );
		Pneukarnik_Clock::freeze( '2027-03-15 10:00' );

		$hide( $this->service );

		$this->assertNull( Pneukarnik_Promotion::current_for( $this->service ) );
		$this->assertSame( [], Pneukarnik_Promotion::current() );
	}

	public function test_draft_promotion_is_hidden(): void {
		$this->promotion( '2027-03-01', '2027-03-31', status: 'draft' );
		Pneukarnik_Clock::freeze( '2027-03-15 10:00' );

		$this->assertNull( Pneukarnik_Promotion::current_for( $this->service ) );
	}

	public function test_complete_promotion_is_published(): void {
		$id = $this->promotion( '2027-03-01', '2027-03-31' );

		$this->assertSame( 'publish', get_post_status( $id ) );
	}

	public function test_promotion_without_price_is_published_and_has_no_price(): void {
		$this->promotion( '2027-03-01', '2027-03-31', 'Kontrola brzd zdarma', 0 );
		Pneukarnik_Clock::freeze( '2027-03-15 10:00' );

		$promotion = Pneukarnik_Promotion::current_for( $this->service );

		$this->assertNotNull( $promotion );
		$this->assertNull( $promotion->price );
	}

	/**
	 * @return array<string, array{array<string, mixed>}>
	 */
	public static function incomplete_promotions(): array {
		return [
			'bez názvu'           => [ [ 'post_title' => '' ] ],
			'bez Služby'          => [ [ '_promotion_service_id' => '' ] ],
			'neexistující Služba' => [ [ '_promotion_service_id' => '999999' ] ],
			'bez začátku'         => [ [ '_promotion_valid_from' => '' ] ],
			'bez konce'           => [ [ '_promotion_valid_to' => '' ] ],
			'neplatné datum'      => [ [ '_promotion_valid_to' => '2027-02-30' ] ],
			'konec před začátkem' => [ [ '_promotion_valid_to' => '2027-02-28' ] ],
		];
	}

	/**
	 * @dataProvider incomplete_promotions
	 * @param array<string, mixed> $override Pole příspěvku (post_title) nebo meta Akce.
	 */
	public function test_incomplete_promotion_stays_draft( array $override ): void {
		$post = $this->promotion_post( '2027-03-01', '2027-03-31' );
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

	public function test_one_day_promotion_is_published_and_shown_that_day(): void {
		$id = $this->promotion( '2027-03-01', '2027-03-01' );
		Pneukarnik_Clock::freeze( '2027-03-01 18:00' );

		$this->assertSame( 'publish', get_post_status( $id ) );
		$this->assertSame( $id, Pneukarnik_Promotion::current_for( $this->service )?->id );
	}

	private function promotion( string $from, string $to, string $title = 'Zaváděcí cena', int $price = 990, string $description = 'Popis.', ?int $service = null, string $status = 'publish' ): int {
		$id = wp_insert_post( $this->promotion_post( $from, $to, $title, $price, $description, $service, $status ), true );
		$this->assertIsInt( $id );
		$this->assertSame( $status, get_post_status( $id ) );
		return $id;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function promotion_post( string $from, string $to, string $title = 'Zaváděcí cena', int $price = 990, string $description = 'Popis.', ?int $service = null, string $status = 'publish' ): array {
		return [
			'post_type'   => 'pneukarnik_promotion',
			'post_status' => $status,
			'post_title'  => $title,
			'meta_input'  => [
				'_promotion_service_id'  => (string) ( $service ?? $this->service ),
				'_promotion_price'       => (string) $price,
				'_promotion_description' => $description,
				'_promotion_valid_from'  => $from,
				'_promotion_valid_to'    => $to,
			],
		];
	}
}
