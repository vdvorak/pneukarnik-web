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
require __DIR__ . '/inc/seo.php';

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

add_action( 'wp_head', 'pneukarnik_old_anchors', 0 );

/**
 * Kotvy staré jednostránky (pneukarnik.cz/#services …) na nové stránky. Kotva se na server
 * neposílá, proto skript hned na začátku Úvodu. Seznam viz docs/stare-url.md.
 */
function pneukarnik_old_anchors(): void {
	if ( ! is_front_page() ) {
		return;
	}
	$contact = home_url( '/kontakt/' );
	$about   = home_url( '/o-nas/' );
	$targets = [
		'services'     => home_url( '/#sluzby' ),
		'reservations' => home_url( '/rezervace/' ),
		'rezervace'    => home_url( '/rezervace/' ),
		'contacts'     => $contact,
		'kontakty'     => $contact,
		'map'          => $contact,
		'location'     => $contact,
		'about'        => $about,
		'o-nas'        => $about,
		'galery'       => $about . '#galerie',
		'galerie'      => $about . '#galerie',
	];
	wp_print_inline_script_tag(
		'try{(function(t,h){if(Object.prototype.hasOwnProperty.call(t,h))location.replace(t[h]);})('
		. wp_json_encode( $targets ) . ',decodeURIComponent(location.hash.slice(1)));}catch(e){}'
	);
}
