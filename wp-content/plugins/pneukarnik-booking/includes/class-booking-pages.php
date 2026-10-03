<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stránky rezervace: /rezervace/ (formulář), /rezervace/potvrzeni/?r={token}
 * a /rezervace/zruseni/?r={token} (Zrušení odkazem z e‑mailu).
 * Plugin vlastní adresy a data, vzhled dodává šablona webu souborem rezervace.php,
 * rezervace-potvrzeni.php a rezervace-zruseni.php.
 */
final class Pneukarnik_Booking_Pages {

	public const QUERY_VAR = 'pnk_stranka';

	private const PAGES = [
		'rezervace' => 'rezervace.php',
		'potvrzeni' => 'rezervace-potvrzeni.php',
		'zruseni'   => 'rezervace-zruseni.php',
	];

	public static function init(): void {
		add_action( 'init', [ self::class, 'add_rewrite_rules' ] );
		add_filter( 'query_vars', [ self::class, 'query_vars' ] );
		add_action( 'wp', [ self::class, 'reject_unknown_confirmation' ] );
		add_action( 'template_redirect', [ self::class, 'handle_cancellation' ] );
		add_filter( 'template_include', [ self::class, 'template' ] );
		add_filter( 'document_title_parts', [ self::class, 'title' ] );
		add_filter( 'wp_robots', [ self::class, 'robots' ] );
	}

	public static function add_rewrite_rules(): void {
		add_rewrite_rule( '^rezervace/potvrzeni/?$', 'index.php?' . self::QUERY_VAR . '=potvrzeni', 'top' );
		add_rewrite_rule( '^rezervace/zruseni/?$', 'index.php?' . self::QUERY_VAR . '=zruseni', 'top' );
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
			'zruseni'   => __( 'Zrušení rezervace', 'pneukarnik-booking' ),
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
		if ( in_array( self::current(), [ 'potvrzeni', 'zruseni' ], true ) ) {
			$robots['noindex'] = true;
		}
		return $robots;
	}

	/**
	 * Potvrzení Zrušení formulářem stránky (funguje i bez JavaScriptu). Token z formuláře
	 * je sám tajemstvím, nonce by nepřidal nic. Po odeslání přesměruje zpět (POST/redirect/GET).
	 */
	public static function handle_cancellation(): void {
		if ( 'zruseni' !== self::current() || 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			return;
		}
		$token  = isset( $_POST['r'] ) ? sanitize_key( wp_unslash( $_POST['r'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- viz výše.
		$result = Pneukarnik_Cancellation::cancel_by_token( $token );
		$url    = Pneukarnik_Cancellation::url( $token );
		wp_safe_redirect( $result['ok'] ? add_query_arg( 'zruseno', '1', $url ) : $url, 303 );
		exit;
	}

	/**
	 * Data pro stránku Zrušení podle odkazu v adrese. code: allowed, too_late, already_cancelled,
	 * cancelled (hned po Zrušení) nebo invalid_token (pak bez Rezervace).
	 *
	 * @return array{token:string,code:string,booking:array{date:string,time_start:string,time_end:string,services:list<string>,plate:string,status:string}|null,cancel_until:string,phone:string}
	 */
	public static function cancellation(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- jen čtení podle tajného tokenu.
		$token     = isset( $_GET['r'] ) ? sanitize_key( wp_unslash( $_GET['r'] ) ) : '';
		$just_done = isset( $_GET['zruseno'] );
		// phpcs:enable
		$preview = Pneukarnik_Cancellation::preview( $token );
		$code    = $preview['code'] ?? Pneukarnik_Cancellation::INVALID_TOKEN;
		if ( $just_done && Pneukarnik_Cancellation::ALREADY_CANCELLED === $code ) {
			$code = Pneukarnik_Cancellation::CANCELLED;
		}
		return [
			'token'        => $token,
			'code'         => $code,
			'booking'      => $preview['booking'] ?? null,
			'cancel_until' => $preview['cancel_until'] ?? '',
			'phone'        => pneukarnik_phone(),
		];
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
