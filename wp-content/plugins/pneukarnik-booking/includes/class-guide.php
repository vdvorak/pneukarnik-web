<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Průvodce: trvalá informační stránka, která odpovídá na častou otázku Zákazníků (viz CONTEXT.md)
 * a končí odkazem na rezervaci vybrané Služby. Čtecí model nad CPT pneukarnik_guide,
 * šablona webu čte Průvodce jen přes tuto třídu.
 */
final class Pneukarnik_Guide {

	public const POST_TYPE = 'pneukarnik_guide';

	private function __construct(
		public readonly int $id,
		public readonly string $title,
		public readonly string $status,
		public readonly string $perex,
		/** Text Průvodce z editoru (HTML před filtry the_content). */
		public readonly string $text,
		/** Služba, na jejíž rezervaci Průvodce odkazuje, 0 = nevybraná. */
		public readonly int $service_id,
		/** Titulek pro vyhledávače od Provozovatele, prázdný = vygeneruje se z názvu. */
		public readonly string $seo_title,
		/** Popis pro vyhledávače od Provozovatele, prázdný = perex. */
		public readonly string $seo_description,
	) {}

	public static function find( int $id ): ?self {
		$post = get_post( $id );
		return $post && self::POST_TYPE === $post->post_type ? self::from_post( $post ) : null;
	}

	public static function from_post( WP_Post $post ): self {
		return new self(
			id: $post->ID,
			title: trim( $post->post_title ),
			status: $post->post_status,
			perex: trim( (string) get_post_meta( $post->ID, '_guide_perex', true ) ),
			text: $post->post_content,
			service_id: (int) get_post_meta( $post->ID, '_guide_service_id', true ),
			seo_title: trim( (string) get_post_meta( $post->ID, '_guide_seo_title', true ) ),
			seo_description: trim( (string) get_post_meta( $post->ID, '_guide_seo_description', true ) ),
		);
	}

	/**
	 * Zveřejnění Průvodci v pořadí nastaveném Provozovatelem (pořadí, pak název).
	 *
	 * @return list<self>
	 */
	public static function published(): array {
		$posts = get_posts(
			[
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => [
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				],
			]
		);
		return array_map( [ self::class, 'from_post' ], $posts );
	}

	/**
	 * Zveřejněný Průvodce ke každé Službě, která nějakého má (klíč ID Služby). Víc Průvodců u jedné
	 * Služby: první v pořadí published(). Jeden dotaz pro celou stránku karet nebo formulář.
	 *
	 * @return array<int, self>
	 */
	public static function by_service(): array {
		$guides = [];
		foreach ( self::published() as $guide ) {
			if ( $guide->service_id > 0 && ! isset( $guides[ $guide->service_id ] ) ) {
				$guides[ $guide->service_id ] = $guide;
			}
		}
		return $guides;
	}

	/**
	 * Zveřejněný Průvodce ke Službě (viz by_service()), null když žádný není.
	 */
	public static function for_service( int $service_id ): ?self {
		return self::by_service()[ $service_id ] ?? null;
	}

	/**
	 * Služba pro odkaz na rezervaci, jen když je zveřejněná. Jinak null a web nabídne obecnou rezervaci.
	 */
	public function service(): ?Pneukarnik_Service {
		$service = $this->service_id > 0 ? Pneukarnik_Service::find( $this->service_id ) : null;
		return $service && 'publish' === $service->status ? $service : null;
	}

	/**
	 * Povinné části, které Průvodci chybí ke zveřejnění.
	 *
	 * @return list<string> Názvy chybějících částí pro hlášku v administraci.
	 */
	public function missing_required(): array {
		$missing = [];
		if ( '' === $this->title ) {
			$missing[] = __( 'název', 'pneukarnik-booking' );
		}
		if ( '' === $this->perex ) {
			$missing[] = __( 'perex', 'pneukarnik-booking' );
		}
		if ( '' === trim( wp_strip_all_tags( $this->text ) ) ) {
			$missing[] = __( 'text', 'pneukarnik-booking' );
		}
		if ( $this->service_id <= 0 || ! Pneukarnik_Service::find( $this->service_id ) ) {
			$missing[] = __( 'Služba pro rezervaci', 'pneukarnik-booking' );
		}
		return $missing;
	}

	public function url(): string {
		return (string) get_permalink( $this->id );
	}
}
