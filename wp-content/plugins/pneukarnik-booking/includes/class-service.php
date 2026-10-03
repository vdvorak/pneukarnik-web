<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Služba: trvalá položka nabídky s vlastní stránkou (viz CONTEXT.md).
 * Čtecí model nad CPT pneukarnik_service, šablona webu čte Služby jen přes tuto třídu.
 */
final class Pneukarnik_Service {

	public const POST_TYPE = 'pneukarnik_service';

	public const PNEUSERVIS = 'pneuservis';
	public const AUTOSERVIS = 'autoservis';

	/**
	 * @param list<string>                        $includes
	 * @param list<string>                        $bring
	 * @param list<array{question:string,answer:string}> $faq
	 * @param list<int>                           $related_ids
	 */
	private function __construct(
		public readonly int $id,
		public readonly string $title,
		public readonly string $slug,
		public readonly string $status,
		public readonly string $category,
		public readonly string $perex,
		public readonly array $includes,
		public readonly string $process,
		public readonly string $duration_text,
		public readonly ?int $price,
		public readonly bool $price_from,
		public readonly bool $price_by_vehicle,
		public readonly string $price_note,
		public readonly array $bring,
		public readonly array $faq,
		public readonly array $related_ids,
		public readonly int $duration,
		public readonly bool $bookable,
		public readonly bool $seasonal,
		public readonly bool $featured,
		public readonly int $order,
	) {}

	/**
	 * @return array<string, string> Kategorie => název pro web.
	 */
	public static function categories(): array {
		return [
			self::PNEUSERVIS => __( 'Pneuservis', 'pneukarnik-booking' ),
			self::AUTOSERVIS => __( 'Autoservis', 'pneukarnik-booking' ),
		];
	}

	public static function find( int $id ): ?self {
		$post = get_post( $id );
		return $post && self::POST_TYPE === $post->post_type ? self::from_post( $post ) : null;
	}

	public static function from_post( WP_Post $post ): self {
		$id    = $post->ID;
		$meta  = static fn( string $key ): string => trim( (string) get_post_meta( $id, $key, true ) );
		$price = (int) $meta( '_service_price' );
		$faq   = get_post_meta( $id, '_service_faq', true );

		return new self(
			id: $id,
			title: trim( $post->post_title ),
			slug: $post->post_name,
			status: $post->post_status,
			category: $meta( '_service_category' ),
			perex: $meta( '_service_perex' ),
			includes: self::lines( $meta( '_service_includes' ) ),
			process: $meta( '_service_process' ),
			duration_text: $meta( '_service_duration_text' ),
			price: $price > 0 ? $price : null,
			price_from: '' !== $meta( '_service_price_from' ),
			price_by_vehicle: '' !== $meta( '_service_price_by_vehicle' ),
			price_note: $meta( '_service_price_note' ),
			bring: self::lines( $meta( '_service_bring' ) ),
			faq: self::faq( $faq ),
			related_ids: array_values( array_filter( array_map( 'intval', (array) get_post_meta( $id, '_service_related', true ) ) ) ),
			duration: (int) $meta( '_service_duration' ),
			bookable: '' !== $meta( '_service_bookable' ),
			seasonal: '' !== $meta( '_service_is_seasonal' ),
			featured: '' !== $meta( '_service_featured' ),
			order: $post->menu_order,
		);
	}

	/**
	 * Všechny zveřejněné Služby v pořadí nastaveném Provozovatelem.
	 *
	 * @return list<self>
	 */
	public static function published(): array {
		return self::query( [] );
	}

	/**
	 * Zveřejněné Služby Kategorie v pořadí nastaveném Provozovatelem.
	 *
	 * @return list<self>
	 */
	public static function in_category( string $category ): array {
		return self::query(
			[
				'meta_key'   => '_service_category', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => $category, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			]
		);
	}

	/**
	 * Zveřejněné související Služby v pořadí, jak je Provozovatel vybral.
	 *
	 * @return list<self>
	 */
	public function related(): array {
		if ( ! $this->related_ids ) {
			return [];
		}
		return self::query(
			[
				'post__in' => $this->related_ids,
				'orderby'  => 'post__in',
			]
		);
	}

	/**
	 * Řazení rozcestníku: pořadí, pak název.
	 *
	 * @return array<string, string>
	 */
	public static function order_by(): array {
		return [
			'menu_order' => 'ASC',
			'title'      => 'ASC',
		];
	}

	/**
	 * Povinné části, které Službě chybí ke zveřejnění.
	 *
	 * @return list<string> Názvy chybějících částí pro hlášku v administraci.
	 */
	public function missing_required(): array {
		$missing = [];
		if ( '' === $this->title ) {
			$missing[] = __( 'název', 'pneukarnik-booking' );
		}
		if ( ! isset( self::categories()[ $this->category ] ) ) {
			$missing[] = __( 'Kategorie', 'pneukarnik-booking' );
		}
		if ( '' === $this->perex ) {
			$missing[] = __( 'perex', 'pneukarnik-booking' );
		}
		if ( null === $this->price && ! $this->price_by_vehicle ) {
			$missing[] = __( 'cena nebo „cena dle vozu“', 'pneukarnik-booking' );
		}
		if ( $this->duration <= 0 ) {
			$missing[] = __( 'Délka', 'pneukarnik-booking' );
		}
		return $missing;
	}

	/**
	 * Adresa rozcestníku Kategorie, prázdný řetězec pro neznámou Kategorii.
	 */
	public static function category_url( string $category ): string {
		return isset( self::categories()[ $category ] ) ? home_url( user_trailingslashit( $category ) ) : '';
	}

	public function url(): string {
		return (string) get_permalink( $this->id );
	}

	public function category_label(): string {
		return self::categories()[ $this->category ] ?? '';
	}

	/**
	 * @param array<string, mixed> $args
	 * @return list<self>
	 */
	private static function query( array $args ): array {
		$posts = get_posts(
			$args + [
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => self::order_by(),
			]
		);
		return array_map( [ self::class, 'from_post' ], $posts );
	}

	/**
	 * @return list<string>
	 */
	private static function lines( string $text ): array {
		return array_values( array_filter( array_map( 'trim', explode( "\n", $text ) ), static fn( string $line ): bool => '' !== $line ) );
	}

	/**
	 * @return list<array{question:string,answer:string}>
	 */
	private static function faq( mixed $raw ): array {
		$faq = [];
		foreach ( is_array( $raw ) ? $raw : [] as $item ) {
			$question = trim( (string) ( $item['question'] ?? '' ) );
			$answer   = trim( (string) ( $item['answer'] ?? '' ) );
			if ( '' !== $question && '' !== $answer ) {
				$faq[] = [
					'question' => $question,
					'answer'   => $answer,
				];
			}
		}
		return $faq;
	}
}
