<?php
/**
 * Šablona Pneukarník.
 *
 * @package Pneukarnik
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require __DIR__ . '/inc/template-tags.php';
require __DIR__ . '/inc/content.php';

add_action(
	'after_setup_theme',
	static function (): void {
		add_theme_support( 'title-tag' );
		add_theme_support( 'html5', [ 'search-form', 'gallery', 'caption', 'style', 'script' ] );
	}
);

add_action(
	'wp_enqueue_scripts',
	static function (): void {
		$version = (string) wp_get_theme()->get( 'Version' );
		wp_enqueue_style( 'pneukarnik', get_stylesheet_uri(), [], $version );
		if ( class_exists( 'Pneukarnik_Booking_Pages' ) && 'rezervace' === Pneukarnik_Booking_Pages::current() ) {
			wp_enqueue_script(
				'pneukarnik-rezervace',
				get_theme_file_uri( 'assets/js/rezervace.js' ),
				[],
				$version,
				[
					'strategy'  => 'defer',
					'in_footer' => true,
				]
			);
		}
	}
);
