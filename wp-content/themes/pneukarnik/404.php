<?php
/**
 * Stránka nenalezena: cesta zpět na Úvod, ke Službám, k rezervaci a telefon.
 *
 * @package Pneukarnik
 */

$pneukarnik_phone = Pneukarnik_Contact::phone();

get_header();
?>
<main id="obsah" class="site-main chyba">
	<div class="chyba__inner">
		<p class="eyebrow"><?php esc_html_e( 'Chyba 404', 'pneukarnik' ); ?></p>
		<h1><?php esc_html_e( 'Stránka nenalezena', 'pneukarnik' ); ?></h1>
		<p class="chyba__text"><?php esc_html_e( 'Adresa je možná překlepnutá, nebo stránka už neexistuje.', 'pneukarnik' ); ?></p>
		<a class="button" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Zpět na úvod', 'pneukarnik' ); ?></a>
		<p class="chyba__odkazy">
			<a class="arrow-link" href="<?php echo esc_url( home_url( '/#sluzby' ) ); ?>"><?php esc_html_e( 'Služby', 'pneukarnik' ); ?></a>
			<a class="arrow-link" href="<?php echo esc_url( home_url( '/rezervace/' ) ); ?>"><?php esc_html_e( 'Rezervace', 'pneukarnik' ); ?></a>
			<?php if ( '' !== $pneukarnik_phone ) : ?>
				<a href="<?php echo esc_url( pneukarnik_tel_href( $pneukarnik_phone ) ); ?>"><span aria-hidden="true">☎ </span><?php echo esc_html( $pneukarnik_phone ); ?></a>
			<?php endif; ?>
		</p>
	</div>
</main>
<?php
get_footer();
