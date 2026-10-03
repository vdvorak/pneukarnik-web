<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registrace Akcí ve WordPressu: typ obsahu bez vlastní stránky (zobrazuje se u Služby),
 * pole a pravidlo zveřejnění. V administraci je pod Službami.
 */
final class Pneukarnik_Promotion_Type {

	public static function init(): void {
		add_action( 'init', [ self::class, 'register' ] );
	}

	public static function register(): void {
		register_post_type(
			Pneukarnik_Promotion::POST_TYPE,
			[
				'labels'       => [
					'name'               => __( 'Akce', 'pneukarnik-booking' ),
					'singular_name'      => __( 'Akce', 'pneukarnik-booking' ),
					'add_new'            => __( 'Přidat Akci', 'pneukarnik-booking' ),
					'add_new_item'       => __( 'Přidat Akci', 'pneukarnik-booking' ),
					'edit_item'          => __( 'Upravit Akci', 'pneukarnik-booking' ),
					'new_item'           => __( 'Nová Akce', 'pneukarnik-booking' ),
					'search_items'       => __( 'Hledat Akce', 'pneukarnik-booking' ),
					'not_found'          => __( 'Žádné Akce nenalezeny', 'pneukarnik-booking' ),
					'not_found_in_trash' => __( 'Koš je prázdný', 'pneukarnik-booking' ),
					'all_items'          => __( 'Akce', 'pneukarnik-booking' ),
				],
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => 'edit.php?post_type=' . Pneukarnik_Service::POST_TYPE,
				'show_in_rest' => false, // Strukturovaná pole v klasickém formuláři, ne blokový editor.
				'supports'     => [ 'title' ],
				'rewrite'      => false,
			]
		);

		$meta = [
			'_promotion_service_id'  => 'integer',
			'_promotion_price'       => 'integer',
			'_promotion_description' => 'string',
			'_promotion_valid_from'  => 'string',
			'_promotion_valid_to'    => 'string',
		];
		foreach ( $meta as $key => $type ) {
			register_post_meta(
				Pneukarnik_Promotion::POST_TYPE,
				$key,
				[
					'single'       => true,
					'type'         => $type,
					'show_in_rest' => false,
				]
			);
		}

		Pneukarnik_Publish_Guard::register(
			Pneukarnik_Promotion::POST_TYPE,
			static fn( int $id ): array => Pneukarnik_Promotion::find( $id )?->missing_required() ?? [],
			/* translators: %s: seznam chybějících částí */
			__( 'Akce není zveřejněná, chybí: %s. Zůstává uložená jako koncept.', 'pneukarnik-booking' )
		);
	}
}
