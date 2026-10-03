<?php
/**
 * Patička stránky: kontaktní a fakturační údaje z Nastavení.
 *
 * @package Pneukarnik
 */

$pneukarnik_phone = Pneukarnik_Contact::phone();
$pneukarnik_email = Pneukarnik_Contact::email();
$pneukarnik_ico   = Pneukarnik_Contact::ico();
$pneukarnik_dic   = Pneukarnik_Contact::dic();
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
</footer>
<?php wp_footer(); ?>
</body>
</html>
