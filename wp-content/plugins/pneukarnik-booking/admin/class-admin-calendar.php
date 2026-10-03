<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Kalendář Rezervací v administraci: den a týden, klik na volné místo = telefonická
 * objednávka, klik na Rezervaci = detail, úprava a Zrušení. Vše přes REST
 * (Pneukarnik_Rest_Admin), tady je jen kostra stránky a konfigurace pro assets/admin-calendar.js.
 */
final class Pneukarnik_Admin_Calendar {

	public const PAGE = 'pneukarnik-booking';

	public static function enqueue(): void {
		$base = PNEUKARNIK_PLUGIN_DIR . 'assets/';
		wp_enqueue_style( 'pneukarnik-admin-calendar', PNEUKARNIK_PLUGIN_URL . 'assets/admin-calendar.css', [], (string) filemtime( $base . 'admin-calendar.css' ) );
		wp_enqueue_script(
			'pneukarnik-admin-calendar',
			PNEUKARNIK_PLUGIN_URL . 'assets/admin-calendar.js',
			[],
			(string) filemtime( $base . 'admin-calendar.js' ),
			[
				'strategy'  => 'defer',
				'in_footer' => true,
			]
		);
	}

	public static function url( string $date = '', int $booking_id = 0 ): string {
		return add_query_arg(
			array_filter(
				[
					'page'       => self::PAGE,
					'date'       => $date,
					'booking_id' => $booking_id,
				]
			),
			admin_url( 'admin.php' )
		);
	}

	public static function render_page(): void {
		if ( ! Pneukarnik_Access::can_view() ) {
			wp_die( esc_html__( 'Nemáte oprávnění.', 'pneukarnik-booking' ) );
		}
		?>
		<div class="wrap pnk-cal-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Kalendář rezervací', 'pneukarnik-booking' ); ?></h1>
			<a class="page-title-action" href="<?php echo esc_url( Pneukarnik_Admin_Bookings::url() ); ?>"><?php esc_html_e( 'Seznam', 'pneukarnik-booking' ); ?></a>
			<hr class="wp-header-end">
			<script type="application/json" id="pnk-cal-config"><?php echo wp_json_encode( self::config(), JSON_HEX_TAG | JSON_HEX_AMP ); ?></script>
			<div id="pnk-cal" class="pnk-cal" aria-live="polite">
				<noscript><p><?php esc_html_e( 'Kalendář potřebuje zapnutý JavaScript. Rezervace najdete i v Seznamu.', 'pneukarnik-booking' ); ?></p></noscript>
			</div>
		</div>
		<?php
	}

	/**
	 * @return array{api:string,nonce:string,can_manage:bool,grid_step:int,today:string,date:string,booking_id:int,services:list<array{id:int,name:string,duration:int,online:bool}>}
	 */
	private static function config(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- jen výchozí den a otevřená Rezervace.
		$date       = isset( $_GET['date'] ) ? sanitize_text_field( wp_unslash( $_GET['date'] ) ) : '';
		$booking_id = isset( $_GET['booking_id'] ) ? absint( $_GET['booking_id'] ) : 0;
		// phpcs:enable
		$today = Pneukarnik_Clock::today()->format( 'Y-m-d' );

		return [
			'api'        => rest_url( PNEUKARNIK_REST_NAMESPACE ),
			'nonce'      => wp_create_nonce( 'wp_rest' ),
			'can_manage' => Pneukarnik_Access::can_manage(),
			'grid_step'  => Pneukarnik_Working_Hours::get_grid_step(),
			'today'      => $today,
			'date'       => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? $date : $today,
			'booking_id' => $booking_id,
			'services'   => array_map(
				static fn( Pneukarnik_Service $s ): array => [
					'id'       => $s->id,
					'name'     => $s->title,
					'duration' => $s->duration,
					'online'   => $s->bookable,
				],
				array_values( array_filter( Pneukarnik_Service::published(), static fn( Pneukarnik_Service $s ): bool => $s->duration > 0 ) )
			),
		];
	}
}
