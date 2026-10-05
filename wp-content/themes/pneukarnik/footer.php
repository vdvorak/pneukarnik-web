<?php
/**
 * Patička stránky: značka a sociální sítě, kontaktní a fakturační údaje z Nastavení, Průvodci
 * a odkazy. Na mobilu pod ní lišta Zavolat + Rezervovat.
 *
 * @package Pneukarnik
 */

$pneukarnik_guides  = Pneukarnik_Guide::published();
$pneukarnik_social  = Pneukarnik_Contact::social();
$pneukarnik_phone   = Pneukarnik_Contact::phone();
$pneukarnik_email   = Pneukarnik_Contact::email();
$pneukarnik_address = Pneukarnik_Contact::address();
$pneukarnik_city    = Pneukarnik_Contact::postal_address()['city'];
$pneukarnik_ids     = array_filter(
	[
		'' !== Pneukarnik_Contact::ico() ? __( 'IČ:', 'pneukarnik' ) . ' ' . Pneukarnik_Contact::ico() : '',
		'' !== Pneukarnik_Contact::dic() ? __( 'DIČ:', 'pneukarnik' ) . ' ' . Pneukarnik_Contact::dic() : '',
	]
);

[ $pneukarnik_prefix, $pneukarnik_name ] = pneukarnik_brand();
?>
<footer class="site-footer">
	<div class="site-footer__inner">
		<div class="site-footer__sloupce">
			<div class="site-footer__sloupec site-footer__znacka">
				<p class="site-footer__nazev"><span><?php echo esc_html( $pneukarnik_prefix ); ?></span> <?php echo esc_html( $pneukarnik_name ); ?></p>
				<p class="site-footer__popis"><?php echo esc_html( Pneukarnik_Contact::company() . ( '' !== $pneukarnik_city ? ', ' . $pneukarnik_city : '' ) ); ?></p>
				<?php if ( $pneukarnik_social ) : ?>
					<p class="site-footer__titulek"><?php esc_html_e( 'Sledujte nás', 'pneukarnik' ); ?></p>
					<ul class="site-footer__socialni" aria-label="<?php esc_attr_e( 'Sociální sítě', 'pneukarnik' ); ?>">
						<?php foreach ( $pneukarnik_social as $pneukarnik_network => $pneukarnik_link ) : ?>
							<li><a href="<?php echo esc_url( $pneukarnik_link['url'] ); ?>" rel="noopener" target="_blank" title="<?php echo esc_attr( $pneukarnik_link['label'] ); ?>"><?php pneukarnik_social_icon( $pneukarnik_network ); ?><span class="screen-reader-text"><?php echo esc_html( $pneukarnik_link['label'] ); ?></span></a></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>

			<address class="site-footer__sloupec">
				<span class="site-footer__titulek"><?php esc_html_e( 'Kontakt', 'pneukarnik' ); ?></span>
				<?php if ( '' !== $pneukarnik_address ) : ?>
					<span><?php echo esc_html( $pneukarnik_address ); ?></span>
				<?php endif; ?>
				<?php if ( '' !== $pneukarnik_phone ) : ?>
					<a href="<?php echo esc_url( pneukarnik_tel_href( $pneukarnik_phone ) ); ?>"><?php echo esc_html( $pneukarnik_phone ); ?></a>
				<?php endif; ?>
				<?php if ( '' !== $pneukarnik_email ) : ?>
					<a href="<?php echo esc_url( 'mailto:' . $pneukarnik_email ); ?>"><?php echo esc_html( $pneukarnik_email ); ?></a>
				<?php endif; ?>
				<?php if ( $pneukarnik_ids ) : ?>
					<span class="site-footer__vedlejsi"><?php echo esc_html( implode( ' · ', $pneukarnik_ids ) ); ?></span>
				<?php endif; ?>
			</address>

			<?php if ( $pneukarnik_guides ) : ?>
				<nav class="site-footer__sloupec" aria-labelledby="pruvodci-v-paticce">
					<span class="site-footer__titulek" id="pruvodci-v-paticce"><?php esc_html_e( 'Průvodci', 'pneukarnik' ); ?></span>
					<ul>
						<?php foreach ( $pneukarnik_guides as $pneukarnik_guide ) : ?>
							<li><a href="<?php echo esc_url( $pneukarnik_guide->url() ); ?>"><?php echo esc_html( $pneukarnik_guide->title ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				</nav>
			<?php endif; ?>

			<nav class="site-footer__sloupec" aria-labelledby="odkazy-v-paticce">
				<span class="site-footer__titulek" id="odkazy-v-paticce"><?php esc_html_e( 'Odkazy', 'pneukarnik' ); ?></span>
				<ul>
					<li><a href="<?php echo esc_url( home_url( '/o-nas/' ) ); ?>"><?php esc_html_e( 'O nás', 'pneukarnik' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/kontakt/' ) ); ?>"><?php esc_html_e( 'Kontakt', 'pneukarnik' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/ochrana-osobnich-udaju/' ) ); ?>"><?php esc_html_e( 'Ochrana osobních údajů', 'pneukarnik' ); ?></a></li>
				</ul>
			</nav>
		</div>

		<div class="site-footer__spodek">
			<p>© <?php echo esc_html( Pneukarnik_Clock::now()->format( 'Y' ) . ' ' . Pneukarnik_Contact::company() ); ?></p>
		</div>
	</div>
</footer>
<?php pneukarnik_mobile_bar(); ?>
<?php wp_footer(); ?>
</body>
</html>
