<?php
/**
 * Potvrzení Rezervace. Rezervaci najde plugin podle tokenu v adrese (bez osobních údajů v URL).
 *
 * @package Pneukarnik
 */

$pneukarnik_booking = Pneukarnik_Booking_Pages::confirmed_booking();
$pneukarnik_start   = Pneukarnik_Clock::at( $pneukarnik_booking['booking_date'] . ' ' . $pneukarnik_booking['time_start'] );

get_header();
?>
<main id="obsah" class="site-main rezervace-potvrzeni">
	<h1><?php esc_html_e( 'Rezervace přijata', 'pneukarnik' ); ?></h1>
	<p><?php esc_html_e( 'Děkujeme, těšíme se na vás. Potvrzení jsme poslali také e‑mailem.', 'pneukarnik' ); ?></p>
	<dl class="rezervace-potvrzeni__udaje">
		<dt><?php esc_html_e( 'Termín', 'pneukarnik' ); ?></dt>
		<dd><?php echo esc_html( wp_date( 'l j. n. Y \v G:i', $pneukarnik_start->getTimestamp(), Pneukarnik_Clock::timezone() ) ); ?></dd>
		<dt><?php esc_html_e( 'Služba', 'pneukarnik' ); ?></dt>
		<dd><?php echo esc_html( $pneukarnik_booking['service_name'] ); ?></dd>
		<dt><?php esc_html_e( 'SPZ', 'pneukarnik' ); ?></dt>
		<dd><?php echo esc_html( $pneukarnik_booking['customer_plate'] ); ?></dd>
	</dl>
	<p><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Zpět na úvod', 'pneukarnik' ); ?></a></p>
</main>
<?php
get_footer();
