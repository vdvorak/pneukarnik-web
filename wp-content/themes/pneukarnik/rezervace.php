<?php
/**
 * Rezervace termínu: výběr jedné nebo víc Služeb, dne a Termínu, kontaktní údaje.
 * Data a pravidla dodává plugin (REST), tady je jen formulář. Vpravo souhrn a karta
 * „Raději zavoláte?“, na mobilu je souhrn nad tlačítkem Rezervovat.
 *
 * @package Pneukarnik
 */

$pneukarnik_config = Pneukarnik_Booking_Pages::form_config();
$pneukarnik_phone  = $pneukarnik_config['phone'];

/**
 * Náhradní stav místo formuláře: Oznámení k rezervaci a Notice s textem a tlačítkem Zavolat.
 */
$pneukarnik_fallback = static function ( string $text ) use ( $pneukarnik_phone ): void {
	pneukarnik_booking_notices();
	?>
	<div class="notice notice--info rezervace__nahrada">
		<p><?php echo esc_html( $text ); ?></p>
		<?php if ( '' !== $pneukarnik_phone ) : ?>
			<p><a class="button button--dark button--md" href="<?php echo esc_url( pneukarnik_tel_href( $pneukarnik_phone ) ); ?>"><?php echo esc_html( sprintf( /* translators: %s: telefon */ __( 'Zavolat %s', 'pneukarnik' ), $pneukarnik_phone ) ); ?></a></p>
		<?php endif; ?>
	</div>
	<?php
};

/**
 * Souhrn vybraných Služeb, Délky a Termínu. Vyplní ho rezervace.js, vykreslí se dvakrát
 * (vpravo a na mobilu nad tlačítkem), vidět je vždy jen jeden.
 */
$pneukarnik_summary = static function ( string $modifier ): void {
	?>
	<div class="souhrn souhrn--<?php echo esc_attr( $modifier ); ?>" data-souhrn>
		<p class="eyebrow eyebrow--accent"><?php esc_html_e( 'Vaše rezervace', 'pneukarnik' ); ?></p>
		<ul class="souhrn__sluzby" data-souhrn-sluzby>
			<li class="souhrn__prazdne"><?php esc_html_e( 'Zatím není vybraná žádná služba.', 'pneukarnik' ); ?></li>
		</ul>
		<dl class="souhrn__celkem">
			<div class="souhrn__delka"><dt><?php esc_html_e( 'Délka', 'pneukarnik' ); ?></dt><dd data-souhrn-delka>—</dd></div>
			<div><dt><?php esc_html_e( 'Termín', 'pneukarnik' ); ?></dt><dd class="souhrn__termin" data-souhrn-termin>—</dd></div>
		</dl>
	</div>
	<?php
};

