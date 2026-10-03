<?php
/**
 * Pomocné funkce pro šablony.
 *
 * @package Pneukarnik
 */

declare(strict_types=1);

/**
 * Cena Služby pro web: „600 Kč“, „od 600 Kč“, nebo „Cena dle vozu“.
 */
function pneukarnik_price_label( Pneukarnik_Service $service ): string {
	if ( $service->price_by_vehicle || null === $service->price ) {
		return __( 'Cena dle vozu', 'pneukarnik' );
	}
	$amount = number_format( $service->price, 0, ',', "\u{00A0}" ) . "\u{00A0}Kč";
	/* translators: %s: částka, např. 600 Kč */
	return $service->price_from ? sprintf( __( 'od %s', 'pneukarnik' ), $amount ) : $amount;
}

/**
 * Odkaz tel: z telefonu ve tvaru pro lidi.
 */
function pneukarnik_tel_href( string $phone ): string {
	return 'tel:' . preg_replace( '/[^0-9+]/', '', $phone );
}

/**
 * Výzva k akci u Služby: Rezervovat (jen u online rezervovatelných) a Zavolat.
 */
function pneukarnik_service_cta( Pneukarnik_Service $service ): void {
	$phone = function_exists( 'pneukarnik_phone' ) ? pneukarnik_phone() : '';
	?>
	<div class="cta">
		<?php if ( $service->bookable ) : ?>
			<a class="cta__rezervovat" href="<?php echo esc_url( add_query_arg( 'sluzba', $service->slug, home_url( '/rezervace/' ) ) ); ?>"><?php esc_html_e( 'Rezervovat', 'pneukarnik' ); ?></a>
		<?php endif; ?>
		<?php if ( '' !== $phone ) : ?>
			<a class="cta__zavolat" href="<?php echo esc_url( pneukarnik_tel_href( $phone ) ); ?>">
				<?php
				/* translators: %s: telefonní číslo */
				echo esc_html( sprintf( __( 'Zavolat %s', 'pneukarnik' ), $phone ) );
				?>
			</a>
		<?php endif; ?>
	</div>
	<?php
}
