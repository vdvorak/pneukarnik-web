<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sitemapa stránek pluginu, které nejsou obsahem WordPressu: rozcestníky Kategorií a rezervace
 * (/wp-sitemap-pneukarnik-1.xml). Načte se až s sitemapami WordPressu (háček wp_sitemaps_init).
 */
final class Pneukarnik_Sitemap_Provider extends WP_Sitemaps_Provider {

	/** Jen malá písmena, jinak sitemapu nenajdou přepisovací pravidla WordPressu. */
	public const NAME = 'pneukarnik';

	public function __construct() {
		$this->name        = self::NAME;
		$this->object_type = self::NAME;
	}

	/**
	 * @param int    $page_num       Stránka sitemapy, všechny adresy se vejdou na první.
	 * @param string $object_subtype Bez podtypů.
	 * @return list<array{loc:string}>
	 */
	public function get_url_list( $page_num, $object_subtype = '' ): array {
		if ( 1 !== (int) $page_num ) {
			return [];
		}
		return array_map( static fn( string $url ): array => [ 'loc' => $url ], Pneukarnik_Seo::plugin_page_urls() );
	}

	/**
	 * @param string $object_subtype Bez podtypů.
	 */
	public function get_max_num_pages( $object_subtype = '' ): int {
		return 1;
	}
}
