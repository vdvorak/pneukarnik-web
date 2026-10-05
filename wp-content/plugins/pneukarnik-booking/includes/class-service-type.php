<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registrace Služeb ve WordPressu: typ obsahu, pole, adresy a pravidlo zveřejnění (Pneukarnik_Publish_Guard).
 *
 * Adresy:
 *   /sluzby/                 stránka Služby s oběma Kategoriemi (archiv typu, QUERY_VAR = ALL)
 *   /{kategorie}/            rozcestník Kategorie, tatáž stránka s jednou Kategorií
 *   /{kategorie}/{služba}/   detail Služby; pod cizí Kategorií je 404
 */
final class Pneukarnik_Service_Type {

	public const QUERY_VAR = 'pnk_kategorie';

	/** Hodnota QUERY_VAR pro stránku Služby se všemi Kategoriemi. */
	public const ALL = 'vse';

	/** Při změně adres pluginu (Služby, Průvodci i stránky rezervace) zvyšte, přegenerují se samy. */
	private const REWRITE_VERSION = '6';

	public static function init(): void {
		add_action( 'init', [ self::class, 'register' ] );
		add_action( 'init', [ self::class, 'maybe_flush_rewrites' ], 99 );
		add_filter( 'query_vars', [ self::class, 'query_vars' ] );
		add_filter( 'post_type_link', [ self::class, 'permalink' ], 10, 2 );
		add_filter( 'post_type_archive_title', [ self::class, 'archive_title' ], 10, 2 );
		add_filter( 'post_type_archive_link', [ self::class, 'archive_link' ], 10, 2 );
		add_action( 'pre_get_posts', [ self::class, 'filter_category_archive' ] );
		add_action( 'wp', [ self::class, 'reject_foreign_category' ] );
	}

	public static function register(): void {
		register_post_type(
			Pneukarnik_Service::POST_TYPE,
			[
				'labels'        => [
					'name'               => __( 'Služby', 'pneukarnik-booking' ),
					'singular_name'      => __( 'Služba', 'pneukarnik-booking' ),
					'add_new'            => __( 'Přidat službu', 'pneukarnik-booking' ),
					'add_new_item'       => __( 'Přidat službu', 'pneukarnik-booking' ),
					'edit_item'          => __( 'Upravit službu', 'pneukarnik-booking' ),
					'new_item'           => __( 'Nová služba', 'pneukarnik-booking' ),
					'view_item'          => __( 'Zobrazit službu', 'pneukarnik-booking' ),
					'search_items'       => __( 'Hledat služby', 'pneukarnik-booking' ),
					'not_found'          => __( 'Žádné služby nenalezeny', 'pneukarnik-booking' ),
					'not_found_in_trash' => __( 'Koš je prázdný', 'pneukarnik-booking' ),
				],
				'public'        => true,
				'show_in_rest'  => false, // Strukturovaná pole v klasickém formuláři, ne blokový editor.
				'supports'      => [ 'title', 'page-attributes' ],
				'has_archive'   => true, // Archiv slouží rozcestníkům, vlastní adresy viz register().
				'rewrite'       => false,
				'menu_position' => 25,
				'menu_icon'     => 'dashicons-car',
			]
		);

		$meta = [
			'_service_category'          => 'string',
			'_service_perex'             => 'string',
			'_service_includes'          => 'string',
			'_service_process'           => 'string',
			'_service_duration_text'     => 'string',
			'_service_price'             => 'integer',
			'_service_price_from'        => 'boolean',
			'_service_price_by_vehicle'  => 'boolean',
			'_service_price_note'        => 'string',
			'_service_bring'             => 'string',
			'_service_faq'               => 'array',
			'_service_related'           => 'array',
			'_service_duration'          => 'integer',
			'_service_bookable'          => 'boolean',
			'_service_is_seasonal'       => 'boolean',
			'_service_ask_stored_wheels' => 'boolean',
			'_service_featured'          => 'boolean',
			'_service_icon'              => 'string',
			'_service_seo_title'         => 'string',
			'_service_seo_description'   => 'string',
		];
		foreach ( $meta as $key => $type ) {
			register_post_meta(
				Pneukarnik_Service::POST_TYPE,
				$key,
				[
					'single'       => true,
					'type'         => $type,
					'show_in_rest' => false,
				]
			);
		}

		Pneukarnik_Publish_Guard::register(
			Pneukarnik_Service::POST_TYPE,
			static fn( int $id ): array => Pneukarnik_Service::find( $id )?->missing_required() ?? [],
			/* translators: %s: seznam chybějících částí */
			__( 'Služba není zveřejněná, chybí: %s. Zůstává uložená jako koncept.', 'pneukarnik-booking' )
		);

		$categories = implode( '|', array_keys( Pneukarnik_Service::categories() ) );
		add_rewrite_rule(
			"^({$categories})/([^/]+)/?$",
			'index.php?post_type=' . Pneukarnik_Service::POST_TYPE . '&name=$matches[2]&' . self::QUERY_VAR . '=$matches[1]',
			'top'
		);
		add_rewrite_rule(
			"^({$categories})/?$",
			'index.php?post_type=' . Pneukarnik_Service::POST_TYPE . '&' . self::QUERY_VAR . '=$matches[1]',
			'top'
		);
		add_rewrite_rule(
			'^sluzby/?$',
			'index.php?post_type=' . Pneukarnik_Service::POST_TYPE . '&' . self::QUERY_VAR . '=' . self::ALL,
			'top'
		);
	}

