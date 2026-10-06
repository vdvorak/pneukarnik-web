<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Akce: časově omezená nabídka ke Službě s akční cenou a platností od–do (viz CONTEXT.md).
 * Čtecí model nad CPT pneukarnik_promotion, šablona webu čte Akce jen přes tuto třídu.
 *
 * Platnost viz Pneukarnik_Validity. Mimo platnost, bez zveřejnění nebo u nezveřejněné Služby se nikde nezobrazí.
 */
final class Pneukarnik_Promotion {

	public const POST_TYPE = 'pneukarnik_promotion';

	private function __construct(
		public readonly int $id,
		public readonly string $title,
		public readonly string $status,
		public readonly int $service_id,
		public readonly ?int $price,
		public readonly string $description,
		/** YYYY-MM-DD, prázdné = nevyplněno. */
		public readonly string $valid_from,
		/** YYYY-MM-DD, prázdné = nevyplněno. */
		public readonly string $valid_to,
	) {}

	public static function find( int $id ): ?self {
		$post = get_post( $id );
		return $post && self::POST_TYPE === $post->post_type ? self::from_post( $post ) : null;
	}

	public static function from_post( WP_Post $post ): self {
		$id    = $post->ID;
		$meta  = static fn( string $key ): string => trim( (string) get_post_meta( $id, $key, true ) );
		$price = (int) $meta( '_promotion_price' );

		return new self(
			id: $id,
			title: trim( $post->post_title ),
			status: $post->post_status,
			service_id: (int) $meta( '_promotion_service_id' ),
			price: $price > 0 ? $price : null,
			description: $meta( '_promotion_description' ),
			valid_from: Pneukarnik_Validity::date( $meta( '_promotion_valid_from' ) ),
			valid_to: Pneukarnik_Validity::date( $meta( '_promotion_valid_to' ) ),
		);
	}

	/**
	 * Akce, které právě platí, nejvýš jedna na zveřejněnou Službu.
	 * Když jich Služba má souběžně víc, platí ta s nejbližším koncem platnosti.
	 *
	 * @return array<int, self> ID Služby => Akce
	 */
	public static function current(): array {
		$posts      = get_posts(
			[
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_query'     => Pneukarnik_Validity::current_meta_query( '_promotion_valid_from', '_promotion_valid_to' ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Akcí je pár.
			]
		);
		$promotions = array_map( [ self::class, 'from_post' ], $posts );
		usort( $promotions, static fn( self $a, self $b ): int => [ $a->valid_to, $a->id ] <=> [ $b->valid_to, $b->id ] );

		$current = [];
		foreach ( $promotions as $promotion ) {
			if ( isset( $current[ $promotion->service_id ] ) ) {
				continue;
			}
			if ( 'publish' === $promotion->service()?->status ) {
				$current[ $promotion->service_id ] = $promotion;
			}
		}
		return $current;
	}

	/**
	 * Akce, která u Služby právě platí (viz current()).
	 */
	public static function current_for( int $service_id ): ?self {
		return self::current()[ $service_id ] ?? null;
	}

	/**
	 * Služba, ke které Akce patří (v jakémkoli stavu), null když neexistuje.
	 */
	public function service(): ?Pneukarnik_Service {
		return $this->service_id > 0 ? Pneukarnik_Service::find( $this->service_id ) : null;
	}

	/**
	 * Povinné části, které Akci chybí ke zveřejnění.
	 *
	 * @return list<string> Názvy chybějících částí pro hlášku v administraci.
	 */
	public function missing_required(): array {
		$missing = [];
		if ( '' === $this->title ) {
			$missing[] = __( 'název', 'pneukarnik-booking' );
		}
		if ( ! $this->service() ) {
			$missing[] = __( 'Služba', 'pneukarnik-booking' );
		}
		return array_merge( $missing, Pneukarnik_Validity::missing( $this->valid_from, $this->valid_to ) );
	}
}
