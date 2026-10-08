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
			'Pneukarnik_Clock'                 => 'includes/class-clock.php',
			'Pneukarnik_DB'                    => 'includes/class-db.php',
			'Pneukarnik_Service'               => 'includes/class-service.php',
			'Pneukarnik_Service_Type'          => 'includes/class-service-type.php',
			'Pneukarnik_Promotion'             => 'includes/class-promotion.php',
			'Pneukarnik_Promotion_Type'        => 'includes/class-promotion-type.php',
			'Pneukarnik_Publish_Guard'         => 'includes/class-publish-guard.php',
			'Pneukarnik_Validity'              => 'includes/class-validity.php',
			'Pneukarnik_Contact'               => 'includes/class-contact.php',
			'Pneukarnik_Reviews'               => 'includes/class-reviews.php',
			'Pneukarnik_Seo'                   => 'includes/class-seo.php',
			'Pneukarnik_Subscriptions'         => 'includes/class-subscriptions.php',
			'Pneukarnik_Reminder'              => 'includes/class-reminder.php',
			'Pneukarnik_Termin_Reminder'       => 'includes/class-termin-reminder.php',
			'Pneukarnik_Review_Request'        => 'includes/class-review-request.php',
			'Pneukarnik_Mailing'               => 'includes/class-mailing.php',
			'Pneukarnik_Rest_Reminder'         => 'api/class-rest-reminder.php',
			'Pneukarnik_Sitemap_Provider'      => 'includes/class-sitemap-provider.php',
			'Pneukarnik_Notice'                => 'includes/class-notice.php',
			'Pneukarnik_Notice_Type'           => 'includes/class-notice-type.php',
			'Pneukarnik_Guide'                 => 'includes/class-guide.php',
			'Pneukarnik_Guide_Type'            => 'includes/class-guide-type.php',
			'Pneukarnik_Working_Hours'         => 'includes/class-working-hours.php',
			'Pneukarnik_Season'                => 'includes/class-season.php',
			'Pneukarnik_Day_Exceptions'        => 'includes/class-day-exceptions.php',
			'Pneukarnik_Holidays'              => 'includes/class-holidays.php',
			'Pneukarnik_Slot_Engine'           => 'includes/class-slot-engine.php',
			'Pneukarnik_Booking'               => 'includes/class-booking.php',
			'Pneukarnik_Booking_Pages'         => 'includes/class-booking-pages.php',
			'Pneukarnik_Cancellation'          => 'includes/class-cancellation.php',
			'Pneukarnik_Rate_Limit'            => 'includes/class-rate-limit.php',
			'Pneukarnik_Access'                => 'includes/class-access.php',
			'Pneukarnik_Rest_Admin'            => 'api/class-rest-admin.php',
			'Pneukarnik_Admin_Calendar'        => 'admin/class-admin-calendar.php',
			'Pneukarnik_Notifications'         => 'includes/class-notifications.php',
			'Pneukarnik_Email'                 => 'includes/class-email.php',
			'Pneukarnik_GDPR'                  => 'includes/class-gdpr.php',
			'Pneukarnik_Rest_Services'         => 'api/class-rest-services.php',
			'Pneukarnik_Rest_Slots'            => 'api/class-rest-slots.php',
			'Pneukarnik_Rest_Available_Days'   => 'api/class-rest-available-days.php',
			'Pneukarnik_Rest_Bookings'         => 'api/class-rest-bookings.php',
			'Pneukarnik_Rest_Cancel'           => 'api/class-rest-cancel.php',
			'Pneukarnik_Rest_Prefill'          => 'api/class-rest-prefill.php',
			'Pneukarnik_Prefill'               => 'includes/class-prefill.php',
			'Pneukarnik_Rest_Calendar'         => 'api/class-rest-calendar.php',
			'Pneukarnik_Rest_Booking_Ics'      => 'api/class-rest-booking-ics.php',
			'Pneukarnik_Ical'                  => 'includes/class-ical.php',
			'Pneukarnik_Admin_Settings'        => 'admin/class-admin-settings.php',
			'Pneukarnik_Admin_Customer_Emails' => 'admin/class-admin-customer-emails.php',
			'Pneukarnik_Admin_Bookings'        => 'admin/class-admin-bookings.php',
			'Pneukarnik_Day_Sheet'             => 'includes/class-day-sheet.php',
			'Pneukarnik_File_Response'         => 'api/class-file-response.php',
			'Pneukarnik_Admin_Service_Meta'    => 'admin/class-admin-service-meta.php',
			'Pneukarnik_Admin_Promotion_Meta'  => 'admin/class-admin-promotion-meta.php',
			'Pneukarnik_Admin_Notice_Meta'     => 'admin/class-admin-notice-meta.php',
			'Pneukarnik_Admin_Guide_Meta'      => 'admin/class-admin-guide-meta.php',
			'Pneukarnik_Admin_Day_Exceptions'  => 'admin/class-admin-day-exceptions.php',
			'Pneukarnik_Legacy_Import'         => 'includes/class-legacy-import.php',
			'Pneukarnik_Old_Urls'              => 'includes/class-old-urls.php',
			'Pneukarnik_Rest_Legacy_Import'    => 'api/class-rest-legacy-import.php',
			'Pneukarnik_Admin_Legacy_Import'   => 'admin/class-admin-legacy-import.php',
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
}

