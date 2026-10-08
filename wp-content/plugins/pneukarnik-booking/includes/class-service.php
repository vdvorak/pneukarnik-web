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

	/** Kolik Služeb nejvýš ukáže Úvod (dvě řady po třech). */
	public const HOME_LIMIT = 6;

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
		public readonly bool $ask_stored_wheels,
		public readonly bool $featured,
		public readonly int $order,
		/** Název ikony ze sady icons(), prázdný = bez ikony. */
		public readonly string $icon,
		/** Titulek pro vyhledávače od Provozovatele, prázdný = vygeneruje se z názvu. */
		public readonly string $seo_title,
		/** Popis pro vyhledávače od Provozovatele, prázdný = vygeneruje se z perexu a ceny. */
		public readonly string $seo_description,
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

	/**
	 * Pevná sada ikon Služeb (Lucide, SVG v šabloně assets/icons) podle ICONSET v návrhu.
	 *
	 * @return array<string, string> název ikony => Služba, ke které se hodí (poslední je obecná náhradní).
	 */
	public static function icons(): array {
		return [
			'circle-dot'       => __( 'Přezutí pneu', 'pneukarnik-booking' ),
			'warehouse'        => __( 'Sezónní uskladnění', 'pneukarnik-booking' ),
			'shopping-cart'    => __( 'Prodej pneu', 'pneukarnik-booking' ),
			'crosshair'        => __( 'Geometrie', 'pneukarnik-booking' ),
			'clipboard-check'  => __( 'Příprava na STK', 'pneukarnik-booking' ),
			'car-front'        => __( 'Oprava čelních skel', 'pneukarnik-booking' ),
			'snowflake'        => __( 'Doplnění klimatizace', 'pneukarnik-booking' ),
			'disc-3'           => __( 'Výměna brzdových destiček', 'pneukarnik-booking' ),
			'cloud-fog'        => __( 'Výměna výfuku', 'pneukarnik-booking' ),
			'droplet'          => __( 'Výměna oleje', 'pneukarnik-booking' ),
			'move-vertical'    => __( 'Výměna tlumičů', 'pneukarnik-booking' ),
			'lightbulb'        => __( 'Výměna žárovek', 'pneukarnik-booking' ),
			'battery-charging' => __( 'Výměna autobaterie', 'pneukarnik-booking' ),
			'activity'         => __( 'Autodiagnostika', 'pneukarnik-booking' ),
			'shield-check'     => __( 'Nástřik podvozku proti korozi', 'pneukarnik-booking' ),
			'flame'            => __( 'Dekarbonizace', 'pneukarnik-booking' ),
			'wind'             => __( 'Dezinfekce vozidla ozonem', 'pneukarnik-booking' ),
			'bike'             => __( 'Oprava defektu kol na elektrokoloběžkách', 'pneukarnik-booking' ),
			'luggage'          => __( 'Servisní prohlídka před dovolenou', 'pneukarnik-booking' ),
			'wrench'           => __( 'Obecná ikona', 'pneukarnik-booking' ),
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
			ask_stored_wheels: '' !== $meta( '_service_ask_stored_wheels' ),
			featured: '' !== $meta( '_service_featured' ),
			order: $post->menu_order,
			icon: self::valid_icon( $meta( '_service_icon' ) ),
			seo_title: $meta( '_service_seo_title' ),
			seo_description: $meta( '_service_seo_description' ),
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
	 * Zveřejněné nejžádanější Služby (pro Úvod) v pořadí nastaveném Provozovatelem.
	 *
	 * @return list<self>
	 */
	public static function featured(): array {
		return self::query(
			[
				'meta_key'   => '_service_featured', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			]
		);
	}

	/**
	 * Služby pro sekci „Co pro vás uděláme“ na Úvodu, nejvýš HOME_LIMIT: Služby s platnou Akcí, pak
	 * nejžádanější, volná místa doplní další zveřejněné Služby. Uvnitř skupin pořadí z administrace.
	 *
	 * @param array<int, Pneukarnik_Promotion> $promotions Platné Akce podle Služby (Pneukarnik_Promotion::current()).
	 * @return list<self>
	 */
	public static function for_home( array $promotions ): array {
		$rank     = static fn( self $service ): int => isset( $promotions[ $service->id ] ) ? 0 : ( $service->featured ? 1 : 2 );
		$services = self::published();
		usort( $services, static fn( self $a, self $b ): int => $rank( $a ) <=> $rank( $b ) );
		return array_slice( $services, 0, self::HOME_LIMIT );
	}

	/**
	 * Služby s platnou Akcí na začátek, jinak beze změny pořadí.
	 *
	 * @param list<self>                       $services
	 * @param array<int, Pneukarnik_Promotion> $promotions Platné Akce podle Služby.
	 * @return list<self>
	 */
	public static function promoted_first( array $services, array $promotions ): array {
		$promoted = static fn( self $service ): bool => isset( $promotions[ $service->id ] );
		return [ ...array_filter( $services, $promoted ), ...array_filter( $services, static fn( self $service ): bool => ! $promoted( $service ) ) ];
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
	 * Ikona ze sady, cokoli jiného (i staré nebo ručně zapsané hodnoty) = bez ikony.
	 */
	public static function valid_icon( string $name ): string {
		return isset( self::icons()[ $name ] ) ? $name : '';
	}

	/**
	 * Adresa stránky Služby s oběma Kategoriemi.
	 */
	public static function services_url(): string {
		return home_url( user_trailingslashit( 'sluzby' ) );
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

	/**
	 * Adresa rezervačního formuláře, u online rezervovatelné Služby s ní předvybranou.
	 */
	public function booking_url(): string {
		$url = home_url( '/rezervace/' );
		return $this->bookable ? add_query_arg( 'sluzba', $this->slug, $url ) : $url;
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
