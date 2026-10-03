<?php
/**
 * Odhlášení z e‑mailů odkazem: z Připomínky přezutí (/odhlaseni/?t=…), nebo starým odkazem
 * ze „informací o slevách“ (/cancel-subscription?email=…). Odhlásí hned při otevření,
 * výsledek dodává plugin (Pneukarnik_Booking_Pages::unsubscription).
 *
 * @package Pneukarnik
 */

$pneukarnik_result = Pneukarnik_Booking_Pages::unsubscription();
$pneukarnik_phone  = Pneukarnik_Contact::phone();
$pneukarnik_email  = Pneukarnik_Contact::email();

get_header();
?>
<main id="obsah" class="site-main odhlaseni">
	<h1><?php esc_html_e( 'Odhlášení z e‑mailů', 'pneukarnik' ); ?></h1>

	<?php if ( Pneukarnik_Subscriptions::DONE === $pneukarnik_result['code'] ) : ?>
		<p role="status">
			<?php
			echo esc_html(
				$pneukarnik_result['legacy']
					? __( 'Hotovo, informace o slevách vám už posílat nebudeme.', 'pneukarnik' )
					: __( 'Hotovo, Připomínky přezutí vám už posílat nebudeme.', 'pneukarnik' )
			);
			?>
		</p>
		<p><?php esc_html_e( 'Kdybyste si to rozmysleli, stačí při příští rezervaci zaškrtnout souhlas s připomínkou.', 'pneukarnik' ); ?></p>
	<?php else : ?>
		<p role="alert"><?php esc_html_e( 'Odkaz pro odhlášení je neplatný. Zkontrolujte, že jste ho z e‑mailu zkopírovali celý, nebo se nám ozvěte a odhlásíme vás sami.', 'pneukarnik' ); ?></p>
		<?php if ( '' !== $pneukarnik_phone ) : ?>
			<p><a href="<?php echo esc_url( pneukarnik_tel_href( $pneukarnik_phone ) ); ?>"><?php echo esc_html( sprintf( /* translators: %s: telefon */ __( 'Zavolat %s', 'pneukarnik' ), $pneukarnik_phone ) ); ?></a></p>
		<?php endif; ?>
		<?php if ( '' !== $pneukarnik_email ) : ?>
			<p><a href="<?php echo esc_url( 'mailto:' . $pneukarnik_email ); ?>"><?php echo esc_html( $pneukarnik_email ); ?></a></p>
		<?php endif; ?>
	<?php endif; ?>

	<p><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Zpět na úvod', 'pneukarnik' ); ?></a></p>
</main>
<?php
get_footer();
