<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trvalé (301) přesměrování adres starého webu na nové stránky. Seznam adres a odkud se vzal:
 * docs/stare-url.md. Pravidla jdou podle cesty, takže fungují, ať starý obsah v databázi
 * zůstal (přepnutí ve stejné instalaci), nebo ne.
 *
 * Příspěvky o dekarbonizaci vedou na detail Služby Dekarbonizace, ostatní příspěvky a jejich
 * archivy na Úvod. Staré odkazy na Služby na Službu se stejným slugem, jinak na Službu převedenou
 * z té staré (Pneukarnik_Legacy_Import). Kotvy staré jednostránky
 * (#services …) řeší skript šablony na Úvodu, na server se nedostanou.
 * Starý /cancel-subscription řeší Pneukarnik_Booking_Pages.
 */
final class Pneukarnik_Old_Urls {

	/** Stránky a příspěvky starého webu s vlastním cílem, cesta bez lomítek na krajích. */
	private const PATHS = [
		'pneuservis-autoservis' => '/',
		'pneuservis-jan-karnik' => '/',
		'zasady-cookies-eu'     => '/ochrana-osobnich-udaju/',
		'dategenerator'         => '/',
		'2020/10/01/o-nas'      => '/o-nas/',
		'service'               => '/',
	];

	/** Slugy příspěvků starého webu podle ID (odkazy ?p=…), i když už v databázi nejsou. */
	private const POST_IDS = [
		290 => 'black-friday',
		957 => 'nove-dekarbonizace-motoru-je-mozna-i-u-nas-zavadejici-ceny',
		976 => 'dekarbonizace-motoru',
	];

	/** Typy obsahu starého webu s veřejnými adresami /{typ}/…, nové stránky nemají. */
	private const OLD_TYPES = [
		'galery'    => '/o-nas/#galerie',
		'closed'    => '/',
		'warning'   => '/',
		'email_tmp' => '/',
		'sluba'     => '/',
		'category'  => '/',
		'author'    => '/',
		'tag'       => '/',
		// Starý pokus o nový web v podadresáři, při přepnutí se smaže.
		'nova'      => '/',
	];

	/** Slug Služby, na kterou vedou příspěvky o dekarbonizaci. */
	private const DECARBONISATION = 'dekarbonizace';

	public static function init(): void {
		add_action( 'template_redirect', [ self::class, 'redirect' ], 1 ); // Před redirect_canonical.
	}

	// Adresa požadavku se jen porovnává s pravidly, nic nemění.
	// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	public static function redirect(): void {
		$path   = (string) wp_parse_url( (string) wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ), PHP_URL_PATH );
		$target = self::target( $path );
		$old_id = absint( $_GET['p'] ?? 0 );
		// Na živém webu jsou pod těmi ID vždy staré příspěvky. Náhledy z administrace (lokálně tam může být nový obsah) nechat.
		if ( null === $target && isset( self::POST_IDS[ $old_id ] ) && ! isset( $_GET['preview'] ) && ! isset( $_GET['post_type'] ) ) {
			$target = self::post_target( self::POST_IDS[ $old_id ] );
		} elseif ( null === $target && is_singular( 'post' ) ) {
			// Starý příspěvek, který v databázi zůstal (přepnutí ve stejné instalaci).
			$target = self::post_target( (string) get_post_field( 'post_name' ) );
		} elseif ( null === $target && is_404() && ( isset( $_GET['p'] ) || isset( $_GET['page_id'] ) ) ) {
			$target = home_url( '/' );
		}
		if ( null !== $target && wp_safe_redirect( $target, 301 ) ) {
			exit;
		}
	}
	// phpcs:enable

	/**
	 * Kam vede stará adresa, null = není stará, nic se nepřesměruje.
	 */
	public static function target( string $path ): ?string {
		$home = trim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
		$path = trim( rawurldecode( $path ), '/' );
		if ( '' !== $home && str_starts_with( $path . '/', $home . '/' ) ) {
			$path = trim( substr( $path, strlen( $home ) ), '/' );
		}

		if ( isset( self::PATHS[ $path ] ) ) {
			return home_url( self::PATHS[ $path ] );
		}
		// Příspěvek /rok/měsíc/den/slug/ (struktura odkazů starého webu).
		if ( preg_match( '~^\d{4}/\d{2}/\d{2}/([^/]+)$~', $path, $m ) ) {
			return self::post_target( $m[1] );
		}
		// Archivy podle data, i stránkované.
		if ( preg_match( '~^\d{4}(/\d{2}){0,2}(/page/\d+)?$~', $path ) ) {
			return home_url( '/' );
		}
		if ( preg_match( '~^service/([^/]+)$~', $path, $m ) ) {
			return self::service_url( $m[1] ) ?? home_url( '/' );
		}
		$type = explode( '/', $path )[0];
		return isset( self::OLD_TYPES[ $type ] ) ? home_url( self::OLD_TYPES[ $type ] ) : null;
	}

	/**
	 * Příspěvek o dekarbonizaci na Službu Dekarbonizace (bez ní na Autoservis), ostatní na Úvod.
	 */
	private static function post_target( string $slug ): string {
		if ( ! str_contains( $slug, self::DECARBONISATION ) ) {
			return home_url( '/' );
		}
		return self::service_url( self::DECARBONISATION ) ?? Pneukarnik_Service::category_url( Pneukarnik_Service::AUTOSERVIS );
	}

	/**
	 * Adresa zveřejněné Služby se slugem, jinak Služby převedené ze staré Služby s tímto slugem
	 * (Pneukarnik_Legacy_Import), null když taková není.
	 */
	private static function service_url( string $slug ): ?string {
		$query = [
			'post_type'   => Pneukarnik_Service::POST_TYPE,
			'post_status' => 'publish',
			'numberposts' => 1,
		];
		$posts = get_posts( $query + [ 'name' => sanitize_title( $slug ) ] );
		if ( ! $posts ) {
			$old   = get_posts(
				[
					'post_type'   => 'service',
					'post_status' => 'any',
					'name'        => sanitize_title( $slug ),
					'numberposts' => 1,
					'fields'      => 'ids',
				]
			);
			$posts = $old ? get_posts(
				$query + [
					'meta_key'   => Pneukarnik_Legacy_Import::SERVICE_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value' => (string) $old[0], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				]
			) : [];
		}
		return $posts ? Pneukarnik_Service::from_post( $posts[0] )->url() : null;
	}
}