function pneukarnik_deactivate(): void {
	Pneukarnik_DB::deactivate();
	// Bez pluginu nesmí zůstat adresy /pneuservis/ a /autoservis/. WordPress pravidla vytvoří znovu.
	delete_option( 'rewrite_rules' );
	delete_option( 'pneukarnik_rewrite_version' );
	wp_clear_scheduled_hook( Pneukarnik_GDPR::CRON_HOOK );
	wp_clear_scheduled_hook( Pneukarnik_Reviews::CRON_HOOK );
	wp_clear_scheduled_hook( Pneukarnik_Reminder::CRON_HOOK );
	wp_clear_scheduled_hook( Pneukarnik_Termin_Reminder::CRON_HOOK );
	wp_clear_scheduled_hook( Pneukarnik_Review_Request::CRON_HOOK );
	wp_clear_scheduled_hook( Pneukarnik_Mailing::CRON_HOOK );
}

function pneukarnik_ensure_capabilities(): void {
	Pneukarnik_Access::ensure();
}

// GDPR
add_action( 'plugins_loaded', [ 'Pneukarnik_GDPR', 'init' ] );
Pneukarnik_Reviews::init();
Pneukarnik_Reminder::init();
Pneukarnik_Termin_Reminder::init();
Pneukarnik_Review_Request::init();
Pneukarnik_Mailing::init();

// Bootstrap
add_action( 'plugins_loaded', [ 'Pneukarnik_DB', 'maybe_upgrade' ] );
add_action( 'plugins_loaded', 'pneukarnik_ensure_capabilities' );
Pneukarnik_Service_Type::init();
Pneukarnik_Promotion_Type::init();
Pneukarnik_Notice_Type::init();
Pneukarnik_Guide_Type::init();
Pneukarnik_Booking_Pages::init();
Pneukarnik_Old_Urls::init();
Pneukarnik_Seo::init();
add_action( 'init', [ 'Pneukarnik_Admin_Service_Meta', 'init' ] );
add_action( 'init', [ 'Pneukarnik_Admin_Promotion_Meta', 'init' ] );
add_action( 'init', [ 'Pneukarnik_Admin_Notice_Meta', 'init' ] );
add_action( 'init', [ 'Pneukarnik_Admin_Guide_Meta', 'init' ] );
add_action( 'rest_api_init', 'pneukarnik_register_rest_routes' );
add_action( 'admin_menu', 'pneukarnik_register_admin_menus' );

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
	Pneukarnik_File_Response::init();
	( new Pneukarnik_Rest_Services() )->register_routes();
	( new Pneukarnik_Rest_Slots() )->register_routes();
	( new Pneukarnik_Rest_Available_Days() )->register_routes();
	( new Pneukarnik_Rest_Bookings() )->register_routes();
	( new Pneukarnik_Rest_Cancel() )->register_routes();
	( new Pneukarnik_Rest_Prefill() )->register_routes();
	( new Pneukarnik_Rest_Calendar() )->register_routes();
	( new Pneukarnik_Rest_Booking_Ics() )->register_routes();
	( new Pneukarnik_Rest_Admin() )->register_routes();
	( new Pneukarnik_Rest_Reminder() )->register_routes();
	( new Pneukarnik_Rest_Legacy_Import() )->register_routes();
}