	public static function maybe_flush_rewrites(): void {
		if ( get_option( 'pneukarnik_rewrite_version' ) !== self::REWRITE_VERSION ) {
			flush_rewrite_rules( false );
			update_option( 'pneukarnik_rewrite_version', self::REWRITE_VERSION );
		}
	}

	/**
	 * @param list<string> $vars
	 * @return list<string>
	 */
	public static function query_vars( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Zveřejněná Služba má adresu /{kategorie}/{služba}/, koncept zůstává na náhledové adrese WordPressu.
	 */
	public static function permalink( string $url, WP_Post $post ): string {
		if ( Pneukarnik_Service::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status || ! get_option( 'permalink_structure' ) ) {
			return $url;
		}
		$category = (string) get_post_meta( $post->ID, '_service_category', true );
		if ( ! isset( Pneukarnik_Service::categories()[ $category ] ) ) {
			return $url;
		}
		return home_url( user_trailingslashit( "{$category}/{$post->post_name}" ) );
	}

	/**
	 * Archiv typu je stránka Služby /sluzby/.
	 */
	public static function archive_link( string|false $link, string $post_type ): string|false {
		return Pneukarnik_Service::POST_TYPE === $post_type ? Pneukarnik_Service::services_url() : $link;
	}

	/**
	 * Název stránky Služby, rozcestníku název Kategorie.
	 */
	public static function archive_title( string $title, string $post_type ): string {
		if ( Pneukarnik_Service::POST_TYPE !== $post_type ) {
			return $title;
		}
		$category = self::shown_category();
		return null === $category ? __( 'Služby', 'pneukarnik-booking' ) : Pneukarnik_Service::categories()[ $category ];
	}

	/**
	 * Kategorie, kterou ukazuje stránka Služby: null = obě (/sluzby/), jinak rozcestník Kategorie.
	 * Mimo stránku Služby null.
	 */
	public static function shown_category(): ?string {
		$category = (string) get_query_var( self::QUERY_VAR );
		return isset( Pneukarnik_Service::categories()[ $category ] ) ? $category : null;
	}

	/**
	 * Stránka Služby: všechny Služby (v rozcestníku jen dané Kategorie) najednou, v nastaveném pořadí.
	 */
	public static function filter_category_archive( WP_Query $query ): void {
		if ( ! $query->is_main_query() || ! $query->is_post_type_archive( Pneukarnik_Service::POST_TYPE ) ) {
			return;
		}
		$category = (string) $query->get( self::QUERY_VAR );
		if ( self::ALL !== $category && ! isset( Pneukarnik_Service::categories()[ $category ] ) ) {
			return; // 404 pošle reject_foreign_category().
		}
		if ( self::ALL !== $category ) {
			$query->set( 'meta_key', '_service_category' );
			$query->set( 'meta_value', $category );
		}
		$query->set( 'post_status', 'publish' ); // Ani přihlášenému Provozovateli soukromé Služby.
		$query->set( 'posts_per_page', -1 );
		$query->set( 'orderby', Pneukarnik_Service::order_by() );
	}

	/**
	 * Detail Služby je jen pod její vlastní Kategorií. Holý archiv typu bez /sluzby/ nebo Kategorie neexistuje.
	 */
	public static function reject_foreign_category(): void {
		global $wp_query;
		$category = (string) get_query_var( self::QUERY_VAR );
		if ( $wp_query->is_post_type_archive( Pneukarnik_Service::POST_TYPE ) && self::ALL !== $category && ! isset( Pneukarnik_Service::categories()[ $category ] ) ) {
			self::send_404();
			return;
		}
		if ( ! $wp_query->is_singular( Pneukarnik_Service::POST_TYPE ) || '' === (string) get_query_var( self::QUERY_VAR ) ) {
			return;
		}
		$service = Pneukarnik_Service::find( (int) $wp_query->get_queried_object_id() );
		if ( ! $service || $service->category !== get_query_var( self::QUERY_VAR ) ) {
			self::send_404();
		}
	}

	private static function send_404(): void {
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
	}
}
