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
		$theme = wp_get_theme();
		wp_enqueue_style( 'pneukarnik', get_stylesheet_uri(), [], (string) $theme->get( 'Version' ) );
	}
);
