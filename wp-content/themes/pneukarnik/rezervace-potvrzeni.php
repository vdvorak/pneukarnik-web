<?php
/**
 * Potvrzení Rezervace. Rezervaci najde plugin podle tokenu v adrese (bez osobních údajů v URL).
 * Bílá karta na přechodu s ✓, souhrnem, tlačítkem „Přidat do kalendáře“ (.ics) a odkazem zpět na Úvod.
 *
 * @package Pneukarnik
 */

$pneukarnik_booking  = Pneukarnik_Booking_Pages::confirmed_booking();
$pneukarnik_services = array_column( $pneukarnik_booking['services'], 'name' );
$pneukarnik_rows     = [
	__( 'Termín', 'pneukarnik' ) => pneukarnik_format_day( $pneukarnik_booking['booking_date'] ) . ' v ' . Pneukarnik_Clock::at( $pneukarnik_booking['booking_date'] . ' ' . $pneukarnik_booking['time_start'] )->format( 'G:i' ),
	( count( $pneukarnik_services ) > 1 ? __( 'Služby', 'pneukarnik' ) : __( 'Služba', 'pneukarnik' ) ) => implode( ', ', $pneukarnik_services ),
	__( 'SPZ', 'pneukarnik' )    => $pneukarnik_booking['customer_plate'],
];
if ( $pneukarnik_booking['leasing'] ) {
	$pneukarnik_rows[ __( 'Leasing', 'pneukarnik' ) ] = $pneukarnik_booking['leasing_company'];
}
if ( $pneukarnik_booking['stored_wheels'] ) {
	$pneukarnik_rows[ __( 'Kola', 'pneukarnik' ) ] = __( 'uskladněná u nás', 'pneukarnik' );
}

get_header();
?>
<main id="obsah" class="site-main vysledek">
	<div class="vysledek__inner">
		<div class="card vysledek__karta">
			<span class="vysledek__ikona" aria-hidden="true">✓</span>
			<h1><?php esc_html_e( 'Rezervace přijata', 'pneukarnik' ); ?></h1>
			<p class="vysledek__perex"><?php esc_html_e( 'Děkujeme, těšíme se na vás. Potvrzení jsme poslali také e‑mailem.', 'pneukarnik' ); ?></p>
			<?php pneukarnik_booking_summary( $pneukarnik_rows ); ?>
			<div class="vysledek__tlacitka">
				<a class="button" href="<?php echo esc_url( Pneukarnik_Booking_Pages::confirmation_calendar_url() ); ?>" download="rezervace.ics"><?php echo pneukarnik_icon( 'calendar-plus' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG ze šablony. ?><?php esc_html_e( 'Přidat do kalendáře', 'pneukarnik' ); ?></a>
			</div>
			<a class="arrow-link arrow-link--back" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Zpět na úvod', 'pneukarnik' ); ?></a>
		</div>
	</div>
</main>
<?php
get_footer();