get_header();
?>
<main id="obsah" class="site-main rezervace">
	<?php pneukarnik_page_hero( __( 'Rezervace termínu', 'pneukarnik' ), __( 'Vyberte službu, den a volný čas. Registrace není potřeba.', 'pneukarnik' ) ); ?>

	<?php if ( ! $pneukarnik_config['enabled'] ) : ?>
		<div class="rezervace__uzka">
			<?php $pneukarnik_fallback( $pneukarnik_config['disabled_message'] ); ?>
		</div>
	<?php elseif ( ! $pneukarnik_config['services'] ) : ?>
		<div class="rezervace__uzka">
			<?php
			$pneukarnik_fallback(
				'' !== $pneukarnik_phone
					/* translators: %s: telefon */
					? sprintf( __( 'Online teď nejde objednat žádná služba. Zavolejte nám na %s.', 'pneukarnik' ), $pneukarnik_phone )
					: __( 'Online teď nejde objednat žádná služba.', 'pneukarnik' )
			);
			?>
		</div>
	<?php else : ?>
		<script type="application/json" id="rezervace-config"><?php echo wp_json_encode( $pneukarnik_config, JSON_HEX_TAG | JSON_HEX_AMP ); ?></script>
		<noscript>
			<div class="rezervace__uzka">
				<?php
				$pneukarnik_fallback(
					'' !== $pneukarnik_phone
						/* translators: %s: telefon */
						? sprintf( __( 'Rezervace potřebuje zapnutý JavaScript. Objednat se můžete i telefonicky na %s.', 'pneukarnik' ), $pneukarnik_phone )
						: __( 'Rezervace potřebuje zapnutý JavaScript.', 'pneukarnik' )
				);
				?>
			</div>
		</noscript>

		<?php // Bez JavaScriptu formulář nefunguje, ukáže ho až rezervace.js. ?>
		<div class="rezervace__mrizka" id="rezervace" hidden>
			<div class="rezervace__hlavni">
				<?php pneukarnik_booking_notices(); ?>

				<form id="rezervace-form" class="card rezervace__form" novalidate>
					<?php
					$pneukarnik_service_options = static function ( int $selected ) use ( $pneukarnik_config ): void {
						?>
						<option value=""><?php esc_html_e( 'Vyberte službu', 'pneukarnik' ); ?></option>
						<?php foreach ( $pneukarnik_config['services'] as $pneukarnik_item ) : ?>
							<?php $pneukarnik_service = Pneukarnik_Service::find( $pneukarnik_item['id'] ); ?>
							<option value="<?php echo (int) $pneukarnik_item['id']; ?>" <?php selected( $selected, $pneukarnik_item['id'] ); ?>><?php echo esc_html( $pneukarnik_item['name'] . ( $pneukarnik_service ? ' (' . pneukarnik_price_label( $pneukarnik_service ) . ')' : '' ) ); ?></option>
						<?php endforeach; ?>
						<?php
					};
					// Odkaz na Průvodce vybrané Služby, adresu a název doplní rezervace.js (bez JavaScriptu se nezobrazí).
					$pneukarnik_guide_link = static function (): void {
						?>
						<p class="rezervace__pruvodce" hidden>
							<a href="" target="_blank" rel="noopener"><span class="ikona-info"><?php echo pneukarnik_icon( 'info' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG ze šablony. ?></span><span><?php esc_html_e( 'Přečtěte si:', 'pneukarnik' ); ?> <span data-pruvodce-nazev></span><span class="screen-reader-text"> <?php esc_html_e( '(otevře se v novém panelu)', 'pneukarnik' ); ?></span></span></a>
						</p>
						<?php
					};
	?>
					<section class="rezervace__cast">
						<h2 class="rezervace__nadpis"><span class="rezervace__cislo" aria-hidden="true">1</span> <?php esc_html_e( 'Služba', 'pneukarnik' ); ?></h2>
						<div class="rezervace__sluzby" id="rez-sluzby">
							<div class="rezervace__sluzba">
								<p class="pole">
									<label class="pole__popisek" for="rez-sluzba-1"><?php esc_html_e( 'Služba', 'pneukarnik' ); ?></label>
									<select id="rez-sluzba-1" name="service_ids" required aria-describedby="chyba-service_ids">
										<?php $pneukarnik_service_options( $pneukarnik_config['selected'] ); ?>
									</select>
									<span class="pole__chyba" id="chyba-service_ids"></span>
								</p>
								<?php $pneukarnik_guide_link(); ?>
							</div>
						</div>
						<template id="rez-sluzba-sablona">
							<div class="rezervace__sluzba">
								<p class="pole">
									<label class="pole__popisek"><?php esc_html_e( 'Další služba', 'pneukarnik' ); ?></label>
									<select name="service_ids" aria-describedby="chyba-service_ids">
										<?php $pneukarnik_service_options( 0 ); ?>
									</select>
								</p>
								<button type="button" class="rezervace__odebrat"><?php esc_html_e( 'Odebrat', 'pneukarnik' ); ?></button>
								<?php $pneukarnik_guide_link(); ?>
							</div>
						</template>
						<button type="button" id="rez-pridat-sluzbu" class="rezervace__pridat" hidden><?php esc_html_e( '+ přidat další službu', 'pneukarnik' ); ?></button>
						<div class="rezervace__volby">
							<p class="zaskrtavaci" id="rez-uskladnena" hidden>
								<input type="checkbox" name="stored_wheels" value="1" id="rez-stored_wheels">
								<label for="rez-stored_wheels"><?php esc_html_e( 'Kola mám uskladněná u vás', 'pneukarnik' ); ?></label>
							</p>
							<p class="zaskrtavaci">
								<input type="checkbox" name="leasing" value="1" id="rez-leasing">
								<label for="rez-leasing"><?php esc_html_e( 'Vozidlo je na leasing', 'pneukarnik' ); ?></label>
							</p>
							<p class="pole rezervace__leasing" id="rez-leasing-spolecnost" hidden>
								<label class="pole__popisek" for="rez-leasing_company"><?php esc_html_e( 'Leasingová společnost', 'pneukarnik' ); ?></label>
								<input id="rez-leasing_company" type="text" name="leasing_company" autocomplete="off" aria-describedby="chyba-leasing_company">
								<span class="pole__chyba" id="chyba-leasing_company"></span>
							</p>
						</div>
					</section>

					<section class="rezervace__cast">
						<h2 class="rezervace__nadpis"><span class="rezervace__cislo" aria-hidden="true">2</span> <?php esc_html_e( 'Den a čas', 'pneukarnik' ); ?></h2>
						<div class="rezervace__den-a-cas">
							<div class="kalendar" role="group" aria-labelledby="kalendar-mesic" aria-describedby="kalendar-stav chyba-date">
								<input type="hidden" name="date" id="rez-den">
								<div class="kalendar__hlavicka">
									<button type="button" class="kalendar__sipka" id="kalendar-predchozi" aria-label="<?php esc_attr_e( 'Předchozí měsíc', 'pneukarnik' ); ?>"><span aria-hidden="true">←</span></button>
									<span class="kalendar__mesic" id="kalendar-mesic" aria-live="polite"></span>
									<button type="button" class="kalendar__sipka" id="kalendar-dalsi" aria-label="<?php esc_attr_e( 'Další měsíc', 'pneukarnik' ); ?>"><span aria-hidden="true">→</span></button>
								</div>
								<table class="kalendar__mrizka">
									<thead>
										<tr>
											<?php foreach ( [ 'Po', 'Út', 'St', 'Čt', 'Pá', 'So', 'Ne' ] as $pneukarnik_weekday ) : ?>
												<th scope="col"><?php echo esc_html( $pneukarnik_weekday ); ?></th>
											<?php endforeach; ?>
										</tr>
									</thead>
									<tbody id="kalendar-dny"></tbody>
								</table>
								<p class="rezervace__stav" id="kalendar-stav"></p>
								<span class="pole__chyba" id="chyba-date"></span>
								<div class="rezervace__omezeni" id="kalendar-omezeni"></div>
							</div>
							<div class="terminy" role="group" aria-labelledby="terminy-nadpis" aria-describedby="terminy-stav chyba-time">
								<input type="hidden" name="time" id="rez-cas">
								<p class="terminy__nadpis" id="terminy-nadpis"><?php esc_html_e( 'Volné termíny', 'pneukarnik' ); ?> <span class="terminy__den" id="terminy-den"></span></p>
								<div class="terminy__seznam" id="terminy"></div>
								<p class="rezervace__stav" id="terminy-stav" aria-live="polite"></p>
								<span class="pole__chyba" id="chyba-time"></span>
							</div>
						</div>
					</section>

					<section class="rezervace__cast">
						<h2 class="rezervace__nadpis"><span class="rezervace__cislo" aria-hidden="true">3</span> <?php esc_html_e( 'Vaše údaje', 'pneukarnik' ); ?></h2>
						<div class="rezervace__udaje">
							<?php
							// name => [ popisek, typ, autocomplete, nepovinné, na celou šířku ].
							$pneukarnik_fields = [
								'name'    => [ __( 'Jméno nebo firma', 'pneukarnik' ), 'text', 'name', false, false ],
								'phone'   => [ __( 'Telefon', 'pneukarnik' ), 'tel', 'tel', false, false ],
								'email'   => [ __( 'E‑mail', 'pneukarnik' ), 'email', 'email', false, false ],
								'plate'   => [ __( 'SPZ', 'pneukarnik' ), 'text', 'off', false, false ],
								'vehicle' => [ __( 'Značka a model', 'pneukarnik' ), 'text', 'off', true, true ],
							];
							foreach ( $pneukarnik_fields as $pneukarnik_name => [ $pneukarnik_label, $pneukarnik_type, $pneukarnik_autocomplete, $pneukarnik_optional, $pneukarnik_wide ] ) :
								?>
								<p class="pole<?php echo $pneukarnik_wide ? ' pole--siroke' : ''; ?>">
									<label class="pole__popisek" for="rez-<?php echo esc_attr( $pneukarnik_name ); ?>">
										<?php echo esc_html( $pneukarnik_label ); ?>
										<?php if ( $pneukarnik_optional ) : ?>
											<span class="pole__nepovinne"><?php esc_html_e( 'nepovinné', 'pneukarnik' ); ?></span>
										<?php endif; ?>
									</label>
									<input id="rez-<?php echo esc_attr( $pneukarnik_name ); ?>" class="<?php echo 'plate' === $pneukarnik_name ? 'pole__spz' : ''; ?>" type="<?php echo esc_attr( $pneukarnik_type ); ?>" name="<?php echo esc_attr( $pneukarnik_name ); ?>" autocomplete="<?php echo esc_attr( $pneukarnik_autocomplete ); ?>" aria-describedby="chyba-<?php echo esc_attr( $pneukarnik_name ); ?>">
									<span class="pole__chyba" id="chyba-<?php echo esc_attr( $pneukarnik_name ); ?>"></span>
								</p>
							<?php endforeach; ?>
							<p class="pole pole--siroke">
								<label class="pole__popisek" for="rez-note">
									<?php esc_html_e( 'Poznámka', 'pneukarnik' ); ?>
									<span class="pole__nepovinne"><?php esc_html_e( 'nepovinné', 'pneukarnik' ); ?></span>
								</label>
								<textarea id="rez-note" name="note" rows="3" aria-describedby="napoveda-note chyba-note"></textarea>
								<span class="pole__napoveda" id="napoveda-note"><?php esc_html_e( 'Další přání, která mezi službami nenajdete, napište sem.', 'pneukarnik' ); ?></span>
								<span class="pole__chyba" id="chyba-note"></span>
							</p>
						</div>

						<div class="rezervace__souhlasy">
							<div class="zaskrtavaci">
								<input type="checkbox" name="remember" value="1" id="rez-zapamatovat" aria-describedby="napoveda-zapamatovat">
								<span>
									<label for="rez-zapamatovat"><?php esc_html_e( 'Zapamatovat údaje na tomto zařízení.', 'pneukarnik' ); ?></label>
									<span id="napoveda-zapamatovat"><?php esc_html_e( 'Uloží se jen v tomto prohlížeči, příště je nebudete vyplňovat znovu.', 'pneukarnik' ); ?></span>
								</span>
							</div>
							<p class="rezervace__zapomenout">
								<button type="button" id="rez-zapomenout" hidden><?php esc_html_e( 'Smazat uložené údaje', 'pneukarnik' ); ?></button>
								<span id="rez-zapomenuto" aria-live="polite"></span>
							</p>
							<div class="zaskrtavaci">
								<input type="checkbox" name="consent_gdpr" value="1" id="rez-consent_gdpr" aria-describedby="chyba-consent_gdpr">
								<span>
									<label for="rez-consent_gdpr"><?php esc_html_e( 'Souhlasím se zpracováním osobních údajů pro vyřízení rezervace.', 'pneukarnik' ); ?></label>
									<a href="<?php echo esc_url( $pneukarnik_config['privacy_url'] ); ?>"><?php esc_html_e( 'Zásady ochrany osobních údajů', 'pneukarnik' ); ?></a>
								</span>
							</div>
							<span class="pole__chyba rezervace__chyba-souhlasu" id="chyba-consent_gdpr"></span>
							<div class="zaskrtavaci">
								<input type="checkbox" name="consent_reminder" value="1" id="rez-consent_reminder" aria-describedby="napoveda-consent_reminder">
								<span>
									<label for="rez-consent_reminder"><?php esc_html_e( 'Připomeňte mi před každou sezónou, že je čas přezout (nepovinné).', 'pneukarnik' ); ?></label>
									<span id="napoveda-consent_reminder"><?php esc_html_e( 'Přijde e‑mailem dvakrát do roka, odhlásit se jde jedním kliknutím v každé připomínce.', 'pneukarnik' ); ?></span>
								</span>
							</div>
						</div>
					</section>

					<div class="rezervace__odeslat">
						<?php $pneukarnik_summary( 'mobil' ); ?>
						<div id="rezervace-zprava" role="alert"></div>
						<p class="rezervace__tlacitko">
							<button type="submit" class="button button--glow"><?php esc_html_e( 'Rezervovat', 'pneukarnik' ); ?></button>
							<span class="rezervace__odesilam" id="rez-odesilam" hidden><?php esc_html_e( 'Stránku prosím nezavírejte.', 'pneukarnik' ); ?></span>
						</p>
					</div>
				</form>
			</div>

			<aside class="rezervace__bok" aria-label="<?php esc_attr_e( 'Souhrn a kontakt', 'pneukarnik' ); ?>">
				<?php $pneukarnik_summary( 'bok' ); ?>
				<?php if ( '' !== $pneukarnik_phone ) : ?>
					<?php $pneukarnik_hours = pneukarnik_weekly_hours(); ?>
					<div class="card card--muted zavolejte">
						<p class="zavolejte__nadpis"><?php esc_html_e( 'Raději zavoláte?', 'pneukarnik' ); ?></p>
						<p class="zavolejte__text">
							<?php
							echo esc_html(
								'' !== $pneukarnik_hours
									/* translators: %s: zkrácená Pracovní doba, např. Po–Pá 8:00–12:00, 13:00–17:00 */
									? sprintf( __( 'Objednáme vás i telefonicky, %s.', 'pneukarnik' ), $pneukarnik_hours )
									: __( 'Objednáme vás i telefonicky.', 'pneukarnik' )
							);
							?>
						</p>
						<a class="zavolejte__telefon" href="<?php echo esc_url( pneukarnik_tel_href( $pneukarnik_phone ) ); ?>"><span aria-hidden="true">☎ </span><?php echo esc_html( $pneukarnik_phone ); ?></a>
					</div>
				<?php endif; ?>
			</aside>
		</div>
	<?php endif; ?>
</main>
<?php
get_footer();
