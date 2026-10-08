<?php
/**
 * Odhlášení z e‑mailů odkazem: z Připomínky přezutí (/odhlaseni/?t=…), nebo starým odkazem
 * ze „informací o slevách“ (/cancel-subscription?email=…). Odhlásí hned při otevření,
 * výsledek dodává plugin (Pneukarnik_Booking_Pages::unsubscription).
 * Bílá karta na přechodu: ✓ a Odhlášeno, nebo Notice s telefonem a e‑mailem.
 *
 * @package Pneukarnik
 */

$pneukarnik_result = Pneukarnik_Booking_Pages::unsubscription();
$pneukarnik_phone  = Pneukarnik_Contact::phone();
$pneukarnik_email  = Pneukarnik_Contact::email();

get_header();
?>
<main id="obsah" class="site-main vysledek">
	<div class="vysledek__inner">
		<div class="card vysledek__karta">
			<?php if ( Pneukarnik_Subscriptions::DONE === $pneukarnik_result['code'] ) : ?>
				<span class="vysledek__ikona" aria-hidden="true">✓</span>
				<h1><?php esc_html_e( 'Odhlášeno', 'pneukarnik' ); ?></h1>
				<p class="vysledek__perex vysledek__perex--tmavy" role="status">
					<?php
					echo esc_html(
						$pneukarnik_result['legacy']
							? __( 'Hotovo, informace o slevách vám už posílat nebudeme.', 'pneukarnik' )
							: __( 'Hotovo, Připomínky přezutí vám už posílat nebudeme.', 'pneukarnik' )
					);
					?>
				</p>
				<p class="vysledek__poznamka"><?php esc_html_e( 'Kdybyste si to rozmysleli, stačí při příští online rezervaci nezaškrtnout „Neposílat nabídky a připomínky“.', 'pneukarnik' ); ?></p>
			<?php else : ?>
				<h1><?php esc_html_e( 'Odhlášení se nepovedlo', 'pneukarnik' ); ?></h1>
				<p class="notice notice--danger" role="alert"><?php esc_html_e( 'Odkaz pro odhlášení je neplatný nebo už vypršel.', 'pneukarnik' ); ?></p>
				<?php if ( '' !== $pneukarnik_phone || '' !== $pneukarnik_email ) : ?>
					<p class="vysledek__poznamka"><?php esc_html_e( 'Odhlásíme vás i sami, stačí zavolat nebo napsat.', 'pneukarnik' ); ?></p>
					<p class="vysledek__kontakt">
						<?php if ( '' !== $pneukarnik_phone ) : ?>
							<a class="vysledek__telefon" href="<?php echo esc_url( pneukarnik_tel_href( $pneukarnik_phone ) ); ?>"><span aria-hidden="true">☎ </span><?php echo esc_html( $pneukarnik_phone ); ?></a>
						<?php endif; ?>
						<?php if ( '' !== $pneukarnik_email ) : ?>
							<a class="vysledek__email" href="<?php echo esc_url( 'mailto:' . $pneukarnik_email ); ?>"><?php echo esc_html( $pneukarnik_email ); ?></a>
						<?php endif; ?>
					</p>
				<?php endif; ?>
			<?php endif; ?>

			<a class="arrow-link arrow-link--back" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Zpět na úvod', 'pneukarnik' ); ?></a>
		</div>
	</div>
</main>
<?php
get_footer();
