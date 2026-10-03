<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stránky rezervace: /rezervace/ (formulář) a /rezervace/potvrzeni/?r={token}.
 * Plugin vlastní adresy a data, vzhled dodává šablona webu souborem rezervace.php
 * a rezervace-potvrzeni.php.
 */
final class Pneukarnik_Booking_Pages {

	public const QUERY_VAR = 'pnk_stranka';

	private const PAGES = [
		'rezervace' => 'rezervace.php',
		'potvrzeni' => 'rezervace-potvrzeni.php',
	];

	public static function init(): void {
		add_action( 'init', [ self::class, 'add_rewrite_rules' ] );
		add_filter( 'query_vars', [ self::class, 'query_vars' ] );
		add_action( 'wp', [ self::class, 'reject_unknown_confirmation' ] );
		add_filter( 'template_include', [ self::class, 'template' ] );
		add_filter( 'document_title_parts', [ self::class, 'title' ] );
		add_filter( 'wp_robots', [ self::class, 'robots' ] );
	}

	public static function add_rewrite_rules(): void {
		add_rewrite_rule( '^rezervace/potvrzeni/?$', 'index.php?' . self::QUERY_VAR . '=potvrzeni', 'top' );
		add_rewrite_rule( '^rezervace/?$', 'index.php?' . self::QUERY_VAR . '=rezervace', 'top' );
	}

	/**
	 * @param list<string> $vars
	 * @return list<string>
	 */
	public static function query_vars( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	public static function current(): string {
		$page = (string) get_query_var( self::QUERY_VAR );
		return isset( self::PAGES[ $page ] ) ? $page : '';
	}

	/**
	 * Rezervace pro stránku potvrzení podle tokenu v adrese, nebo null.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function confirmed_booking(): ?array {
		static $booking = false;
		if ( false === $booking ) {
			$token   = isset( $_GET['r'] ) ? sanitize_key( wp_unslash( $_GET['r'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- jen čtení podle tajného tokenu.
			$booking = Pneukarnik_Booking::find_by_confirmation_token( $token );
		}
		return $booking;
	}

	public static function reject_unknown_confirmation(): void {
		if ( 'potvrzeni' === self::current() && null === self::confirmed_booking() ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
		}
	}

	public static function template( string $template ): string {
		$page = self::current();
		if ( '' === $page || is_404() ) {
			return $template;
		}
		return locate_template( self::PAGES[ $page ] ) ?: $template;
	}

	/**
	 * @param array<string,string> $parts
	 * @return array<string,string>
	 */
	public static function title( array $parts ): array {
		$titles = [
			'rezervace' => __( 'Rezervace termínu', 'pneukarnik-booking' ),
			'potvrzeni' => __( 'Rezervace přijata', 'pneukarnik-booking' ),
		];
		$page   = self::current();
		if ( '' !== $page && ! is_404() ) {
			$parts['title'] = $titles[ $page ];
		}
		return $parts;
	}

	/**
	 * @param array<string,bool|string> $robots
	 * @return array<string,bool|string>
	 */
	public static function robots( array $robots ): array {
		if ( 'potvrzeni' === self::current() ) {
			$robots['noindex'] = true;
		}
		return $robots;
	}

	/**
	 * Data pro rezervační formulář šablony.
	 *
	 * @return array{enabled:bool,disabled_message:string,services:list<array{id:int,slug:string,name:string,ask_stored_wheels:bool}>,max_services:int,selected:int,min_date:string,max_date:string,api:string,nonce:string,phone:string,privacy_url:string}
	 */
	public static function form_config(): array {
		$services = array_values(
			array_filter(
				Pneukarnik_Service::published(),
				static fn( Pneukarnik_Service $s ): bool => null === Pneukarnik_Booking::service_refusal( $s )
			)
		);
		$slug     = isset( $_GET['sluzba'] ) ? sanitize_title( wp_unslash( $_GET['sluzba'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- jen předvýběr Služby.
		$selected = 0;
		foreach ( $services as $service ) {
			if ( $service->slug === $slug ) {
				$selected = $service->id;
			}
		}
		$today = Pneukarnik_Clock::today();

		return [
			'enabled'          => (bool) get_option( 'pneukarnik_booking_enabled', '1' ),
			'disabled_message' => (string) get_option( 'pneukarnik_booking_disabled_msg', __( 'Online rezervace jsou momentálně nedostupné. Kontaktujte nás telefonicky.', 'pneukarnik-booking' ) ),
			'services'         => array_map(
				static fn( Pneukarnik_Service $s ): array => [
					'id'                => $s->id,
					'slug'              => $s->slug,
					'name'              => $s->title,
					'ask_stored_wheels' => $s->ask_stored_wheels,
				],
				$services
			),
			'max_services'     => Pneukarnik_Booking::MAX_SERVICES,
			'selected'         => $selected,
			'min_date'         => $today->format( 'Y-m-d' ),
			'max_date'         => $today->modify( '+' . Pneukarnik_Working_Hours::get_horizon_days() . ' days' )->format( 'Y-m-d' ),
			'api'              => rest_url( PNEUKARNIK_REST_NAMESPACE ),
			'nonce'            => wp_create_nonce( 'wp_rest' ),
			'phone'            => pneukarnik_phone(),
			'privacy_url'      => home_url( '/ochrana-osobnich-udaju/' ),
		];
	}
}
