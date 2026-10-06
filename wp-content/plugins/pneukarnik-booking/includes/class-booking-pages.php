<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stránky rezervace: /rezervace/ (formulář), /rezervace/potvrzeni/?r={token}
 * a /rezervace/zruseni/?r={token} (Zrušení odkazem z e‑mailu). K nim odhlášení z e‑mailů
 * /odhlaseni/?t={token} (Připomínka přezutí) nebo ?email=… (starý odkaz /cancel-subscription?email=…
 * se sem přesměruje).
 * Plugin vlastní adresy a data, vzhled dodává šablona webu souborem rezervace.php,
 * rezervace-potvrzeni.php, rezervace-zruseni.php a odhlaseni.php.
 */
final class Pneukarnik_Booking_Pages {

	public const QUERY_VAR = 'pnk_stranka';

	/** Starý odkaz /cancel-subscription?email=… z e‑mailů starého webu. */
	private const OLD_UNSUBSCRIBE = 'stary-odber';

	private const PAGES = [
		'rezervace' => 'rezervace.php',
		'potvrzeni' => 'rezervace-potvrzeni.php',
		'zruseni'   => 'rezervace-zruseni.php',
		'odhlaseni' => 'odhlaseni.php',
	];

	public static function init(): void {
		add_action( 'init', [ self::class, 'add_rewrite_rules' ] );
		add_filter( 'query_vars', [ self::class, 'query_vars' ] );
		add_action( 'wp', [ self::class, 'reject_unknown_confirmation' ] );
		add_action( 'template_redirect', [ self::class, 'handle_cancellation' ] );
		add_action( 'template_redirect', [ self::class, 'redirect_old_unsubscribe' ], 1 ); // Před redirect_canonical.
		add_action( 'template_redirect', [ self::class, 'handle_unsubscription' ] );
		add_filter( 'template_include', [ self::class, 'template' ] );
		add_filter( 'wp_robots', [ self::class, 'robots' ] );
	}