function pneukarnik_register_admin_menus(): void {
	add_menu_page(
		__( 'Rezervace', 'pneukarnik-booking' ),
		__( 'Rezervace', 'pneukarnik-booking' ),
		Pneukarnik_Access::VIEW,
		Pneukarnik_Admin_Calendar::PAGE,
		[ 'Pneukarnik_Admin_Calendar', 'render_page' ],
		'dashicons-car',
		30
	);

	$calendar_hook = add_submenu_page(
		Pneukarnik_Admin_Calendar::PAGE,
		__( 'Kalendář', 'pneukarnik-booking' ),
		__( 'Kalendář', 'pneukarnik-booking' ),
		Pneukarnik_Access::VIEW,
		Pneukarnik_Admin_Calendar::PAGE,
		[ 'Pneukarnik_Admin_Calendar', 'render_page' ]
	);
	if ( $calendar_hook ) {
		add_action( "admin_print_scripts-{$calendar_hook}", [ 'Pneukarnik_Admin_Calendar', 'enqueue' ] );
	}

	add_submenu_page(
		Pneukarnik_Admin_Calendar::PAGE,
		__( 'Seznam rezervací', 'pneukarnik-booking' ),
		__( 'Seznam', 'pneukarnik-booking' ),
		Pneukarnik_Access::VIEW,
		Pneukarnik_Admin_Bookings::PAGE,
		[ 'Pneukarnik_Admin_Bookings', 'render_page' ]
	);

	$customer_emails_hook = add_submenu_page(
		Pneukarnik_Admin_Calendar::PAGE,
		__( 'E‑maily Zákazníkům', 'pneukarnik-booking' ),
		__( 'E‑maily Zákazníkům', 'pneukarnik-booking' ),
		'manage_options',
		Pneukarnik_Admin_Customer_Emails::PAGE,
		[ 'Pneukarnik_Admin_Customer_Emails', 'render_page' ]
	);
	if ( $customer_emails_hook ) {
		add_action( "load-{$customer_emails_hook}", [ 'Pneukarnik_Admin_Customer_Emails', 'handle_post' ] );
	}

	$settings_hook = add_submenu_page(
		Pneukarnik_Admin_Calendar::PAGE,
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
		Pneukarnik_Admin_Calendar::PAGE,
		__( 'Výjimky', 'pneukarnik-booking' ),
		__( 'Výjimky', 'pneukarnik-booking' ),
		'manage_options',
		Pneukarnik_Admin_Day_Exceptions::PAGE,
		[ 'Pneukarnik_Admin_Day_Exceptions', 'render_page' ]
	);
	if ( $exceptions_hook ) {
		add_action( "load-{$exceptions_hook}", [ 'Pneukarnik_Admin_Day_Exceptions', 'handle_post' ] );
	}

	$import_hook = add_submenu_page(
		Pneukarnik_Admin_Calendar::PAGE,
		__( 'Převod ze starého webu', 'pneukarnik-booking' ),
		__( 'Převod ze starého webu', 'pneukarnik-booking' ),
		'manage_options',
		Pneukarnik_Admin_Legacy_Import::PAGE,
		[ 'Pneukarnik_Admin_Legacy_Import', 'render_page' ]
	);
	if ( $import_hook ) {
		add_action( "load-{$import_hook}", [ 'Pneukarnik_Admin_Legacy_Import', 'handle_post' ] );
	}
}

/**
 * Telefon Provozovatele z Nastavení, jak se má zobrazit (např. „+420 775 565 326“).
 */
function pneukarnik_phone(): string {
	return Pneukarnik_Contact::phone();
}

/**
 * Důvody „proč k nám“ pro Úvod z Nastavení, jeden na řádek ve tvaru „Nadpis | text“. Text je
 * nepovinný, řádek bez „|“ je jen nadpis. Řádek bez nadpisu použije text jako nadpis.
 *
 * @return list<array{title:string,text:string}>
 */
function pneukarnik_why_us(): array {
	$reasons = [];
	foreach ( explode( "\n", (string) get_option( 'pneukarnik_why_us', '' ) ) as $line ) {
		[ $title, $text ] = array_map( 'trim', explode( '|', $line, 2 ) ) + [ '', '' ];
		if ( '' === $title ) {
			[ $title, $text ] = [ $text, '' ];
		}
		if ( '' !== $title ) {
			$reasons[] = [
				'title' => $title,
				'text'  => $text,
			];
		}
	}
	return $reasons;
}

/**
 * Rok založení z Nastavení pro Úvod („Znojmo · od roku 1991“), null = nezadaný nebo nesmyslný.
 */
function pneukarnik_founded_year(): ?int {
	$year = trim( (string) get_option( 'pneukarnik_founded_year', '' ) );
	if ( ! preg_match( '/^\d{4}$/', $year ) ) {
		return null;
	}
	return (int) $year >= 1900 && (int) $year <= (int) Pneukarnik_Clock::today()->format( 'Y' ) ? (int) $year : null;
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
