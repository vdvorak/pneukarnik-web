<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registrace Služeb ve WordPressu: typ obsahu, pole, adresy a pravidlo zveřejnění.
 *
 * Adresy:
 *   /{kategorie}/            rozcestník Kategorie (archiv typu s filtrem na Kategorii)
 *   /{kategorie}/{služba}/   detail Služby; pod cizí Kategorií je 404
 */
final class Pneukarnik_Service_Type {

	public const QUERY_VAR = 'pnk_kategorie';

	/** Při změně adres pluginu (Služby i stránky rezervace) zvyšte, přegenerují se samy. */
	private const REWRITE_VERSION = '2';

	/** @var array<int, true> Služby vrácené do konceptu v tomto požadavku (kvůli hlášce po přesměrování). */
	private static array $demoted = [];

	/** Brání zacyklení: vracení do konceptu samo volá save_post. */
	private static bool $demoting = false;

	public static function init(): void {
		add_action( 'init', [ self::class, 'register' ] );
		add_action( 'init', [ self::class, 'maybe_flush_rewrites' ], 99 );
		add_filter( 'query_vars', [ self::class, 'query_vars' ] );
		add_filter( 'post_type_link', [ self::class, 'permalink' ], 10, 2 );
		add_filter( 'post_type_archive_title', [ self::class, 'archive_title' ], 10, 2 );
		add_filter( 'post_type_archive_link', [ self::class, 'archive_link' ], 10, 2 );
		add_action( 'pre_get_posts', [ self::class, 'filter_category_archive' ] );
		add_action( 'wp', [ self::class, 'reject_foreign_category' ] );
		add_action( 'save_post_' . Pneukarnik_Service::POST_TYPE, [ self::class, 'enforce_required_for_publish' ], 20 );
		add_filter( 'redirect_post_location', [ self::class, 'redirect_after_demotion' ], 10, 2 );
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
	 * Archiv bez Kategorie neexistuje (jen rozcestníky), WordPress na něj tedy nemá odkazovat.
	 */
	public static function archive_link( string|false $link, string $post_type ): string|false {
		return Pneukarnik_Service::POST_TYPE === $post_type ? false : $link;
	}

	/**
	 * Název rozcestníku je název Kategorie.
	 */
	public static function archive_title( string $title, string $post_type ): string {
		if ( Pneukarnik_Service::POST_TYPE !== $post_type ) {
			return $title;
		}
		return Pneukarnik_Service::categories()[ (string) get_query_var( self::QUERY_VAR ) ] ?? $title;
	}

	/**
	 * Rozcestník: jen Služby dané Kategorie, všechny najednou, v nastaveném pořadí.
	 */
	public static function filter_category_archive( WP_Query $query ): void {
		if ( ! $query->is_main_query() || ! $query->is_post_type_archive( Pneukarnik_Service::POST_TYPE ) ) {
			return;
		}
		$category = (string) $query->get( self::QUERY_VAR );
		if ( ! isset( Pneukarnik_Service::categories()[ $category ] ) ) {
			return; // 404 pošle reject_foreign_category().
		}
		$query->set( 'meta_key', '_service_category' );
		$query->set( 'meta_value', $category );
		$query->set( 'posts_per_page', -1 );
		$query->set( 'orderby', Pneukarnik_Service::order_by() );
	}

	/**
	 * Detail Služby je jen pod její vlastní Kategorií. Holý archiv typu bez Kategorie neexistuje.
	 */
	public static function reject_foreign_category(): void {
		global $wp_query;
		if ( $wp_query->is_post_type_archive( Pneukarnik_Service::POST_TYPE ) && ! isset( Pneukarnik_Service::categories()[ (string) get_query_var( self::QUERY_VAR ) ] ) ) {
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

	/**
	 * Služba bez povinných částí nesmí být zveřejněná: vrátí se do konceptu.
	 * Běží po uložení polí (priorita 20), platí pro administraci i kód.
	 */
	public static function enforce_required_for_publish( int $post_id ): void {
		if ( self::$demoting || wp_is_post_revision( $post_id ) ) {
			return;
		}
		$service = Pneukarnik_Service::find( $post_id );
		if ( ! $service || ! in_array( $service->status, [ 'publish', 'future' ], true ) ) {
			return;
		}
		$missing = $service->missing_required();
		if ( ! $missing ) {
			return;
		}
		self::$demoted[ $post_id ] = true;
		self::$demoting            = true;
		try {
			wp_update_post(
				[
					'ID'          => $post_id,
					'post_status' => 'draft',
				]
			);
		} finally {
			self::$demoting = false;
		}
		set_transient( self::notice_key( $post_id ), $missing, MINUTE_IN_SECONDS );
	}

	/**
	 * Po vrácení do konceptu nezobrazovat hlášku WordPressu „Služba publikována“.
	 */
	public static function redirect_after_demotion( string $location, int $post_id ): string {
		if ( ! isset( self::$demoted[ $post_id ] ) ) {
			return $location;
		}
		return add_query_arg( 'message', 10, $location ); // 10 = „Koncept aktualizován“.
	}

	/**
	 * Co chybělo Službě, kterou systém právě vrátil do konceptu (pro hlášku po uložení).
	 *
	 * @return list<string>
	 */
	public static function pull_demotion_notice( int $post_id ): array {
		$missing = get_transient( self::notice_key( $post_id ) );
		delete_transient( self::notice_key( $post_id ) );
		return is_array( $missing ) ? $missing : [];
	}

	private static function notice_key( int $post_id ): string {
		return 'pnk_service_demoted_' . $post_id;
	}

	private static function send_404(): void {
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
	}
}
