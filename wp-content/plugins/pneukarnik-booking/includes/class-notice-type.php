<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registrace Oznámení ve WordPressu: typ obsahu bez vlastní stránky (zobrazuje se nahoře na webu
 * a u rezervace), pole a pravidlo zveřejnění.
 */
final class Pneukarnik_Notice_Type {

	public static function init(): void {
		add_action( 'init', [ self::class, 'register' ] );
	}

	public static function register(): void {
		register_post_type(
			Pneukarnik_Notice::POST_TYPE,
			[
				'labels'        => [
					'name'               => __( 'Oznámení', 'pneukarnik-booking' ),
					'singular_name'      => __( 'Oznámení', 'pneukarnik-booking' ),
					'add_new'            => __( 'Přidat Oznámení', 'pneukarnik-booking' ),
					'add_new_item'       => __( 'Přidat Oznámení', 'pneukarnik-booking' ),
					'edit_item'          => __( 'Upravit Oznámení', 'pneukarnik-booking' ),
					'new_item'           => __( 'Nové Oznámení', 'pneukarnik-booking' ),
					'search_items'       => __( 'Hledat Oznámení', 'pneukarnik-booking' ),
					'not_found'          => __( 'Žádná Oznámení nenalezena', 'pneukarnik-booking' ),
					'not_found_in_trash' => __( 'Koš je prázdný', 'pneukarnik-booking' ),
					'all_items'          => __( 'Oznámení', 'pneukarnik-booking' ),
				],
				'public'        => false,
				'show_ui'       => true,
				'show_in_rest'  => false, // Strukturovaná pole v klasickém formuláři, ne blokový editor.
				'supports'      => [ 'title' ],
				'rewrite'       => false,
				'menu_position' => 26,
				'menu_icon'     => 'dashicons-megaphone',
			]
		);

		$meta = [
			'_notice_text'       => 'string',
			'_notice_valid_from' => 'string',
			'_notice_valid_to'   => 'string',
			'_notice_at_booking' => 'boolean',
		];
		foreach ( $meta as $key => $type ) {
			register_post_meta(
				Pneukarnik_Notice::POST_TYPE,
				$key,
				[
					'single'       => true,
					'type'         => $type,
					'show_in_rest' => false,
				]
			);
		}

		Pneukarnik_Publish_Guard::register(
			Pneukarnik_Notice::POST_TYPE,
			static fn( int $id ): array => Pneukarnik_Notice::find( $id )?->missing_required() ?? [],
			/* translators: %s: seznam chybějících částí */
			__( 'Oznámení není zveřejněné, chybí: %s. Zůstává uložené jako koncept.', 'pneukarnik-booking' )
		);
	}
}
