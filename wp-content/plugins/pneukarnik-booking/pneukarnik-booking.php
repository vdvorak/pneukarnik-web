<?php
/**
 * Plugin Name: Pneukarnik Booking
 * Plugin URI:  https://pneukarnik.cz
 * Description: Rezervační systém pro autoservis Jan Kárník.
 * Version:     1.0.0
 * Author:      Jan Kárník
 * Text Domain: pneukarnik-booking
 * Requires PHP: 8.1
 * Requires at least: 6.4
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PNEUKARNIK_VERSION', '1.0.0' );
define( 'PNEUKARNIK_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PNEUKARNIK_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PNEUKARNIK_REST_NAMESPACE', 'pneukarnik/v1' );

// Autoload
spl_autoload_register(
	function ( string $class_name ): void {
		$map = [
			'Pneukarnik_Clock'                => 'includes/class-clock.php',
			'Pneukarnik_DB'                   => 'includes/class-db.php',
			'Pneukarnik_Service'              => 'includes/class-service.php',
			'Pneukarnik_Service_Type'         => 'includes/class-service-type.php',
			'Pneukarnik_Working_Hours'        => 'includes/class-working-hours.php',
			'Pneukarnik_Season'               => 'includes/class-season.php',
			'Pneukarnik_Day_Exceptions'       => 'includes/class-day-exceptions.php',
			'Pneukarnik_Holidays'             => 'includes/class-holidays.php',
			'Pneukarnik_Slot_Engine'          => 'includes/class-slot-engine.php',
			'Pneukarnik_Booking'              => 'includes/class-booking.php',
			'Pneukarnik_Booking_Pages'        => 'includes/class-booking-pages.php',
			'Pneukarnik_Cancellation'         => 'includes/class-cancellation.php',
			'Pneukarnik_Notifications'        => 'includes/class-notifications.php',
			'Pneukarnik_Email'                => 'includes/class-email.php',
			'Pneukarnik_GDPR'                 => 'includes/class-gdpr.php',
			'Pneukarnik_Rest_Services'        => 'api/class-rest-services.php',
			'Pneukarnik_Rest_Slots'           => 'api/class-rest-slots.php',
			'Pneukarnik_Rest_Available_Days'  => 'api/class-rest-available-days.php',
			'Pneukarnik_Rest_Bookings'        => 'api/class-rest-bookings.php',
			'Pneukarnik_Rest_Cancel'          => 'api/class-rest-cancel.php',
			'Pneukarnik_Rest_Prefill'         => 'api/class-rest-prefill.php',
			'Pneukarnik_Prefill'              => 'includes/class-prefill.php',
			'Pneukarnik_Rest_Calendar'        => 'api/class-rest-calendar.php',
			'Pneukarnik_Admin_Settings'       => 'admin/class-admin-settings.php',
			'Pneukarnik_Admin_Bookings'       => 'admin/class-admin-bookings.php',
			'Pneukarnik_Admin_Create'         => 'admin/class-admin-create.php',
			'Pneukarnik_Admin_Pdf'            => 'admin/class-admin-pdf.php',
			'Pneukarnik_Admin_Service_Meta'   => 'admin/class-admin-service-meta.php',
			'Pneukarnik_Admin_Day_Exceptions' => 'admin/class-admin-day-exceptions.php',
		];
		if ( isset( $map[ $class_name ] ) ) {
			require_once PNEUKARNIK_PLUGIN_DIR . $map[ $class_name ];
		}
	}
);

// Activation / deactivation
register_activation_hook( __FILE__, 'pneukarnik_activate' );
register_deactivation_hook( __FILE__, 'pneukarnik_deactivate' );

function pneukarnik_activate(): void {
	Pneukarnik_DB::activate();
	// Adresy Služeb se přegenerují při dalším požadavku (Pneukarnik_Service_Type::maybe_flush_rewrites).
	delete_option( 'pneukarnik_rewrite_version' );
	pneukarnik_ensure_capabilities();
	// Schedule daily GDPR anonymisation cron at 03:00
	if ( ! wp_next_scheduled( Pneukarnik_GDPR::CRON_HOOK ) ) {
		$next_3am = Pneukarnik_Clock::today()->setTime( 3, 0 );
		if ( $next_3am <= Pneukarnik_Clock::now() ) {
			$next_3am = $next_3am->modify( '+1 day' );
		}
		wp_schedule_event( $next_3am->getTimestamp(), 'daily', Pneukarnik_GDPR::CRON_HOOK );
	}
}

function pneukarnik_deactivate(): void {
	Pneukarnik_DB::deactivate();
	// Bez pluginu nesmí zůstat adresy /pneuservis/ a /autoservis/. WordPress pravidla vytvoří znovu.
	delete_option( 'rewrite_rules' );
	delete_option( 'pneukarnik_rewrite_version' );
	wp_clear_scheduled_hook( Pneukarnik_GDPR::CRON_HOOK );
}

function pneukarnik_ensure_capabilities(): void {
	$admin = get_role( 'administrator' );
	if ( $admin ) {
		$admin->add_cap( 'pneukarnik_manage_bookings' );
		$admin->add_cap( 'pneukarnik_view_bookings' );
	}
}

// GDPR
add_action( 'plugins_loaded', [ 'Pneukarnik_GDPR', 'init' ] );

// Bootstrap
add_action( 'plugins_loaded', [ 'Pneukarnik_DB', 'maybe_upgrade' ] );
add_action( 'plugins_loaded', 'pneukarnik_ensure_capabilities' );
Pneukarnik_Service_Type::init();
Pneukarnik_Booking_Pages::init();
add_action( 'init', [ 'Pneukarnik_Admin_Service_Meta', 'init' ] );
add_action( 'rest_api_init', 'pneukarnik_register_rest_routes' );
add_action( 'admin_menu', 'pneukarnik_register_admin_menus' );
add_action( 'admin_post_pneukarnik_export_day_pdf', [ 'Pneukarnik_Admin_Pdf', 'handle_export' ] );

// Cache invalidation
add_action( 'save_post_pneukarnik_service', [ 'Pneukarnik_Rest_Services', 'invalidate_cache' ] );
add_action( 'delete_post', 'pneukarnik_invalidate_post_cache' );

function pneukarnik_invalidate_post_cache( int $post_id ): void {
	$type = get_post_type( $post_id );
	if ( $type === 'pneukarnik_service' ) {
		Pneukarnik_Rest_Services::invalidate_cache();
	}
}

function pneukarnik_register_rest_routes(): void {
	( new Pneukarnik_Rest_Services() )->register_routes();
	( new Pneukarnik_Rest_Slots() )->register_routes();
	( new Pneukarnik_Rest_Available_Days() )->register_routes();
	( new Pneukarnik_Rest_Bookings() )->register_routes();
	( new Pneukarnik_Rest_Cancel() )->register_routes();
	( new Pneukarnik_Rest_Prefill() )->register_routes();
	( new Pneukarnik_Rest_Calendar() )->register_routes();
}

function pneukarnik_register_admin_menus(): void {
	add_menu_page(
		__( 'Pneukarnik', 'pneukarnik-booking' ),
		__( 'Pneukarnik', 'pneukarnik-booking' ),
		'pneukarnik_view_bookings',
		'pneukarnik-booking',
		[ 'Pneukarnik_Admin_Bookings', 'render_page' ],
		'dashicons-car',
		30
	);

	add_submenu_page(
		'pneukarnik-booking',
		__( 'Rezervace', 'pneukarnik-booking' ),
		__( 'Rezervace', 'pneukarnik-booking' ),
		'pneukarnik_view_bookings',
		'pneukarnik-booking',
		[ 'Pneukarnik_Admin_Bookings', 'render_page' ]
	);

	$settings_hook = add_submenu_page(
		'pneukarnik-booking',
		__( 'Nastavení', 'pneukarnik-booking' ),
		__( 'Nastavení', 'pneukarnik-booking' ),
		'manage_options',
		'pneukarnik-settings',
		[ 'Pneukarnik_Admin_Settings', 'render_page' ]
	);
	if ( $settings_hook ) {
		add_action( "load-{$settings_hook}", [ 'Pneukarnik_Admin_Settings', 'handle_post' ] );
	}

	$exceptions_hook = add_submenu_page(
		'pneukarnik-booking',
		__( 'Výjimky', 'pneukarnik-booking' ),
		__( 'Výjimky', 'pneukarnik-booking' ),
		'manage_options',
		Pneukarnik_Admin_Day_Exceptions::PAGE,
		[ 'Pneukarnik_Admin_Day_Exceptions', 'render_page' ]
	);
	if ( $exceptions_hook ) {
		add_action( "load-{$exceptions_hook}", [ 'Pneukarnik_Admin_Day_Exceptions', 'handle_post' ] );
	}
}

/**
 * Telefon Provozovatele z Nastavení, jak se má zobrazit (např. „+420 775 565 326“).
 */
