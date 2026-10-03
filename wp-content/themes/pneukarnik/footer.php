<?php
/**
 * Patička stránky: kontaktní a fakturační údaje a sociální sítě z Nastavení, Průvodci
 * a Ochrana osobních údajů.
 *
 * @package Pneukarnik
 */

$pneukarnik_guides = Pneukarnik_Guide::published();
$pneukarnik_social = Pneukarnik_Contact::social();
$pneukarnik_phone  = Pneukarnik_Contact::phone();
$pneukarnik_email  = Pneukarnik_Contact::email();
$pneukarnik_ico    = Pneukarnik_Contact::ico();
$pneukarnik_dic    = Pneukarnik_Contact::dic();
?>
<footer class="site-footer">
	<address class="site-footer__kontakt">
		<strong><?php echo esc_html( Pneukarnik_Contact::company() ); ?></strong><br>
		<?php if ( '' !== Pneukarnik_Contact::address() ) : ?>
			<?php echo esc_html( Pneukarnik_Contact::address() ); ?><br>
		<?php endif; ?>
		<?php if ( '' !== $pneukarnik_phone ) : ?>
			<?php esc_html_e( 'Tel.:', 'pneukarnik' ); ?> <a href="<?php echo esc_url( pneukarnik_tel_href( $pneukarnik_phone ) ); ?>"><?php echo esc_html( $pneukarnik_phone ); ?></a><br>
		<?php endif; ?>
		<?php if ( '' !== $pneukarnik_email ) : ?>
			<?php esc_html_e( 'E‑mail:', 'pneukarnik' ); ?> <a href="<?php echo esc_url( 'mailto:' . $pneukarnik_email ); ?>"><?php echo esc_html( $pneukarnik_email ); ?></a><br>
		<?php endif; ?>
		<?php if ( '' !== $pneukarnik_ico ) : ?>
			<?php esc_html_e( 'IČ:', 'pneukarnik' ); ?> <?php echo esc_html( $pneukarnik_ico ); ?>
		<?php endif; ?>
		<?php if ( '' !== $pneukarnik_dic ) : ?>
			<?php esc_html_e( 'DIČ:', 'pneukarnik' ); ?> <?php echo esc_html( $pneukarnik_dic ); ?>
		<?php endif; ?>
	</address>
	<?php if ( $pneukarnik_guides ) : ?>
		<nav class="site-footer__pruvodci" aria-label="<?php esc_attr_e( 'Průvodci', 'pneukarnik' ); ?>">
			<p><strong><?php esc_html_e( 'Průvodci', 'pneukarnik' ); ?></strong></p>
			<ul>
				<?php foreach ( $pneukarnik_guides as $pneukarnik_guide ) : ?>
					<li><a href="<?php echo esc_url( $pneukarnik_guide->url() ); ?>"><?php echo esc_html( $pneukarnik_guide->title ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</nav>
	<?php endif; ?>
	<?php if ( $pneukarnik_social ) : ?>
		<ul class="site-footer__socialni-site" aria-label="<?php esc_attr_e( 'Sociální sítě', 'pneukarnik' ); ?>">
			<?php foreach ( $pneukarnik_social as $pneukarnik_network => $pneukarnik_link ) : ?>
				<li><a class="site-footer__sit--<?php echo esc_attr( $pneukarnik_network ); ?>" href="<?php echo esc_url( $pneukarnik_link['url'] ); ?>" rel="noopener" target="_blank"><?php echo esc_html( $pneukarnik_link['label'] ); ?></a></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
	<nav class="site-footer__odkazy" aria-label="<?php esc_attr_e( 'Odkazy v patičce', 'pneukarnik' ); ?>">
		<a href="<?php echo esc_url( home_url( '/o-nas/' ) ); ?>"><?php esc_html_e( 'O nás', 'pneukarnik' ); ?></a>
		<a href="<?php echo esc_url( home_url( '/kontakt/' ) ); ?>"><?php esc_html_e( 'Kontakt', 'pneukarnik' ); ?></a>
		<a href="<?php echo esc_url( home_url( '/ochrana-osobnich-udaju/' ) ); ?>"><?php esc_html_e( 'Ochrana osobních údajů', 'pneukarnik' ); ?></a>
	</nav>
</footer>
<?php wp_footer(); ?>
</body>
</html>
