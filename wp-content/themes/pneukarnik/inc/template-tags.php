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
	$amount = pneukarnik_amount( $service->price );
	/* translators: %s: částka, např. 600 Kč */
	return $service->price_from ? sprintf( __( 'od %s', 'pneukarnik' ), $amount ) : $amount;
}

/**
 * Částka v Kč pro web, např. „1 200 Kč“ (s nezlomitelnými mezerami).
 */
function pneukarnik_amount( int $amount ): string {
	return number_format( $amount, 0, ',', "\u{00A0}" ) . "\u{00A0}Kč";
}

/**
 * Blok platné Akce v detailu Služby: název, akční cena, popis a do kdy platí.
 */
function pneukarnik_promotion_block( Pneukarnik_Promotion $promotion ): void {
	?>
	<section class="sluzba__akce">
		<p class="stitek-akce"><?php esc_html_e( 'Akce', 'pneukarnik' ); ?></p>
		<h2><?php echo esc_html( $promotion->title ); ?></h2>
		<p class="sluzba__akce-cena"><?php echo esc_html( pneukarnik_amount( (int) $promotion->price ) ); ?></p>
		<?php if ( '' !== $promotion->description ) : ?>
			<?php echo wp_kses_post( wpautop( esc_html( $promotion->description ) ) ); ?>
		<?php endif; ?>
		<p class="sluzba__akce-platnost">
			<?php
			/* translators: %s: poslední den platnosti Akce, např. 31. 3. 2027 */
			echo esc_html( sprintf( __( 'Akce platí do %s.', 'pneukarnik' ), Pneukarnik_Clock::at( $promotion->valid_to )->format( 'j. n. Y' ) ) );
			?>
		</p>
	</section>
	<?php
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