	public static function add_rewrite_rules(): void {
		add_rewrite_rule( '^rezervace/potvrzeni/?$', 'index.php?' . self::QUERY_VAR . '=potvrzeni', 'top' );
		add_rewrite_rule( '^rezervace/zruseni/?$', 'index.php?' . self::QUERY_VAR . '=zruseni', 'top' );
		add_rewrite_rule( '^rezervace/?$', 'index.php?' . self::QUERY_VAR . '=rezervace', 'top' );
		add_rewrite_rule( '^odhlaseni/?$', 'index.php?' . self::QUERY_VAR . '=odhlaseni', 'top' );
		// Odkaz z e‑mailů starého webu (odhlášení z „informací o slevách“), přesměruje na /odhlaseni/.
		add_rewrite_rule( '^cancel-subscription/?$', 'index.php?' . self::QUERY_VAR . '=' . self::OLD_UNSUBSCRIBE, 'top' );
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

	/**
	 * Odkaz „Přidat do kalendáře“ na stránce potvrzení: soubor .ics chráněný týmž tokenem.
	 */
	public static function confirmation_calendar_url(): string {
		$token = isset( $_GET['r'] ) ? sanitize_key( wp_unslash( $_GET['r'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- jen čtení podle tajného tokenu.
		return Pneukarnik_Rest_Booking_Ics::url( $token );
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
	 * Název aktuální stránky rezervace pro <title> (Pneukarnik_Seo), prázdný mimo stránky rezervace.
	 */
	public static function page_title(): string {
		$titles = [
			'rezervace' => __( 'Rezervace termínu', 'pneukarnik-booking' ),
			'potvrzeni' => __( 'Rezervace přijata', 'pneukarnik-booking' ),
			'zruseni'   => __( 'Zrušení rezervace', 'pneukarnik-booking' ),
			'odhlaseni' => __( 'Odhlášení z e‑mailů', 'pneukarnik-booking' ),
		];
		return $titles[ self::current() ] ?? '';
	}

	/**
	 * @param array<string,bool|string> $robots
	 * @return array<string,bool|string>
	 */
	public static function robots( array $robots ): array {
		if ( in_array( self::current(), [ 'potvrzeni', 'zruseni', 'odhlaseni' ], true ) ) {
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
		$token = isset( $_POST['r'] ) ? sanitize_key( wp_unslash( $_POST['r'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- viz výše.
		$url   = Pneukarnik_Cancellation::url( $token );
		if ( ! Pneukarnik_Rate_Limit::attempt( Pneukarnik_Rate_Limit::CANCEL ) ) {
			wp_safe_redirect( add_query_arg( 'omezeno', '1', $url ), 303 );
			exit;
		}
		$result = Pneukarnik_Cancellation::cancel_by_token( $token );
		wp_safe_redirect( $result['ok'] ? add_query_arg( 'zruseno', '1', $url ) : $url, 303 );
		exit;
	}

	/**
	 * Data pro stránku Zrušení podle odkazu v adrese. code: allowed, too_late, already_cancelled,
	 * cancelled (hned po Zrušení), rate_limited (pokus o Zrušení nad limit IP)
	 * nebo invalid_token (pak bez Rezervace).
	 *
	 * @return array{token:string,code:string,booking:array{date:string,time_start:string,time_end:string,services:list<string>,plate:string,status:string}|null,cancel_until:string,prefill_url:string,phone:string}
	 */
	public static function cancellation(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- jen čtení podle tajného tokenu.
		$token     = isset( $_GET['r'] ) ? sanitize_key( wp_unslash( $_GET['r'] ) ) : '';
		$just_done = isset( $_GET['zruseno'] );
		$limited   = isset( $_GET['omezeno'] );
		// phpcs:enable
		$preview = Pneukarnik_Cancellation::preview( $token );
		$code    = $preview['code'] ?? Pneukarnik_Cancellation::INVALID_TOKEN;
		if ( $just_done && Pneukarnik_Cancellation::ALREADY_CANCELLED === $code ) {
			$code = Pneukarnik_Cancellation::CANCELLED;
		}
		if ( $limited && Pneukarnik_Cancellation::ALLOWED === $code ) {
			$code = Pneukarnik_Cancellation::RATE_LIMITED;
		}
		return [
			'token'        => $token,
			'code'         => $code,
			'booking'      => $preview['booking'] ?? null,
			'cancel_until' => $preview['cancel_until'] ?? '',
			'prefill_url'  => Pneukarnik_Cancellation::prefill_url( $token ),
			'phone'        => pneukarnik_phone(),
		];
	}

	/**
	 * Starý odkaz /cancel-subscription?email=… trvale (301) přesměruje na /odhlaseni/?email=…,
	 * kde se odhlášení ze starého odběru provede.
	 */
	public static function redirect_old_unsubscribe(): void {
		if ( self::OLD_UNSUBSCRIBE !== get_query_var( self::QUERY_VAR ) ) {
			return;
		}
		$email = isset( $_GET['email'] ) ? sanitize_email( wp_unslash( $_GET['email'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- odkaz z e‑mailu starého webu.
		if ( wp_safe_redirect( add_query_arg( 'email', rawurlencode( $email ), home_url( '/odhlaseni/' ) ), 301 ) ) {
			exit;
		}
	}

	/**
	 * Odhlásí hned při otevření stránky, i když šablona webu výsledek nevypíše.
	 */
	public static function handle_unsubscription(): void {
		if ( 'odhlaseni' === self::current() ) {
			self::unsubscription();
		}
	}

	/**
	 * Odhlášení odkazem z e‑mailu hned při otevření stránky (i POST z tlačítka odhlášení v poště):
	 * ?t={token} z Připomínky přezutí, ?email=… ze starého odkazu (jen starý odběr).
	 * Výsledek pro šablonu: code unsubscribe.done nebo unsubscribe.invalid_token, legacy = starý odkaz.
	 *
	 * @return array{code:string,legacy:bool}
	 */
	public static function unsubscription(): array {
		static $results = [];
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- odkaz z e‑mailu, token je sám tajemstvím.
		$token = isset( $_GET['t'] ) ? sanitize_text_field( wp_unslash( $_GET['t'] ) ) : null;
		$email = isset( $_GET['email'] ) ? sanitize_email( wp_unslash( $_GET['email'] ) ) : null;
		// phpcs:enable
		if ( 'odhlaseni' !== self::current() ) {
			return [
				'code'   => Pneukarnik_Subscriptions::INVALID_TOKEN,
				'legacy' => false,
			];
		}
		$key = wp_json_encode( [ $token, $email ] );
		if ( ! isset( $results[ $key ] ) ) {
			$legacy          = null === $token && null !== $email;
			$results[ $key ] = [
				'code'   => $legacy ? Pneukarnik_Subscriptions::withdraw_legacy( $email ) : Pneukarnik_Subscriptions::withdraw_by_token( $token ),
				'legacy' => $legacy,
			];
		}
		return $results[ $key ];
	}

	/**
	 * Data pro rezervační formulář šablony.
	 *
	 * @return array{enabled:bool,disabled_message:string,services:list<array{id:int,slug:string,name:string,duration:int,ask_stored_wheels:bool,guide:array{url:string,title:string}|null}>,max_services:int,selected:int,min_date:string,max_date:string,api:string,nonce:string,phone:string,privacy_url:string}
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
		$today  = Pneukarnik_Clock::today();
		$guides = Pneukarnik_Guide::by_service();

		return [
			'enabled'          => Pneukarnik_Booking::online_enabled(),
			'disabled_message' => Pneukarnik_Booking::online_disabled_message(),
			'services'         => array_map(
				static fn( Pneukarnik_Service $s ): array => [
					'id'                => $s->id,
					'slug'              => $s->slug,
					'name'              => $s->title,
					'duration'          => $s->duration,
					'ask_stored_wheels' => $s->ask_stored_wheels,
					// Odkaz „Přečtěte si“ pod výběrem Služby.
					'guide'             => isset( $guides[ $s->id ] ) ? [
						'url'   => $guides[ $s->id ]->url(),
						'title' => $guides[ $s->id ]->title,
					] : null,
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