function pneukarnik_phone(): string {
	return trim( (string) get_option( 'pneukarnik_phone', '' ) );
}

function pneukarnik_format_date( string $ymd ): string {
	$dt = \DateTimeImmutable::createFromFormat( 'Y-m-d', $ymd );
	return $dt ? $dt->format( 'd.m.Y' ) : $ymd;
}

/**
 * Den s názvem dne v týdnu nezávisle na jazyku WordPressu, např. „středa 3. 3. 2027“,
 * ve 4. pádě po „na“ „středu 3. 3. 2027“.
 */
function pneukarnik_format_day( string $ymd, bool $accusative = false ): string {
	$days = $accusative
		? [ 'neděli', 'pondělí', 'úterý', 'středu', 'čtvrtek', 'pátek', 'sobotu' ]
		: [ 'neděle', 'pondělí', 'úterý', 'středa', 'čtvrtek', 'pátek', 'sobota' ];
	$day  = Pneukarnik_Clock::at( $ymd );
	return $days[ (int) $day->format( 'w' ) ] . ' ' . $day->format( 'j. n. Y' );
}

function pneukarnik_parse_date_cz( string $input ): string {
	$dt = \DateTimeImmutable::createFromFormat( 'd.m.Y', trim( $input ) );
	return $dt ? $dt->format( 'Y-m-d' ) : '';
}
