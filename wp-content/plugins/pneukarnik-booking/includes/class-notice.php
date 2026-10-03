<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Oznámení: krátké provozní sdělení s platností od–do (viz CONTEXT.md).
 * Čtecí model nad CPT pneukarnik_notice, šablona webu čte Oznámení jen přes tuto třídu.
 *
 * Platnost viz Pneukarnik_Validity. Mimo platnost nebo bez zveřejnění se nikde nezobrazí.
 */
final class Pneukarnik_Notice {

	public const POST_TYPE = 'pneukarnik_notice';

	private function __construct(
		public readonly int $id,
		public readonly string $title,
		public readonly string $status,
		public readonly string $text,
		/** YYYY-MM-DD, prázdné = nevyplněno. */
		public readonly string $valid_from,
		/** YYYY-MM-DD, prázdné = nevyplněno. */
		public readonly string $valid_to,
		/** Zobrazit i u rezervačního formuláře. */
		public readonly bool $at_booking,
	) {}

	public static function find( int $id ): ?self {
		$post = get_post( $id );
		return $post && self::POST_TYPE === $post->post_type ? self::from_post( $post ) : null;
	}

	public static function from_post( WP_Post $post ): self {
		$id   = $post->ID;
		$meta = static fn( string $key ): string => trim( (string) get_post_meta( $id, $key, true ) );

		return new self(
			id: $id,
			title: trim( $post->post_title ),
			status: $post->post_status,
			text: $meta( '_notice_text' ),
			valid_from: Pneukarnik_Validity::date( $meta( '_notice_valid_from' ) ),
			valid_to: Pneukarnik_Validity::date( $meta( '_notice_valid_to' ) ),
			at_booking: '' !== $meta( '_notice_at_booking' ),
		);
	}

	/**
	 * Oznámení, která právě platí, od nejnovějšího podle začátku platnosti.
	 *
	 * @return list<self>
	 */
	public static function current(): array {
		$posts   = get_posts(
			[
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_query'     => Pneukarnik_Validity::current_meta_query( '_notice_valid_from', '_notice_valid_to' ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Oznámení je pár.
			]
		);
		$notices = array_map( [ self::class, 'from_post' ], $posts );
		usort( $notices, static fn( self $a, self $b ): int => [ $b->valid_from, $b->id ] <=> [ $a->valid_from, $a->id ] );
		return $notices;
	}

	/**
	 * Oznámení nahoře na webu: nejnovější platné.
	 */
	public static function top(): ?self {
		return self::current()[0] ?? null;
	}

	/**
	 * Platná Oznámení označená „zobrazit i u rezervace“, od nejnovějšího.
	 *
	 * @return list<self>
	 */
	public static function at_booking(): array {
		return array_values( array_filter( self::current(), static fn( self $notice ): bool => $notice->at_booking ) );
	}

	/**
	 * Povinné části, které Oznámení chybí ke zveřejnění.
	 *
	 * @return list<string> Názvy chybějících částí pro hlášku v administraci.
	 */
	public function missing_required(): array {
		$missing = [];
		if ( '' === $this->title ) {
			$missing[] = __( 'nadpis', 'pneukarnik-booking' );
		}
		if ( '' === $this->text ) {
			$missing[] = __( 'text', 'pneukarnik-booking' );
		}
		return array_merge( $missing, Pneukarnik_Validity::missing( $this->valid_from, $this->valid_to ) );
	}
}
