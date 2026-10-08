<?php
/**
 * Nastavení e‑mailů z Nabídek a připomínek (/odhlaseni/?k=…): zvlášť Připomínka přezutí a Akce,
 * nebo Neposílat nic. „Ano, posílejte“ z potvrzení Rezervace (/odhlaseni/?s=…) se zeptá a po stisku
 * tlačítka ukáže nastavení. Odkazy odeslané dřív odhlásí hned při otevření: z Připomínky přezutí
 * (/odhlaseni/?t=…, pak ukáže nastavení) a ze „informací o slevách“ (/cancel-subscription?email=…).
 * Stav dodává a formulář zpracuje plugin (Pneukarnik_Booking_Pages::email_settings).
 * Bílá karta na přechodu: nastavení, nabídka, ✓ a Odhlášeno, nebo Notice s telefonem a e‑mailem.
 *
 * @package Pneukarnik
 */

$pneukarnik_result   = Pneukarnik_Booking_Pages::email_settings();
$pneukarnik_settings = $pneukarnik_result['settings'];
$pneukarnik_offer    = $pneukarnik_result['offer'];
$pneukarnik_phone    = Pneukarnik_Contact::phone();
$pneukarnik_email    = Pneukarnik_Contact::email();
$pneukarnik_notices  = [
	'reminder_off' => __( 'Hotovo, Připomínky přezutí vám už posílat nebudeme.', 'pneukarnik' ),
	'saved'        => __( 'Uloženo.', 'pneukarnik' ),
	'nothing'      => __( 'Hotovo, nebudeme vám posílat nic.', 'pneukarnik' ),
	'consented'    => __( 'Děkujeme, budeme vám posílat Připomínku přezutí a naše akce.', 'pneukarnik' ),
];

get_header();
?>
<main id="obsah" class="site-main vysledek">
	<div class="vysledek__inner">
		<div class="card vysledek__karta">
			<?php if ( null !== $pneukarnik_settings ) : ?>
				<?php if ( '' !== $pneukarnik_result['notice'] ) : ?>
					<span class="vysledek__ikona" aria-hidden="true">✓</span>
				<?php endif; ?>
				<h1><?php esc_html_e( 'E‑maily od nás', 'pneukarnik' ); ?></h1>
				<?php if ( '' !== $pneukarnik_result['notice'] ) : ?>
					<p class="vysledek__perex vysledek__perex--tmavy" role="status"><?php echo esc_html( $pneukarnik_notices[ $pneukarnik_result['notice'] ] ); ?></p>
				<?php endif; ?>
				<p class="vysledek__text">
					<?php
					/* translators: %s: e‑mail Zákazníka */
					echo esc_html( sprintf( __( 'Co smíme posílat na %s:', 'pneukarnik' ), $pneukarnik_settings['email'] ) );
					?>
				</p>
				<form class="vysledek__formular" method="post" action="<?php echo esc_url( $pneukarnik_settings['url'] ); ?>">
					<div class="zaskrtavaci">
						<input type="checkbox" name="reminder" value="1" id="nastaveni-reminder"<?php checked( $pneukarnik_settings['reminder'] ); ?>>
						<label for="nastaveni-reminder"><?php esc_html_e( 'Připomínku přezutí před každou sezónou', 'pneukarnik' ); ?></label>
					</div>
					<div class="zaskrtavaci">
						<input type="checkbox" name="promotions" value="1" id="nastaveni-promotions"<?php checked( $pneukarnik_settings['promotions'] ); ?>>
						<label for="nastaveni-promotions"><?php esc_html_e( 'Naše akce', 'pneukarnik' ); ?></label>
					</div>
					<div class="vysledek__tlacitka">
						<button type="submit" class="button" name="volba" value="ulozit"><?php esc_html_e( 'Uložit', 'pneukarnik' ); ?></button>
						<button type="submit" class="button button--secondary" name="volba" value="nic"><?php esc_html_e( 'Neposílat nic', 'pneukarnik' ); ?></button>
					</div>
				</form>
				<p class="vysledek__poznamka"><?php esc_html_e( '„Neposílat nic“ vypne i jednorázovou prosbu o hodnocení na Googlu. E‑maily k vašim rezervacím chodí dál.', 'pneukarnik' ); ?></p>
			<?php elseif ( null !== $pneukarnik_offer ) : ?>
				<h1><?php esc_html_e( 'E‑maily od nás', 'pneukarnik' ); ?></h1>
				<p class="vysledek__text">
					<?php
					/* translators: %s: e‑mail Zákazníka */
					echo esc_html( sprintf( __( 'Chcete na %s před každou sezónou připomenout přezutí a dostávat naše akce?', 'pneukarnik' ), $pneukarnik_offer['email'] ) );
					?>
				</p>
				<form class="vysledek__formular" method="post" action="<?php echo esc_url( $pneukarnik_offer['url'] ); ?>">
					<div class="vysledek__tlacitka">
						<button type="submit" class="button" name="volba" value="ano"><?php esc_html_e( 'Ano, posílejte', 'pneukarnik' ); ?></button>
					</div>
				</form>
				<p class="vysledek__poznamka"><?php esc_html_e( 'Jednou po první návštěvě vás poprosíme i o hodnocení na Googlu. Co vám posíláme, nastavíte odkazem v každém z těchto e‑mailů.', 'pneukarnik' ); ?></p>
			<?php elseif ( 'legacy' === $pneukarnik_result['state'] ) : ?>
				<span class="vysledek__ikona" aria-hidden="true">✓</span>
				<h1><?php esc_html_e( 'Odhlášeno', 'pneukarnik' ); ?></h1>
				<p class="vysledek__perex vysledek__perex--tmavy" role="status"><?php esc_html_e( 'Hotovo, informace o slevách vám už posílat nebudeme.', 'pneukarnik' ); ?></p>
			<?php else : ?>
				<h1><?php esc_html_e( 'Odkaz nefunguje', 'pneukarnik' ); ?></h1>
				<p class="notice notice--danger" role="alert"><?php esc_html_e( 'Odkaz na nastavení e‑mailů je neplatný nebo už vypršel.', 'pneukarnik' ); ?></p>
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
