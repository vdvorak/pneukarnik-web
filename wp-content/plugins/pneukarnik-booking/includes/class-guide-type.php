<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registrace Průvodců ve WordPressu: typ obsahu s adresou /pruvodce/{průvodce}/, pole
 * a pravidlo zveřejnění (Pneukarnik_Publish_Guard). Text se píše v editoru, Služba a perex jsou pole.
 */
final class Pneukarnik_Guide_Type {

	public static function init(): void {
		add_action( 'init', [ self::class, 'register' ] );
	}

	public static function register(): void {
		register_post_type(
			Pneukarnik_Guide::POST_TYPE,
			[
				'labels'        => [
					'name'               => __( 'Průvodci', 'pneukarnik-booking' ),
					'singular_name'      => __( 'Průvodce', 'pneukarnik-booking' ),
					'add_new'            => __( 'Přidat Průvodce', 'pneukarnik-booking' ),
					'add_new_item'       => __( 'Přidat Průvodce', 'pneukarnik-booking' ),
					'edit_item'          => __( 'Upravit Průvodce', 'pneukarnik-booking' ),
					'new_item'           => __( 'Nový Průvodce', 'pneukarnik-booking' ),
					'view_item'          => __( 'Zobrazit Průvodce', 'pneukarnik-booking' ),
					'search_items'       => __( 'Hledat Průvodce', 'pneukarnik-booking' ),
					'not_found'          => __( 'Žádní Průvodci nenalezeni', 'pneukarnik-booking' ),
					'not_found_in_trash' => __( 'Koš je prázdný', 'pneukarnik-booking' ),
					'all_items'          => __( 'Průvodci', 'pneukarnik-booking' ),
				],
				'public'        => true,
				'show_in_rest'  => false, // Klasický formulář s poli, jako ostatní obsah pluginu.
				'supports'      => [ 'title', 'editor', 'page-attributes' ],
				'has_archive'   => false,
				'rewrite'       => [
					'slug'       => 'pruvodce',
					'with_front' => false,
				],
				'menu_position' => 27,
				'menu_icon'     => 'dashicons-book-alt',
			]
		);

		$meta = [
			'_guide_perex'           => 'string',
			'_guide_service_id'      => 'integer',
			'_guide_seo_title'       => 'string',
			'_guide_seo_description' => 'string',
		];
		foreach ( $meta as $key => $type ) {
			register_post_meta(
				Pneukarnik_Guide::POST_TYPE,
				$key,
				[
					'single'       => true,
					'type'         => $type,
					'show_in_rest' => false,
				]
			);
		}

		Pneukarnik_Publish_Guard::register(
			Pneukarnik_Guide::POST_TYPE,
			static fn( int $id ): array => Pneukarnik_Guide::find( $id )?->missing_required() ?? [],
			/* translators: %s: seznam chybějících částí */
			__( 'Průvodce není zveřejněný, chybí: %s. Zůstává uložený jako koncept.', 'pneukarnik-booking' )
		);
	}
}
