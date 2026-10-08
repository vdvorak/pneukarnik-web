<?php
/**
 * Hlavička pro vyhledávače a sociální sítě: description, kanonická adresa, Open Graph,
 * strukturovaná data (schema.org) a ověření Google Search Console. Data dodává Pneukarnik_Seo z pluginu.
 * Měření návštěvnosti Matomem bez cookies.
 *
 * @package Pneukarnik
 */

declare(strict_types=1);

// Kanonickou adresu vypisuje pneukarnik_seo_head() pro všechny stránky, WordPress jen pro detaily.
remove_action( 'wp_head', 'rel_canonical' );
add_action( 'wp_head', 'pneukarnik_seo_head', 1 );
add_action( 'wp_head', 'pneukarnik_matomo' );

function pneukarnik_seo_head(): void {
	if ( ! class_exists( 'Pneukarnik_Seo' ) ) {
		return;
	}
	$verification = Pneukarnik_Seo::google_verification();
	if ( '' !== $verification ) {
		printf( '<meta name="google-site-verification" content="%s">' . "\n", esc_attr( $verification ) );
	}

	$page = Pneukarnik_Seo::current();
	if ( ! $page ) {
		return;
	}
	if ( '' !== $page['description'] ) {
		printf( '<meta name="description" content="%s">' . "\n", esc_attr( $page['description'] ) );
	}
	if ( '' !== $page['url'] ) {
		printf( '<link rel="canonical" href="%s">' . "\n", esc_url( $page['url'] ) );
	}

	$og = [
		'og:type'        => 'website',
		'og:locale'      => get_locale(),
		'og:site_name'   => Pneukarnik_Contact::company(),
		'og:title'       => $page['title'],
		'og:description' => $page['description'],
		'og:url'         => $page['url'],
		'og:image'       => (string) get_site_icon_url( 512 ),
	];
	foreach ( array_filter( $og ) as $property => $content ) {
		printf( '<meta property="%s" content="%s">' . "\n", esc_attr( $property ), 'og:url' === $property || 'og:image' === $property ? esc_url( $content ) : esc_attr( $content ) );
	}
	echo '<meta name="twitter:card" content="summary">' . "\n";

	foreach ( Pneukarnik_Seo::schema() as $schema ) {
		wp_print_inline_script_tag(
			(string) wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG ),
			[ 'type' => 'application/ld+json' ]
		);
	}
}

/**
 * Matomo bez cookies (disableCookies), web proto nepotřebuje cookie lištu. Jen když ho Provozovatel
 * nastavil. Tajné tokeny z adres (potvrzení, Zrušení, Objednat znovu, nastavení e‑mailů) ani e‑mail
 * ze starého odkazu na odhlášení do Matoma neodejdou.
 */
function pneukarnik_matomo(): void {
	$matomo = class_exists( 'Pneukarnik_Seo' ) ? Pneukarnik_Seo::matomo() : null;
	if ( ! $matomo ) {
		return;
	}
	$config = (string) wp_json_encode(
		[
			'url'    => $matomo['url'],
			'siteId' => (string) $matomo['site_id'],
		],
		JSON_UNESCAPED_SLASHES | JSON_HEX_TAG
	);
	wp_print_inline_script_tag(
		<<<JS
		(function (config) {
			function withoutTokens(href) {
				try {
					var url = new URL(href);
					['r', 'znovu', 'k', 't', 'email'].forEach(function (name) {
						url.searchParams.delete(name);
					});
					return url.href;
				} catch (e) {
					return '';
				}
			}
			var _paq = (window._paq = window._paq || []);
			_paq.push(['disableCookies']);
			_paq.push(['setTrackerUrl', config.url + 'matomo.php']);
			_paq.push(['setSiteId', config.siteId]);
			_paq.push(['setCustomUrl', withoutTokens(window.location.href)]);
			if (document.referrer) {
				_paq.push(['setReferrerUrl', withoutTokens(document.referrer)]);
			}
			_paq.push(['trackPageView']);
			_paq.push(['enableLinkTracking']);
			var script = document.createElement('script');
			script.async = true;
			script.src = config.url + 'matomo.js';
			document.head.appendChild(script);
		})({$config});
		JS
	);
}
