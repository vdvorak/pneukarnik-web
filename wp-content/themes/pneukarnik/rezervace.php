<?php
/**
 * Rezervace termínu: výběr jedné nebo víc Služeb, dne a Termínu, kontaktní údaje.
 * Data a pravidla dodává plugin (REST), tady je jen formulář.
 *
 * @package Pneukarnik
 */

$pneukarnik_config = Pneukarnik_Booking_Pages::form_config();

get_header();
?>
<main id="obsah" class="site-main rezervace">
	<h1><?php esc_html_e( 'Rezervace termínu', 'pneukarnik' ); ?></h1>

	<?php pneukarnik_booking_notices(); ?>

	<?php if ( ! $pneukarnik_config['enabled'] ) : ?>
		<p class="rezervace__vypnuto"><?php echo esc_html( $pneukarnik_config['disabled_message'] ); ?></p>
		<?php if ( '' !== $pneukarnik_config['phone'] ) : ?>
			<p><a href="<?php echo esc_url( pneukarnik_tel_href( $pneukarnik_config['phone'] ) ); ?>"><?php echo esc_html( sprintf( /* translators: %s: telefon */ __( 'Zavolat %s', 'pneukarnik' ), $pneukarnik_config['phone'] ) ); ?></a></p>
		<?php endif; ?>
	<?php elseif ( ! $pneukarnik_config['services'] ) : ?>
		<p>
			<?php esc_html_e( 'Online teď nejde objednat žádná služba.', 'pneukarnik' ); ?>
			<?php if ( '' !== $pneukarnik_config['phone'] ) : ?>
				<a href="<?php echo esc_url( pneukarnik_tel_href( $pneukarnik_config['phone'] ) ); ?>"><?php echo esc_html( sprintf( /* translators: %s: telefon */ __( 'Zavolejte nám na %s.', 'pneukarnik' ), $pneukarnik_config['phone'] ) ); ?></a>
			<?php endif; ?>
		</p>
	<?php else : ?>
		<script type="application/json" id="rezervace-config"><?php echo wp_json_encode( $pneukarnik_config, JSON_HEX_TAG | JSON_HEX_AMP ); ?></script>
		<noscript>
			<p><?php echo esc_html( sprintf( /* translators: %s: telefon */ __( 'Rezervace potřebuje zapnutý JavaScript. Objednat se můžete i telefonicky na %s.', 'pneukarnik' ), $pneukarnik_config['phone'] ) ); ?></p>
		</noscript>

		<form id="rezervace-form" class="rezervace__form" novalidate>
			<?php
			$pneukarnik_service_options = static function ( int $selected ) use ( $pneukarnik_config ): void {
				?>
				<option value=""><?php esc_html_e( '— vyberte službu —', 'pneukarnik' ); ?></option>
				<?php foreach ( $pneukarnik_config['services'] as $pneukarnik_service ) : ?>
					<option value="<?php echo (int) $pneukarnik_service['id']; ?>" <?php selected( $selected, $pneukarnik_service['id'] ); ?>><?php echo esc_html( $pneukarnik_service['name'] ); ?></option>
				<?php endforeach; ?>
				<?php
			};
	?>
			<div class="sluzby" id="rez-sluzby">
				<p class="pole sluzby__radek">
					<label for="rez-sluzba-1"><?php esc_html_e( 'Služba', 'pneukarnik' ); ?></label>
					<select id="rez-sluzba-1" name="service_ids" required aria-describedby="chyba-service_ids">
						<?php $pneukarnik_service_options( $pneukarnik_config['selected'] ); ?>
					</select>
				</p>
			</div>
			<template id="rez-sluzba-sablona">
				<p class="pole sluzby__radek">
					<label><?php esc_html_e( 'Další služba', 'pneukarnik' ); ?></label>
					<select name="service_ids" aria-describedby="chyba-service_ids">
						<?php $pneukarnik_service_options( 0 ); ?>
					</select>
					<button type="button" class="sluzby__odebrat"><?php esc_html_e( 'Odebrat', 'pneukarnik' ); ?></button>
				</p>
			</template>
			<p class="pole">
				<button type="button" id="rez-pridat-sluzbu" class="sluzby__pridat" hidden><?php esc_html_e( '+ přidat další službu', 'pneukarnik' ); ?></button>
				<span class="pole__chyba" id="chyba-service_ids"></span>
			</p>
			<p class="pole pole--zaskrtavaci" id="rez-uskladnena" hidden>
				<label>
					<input type="checkbox" name="stored_wheels" value="1">
					<?php esc_html_e( 'Kola mám uskladněná u vás', 'pneukarnik' ); ?>
				</label>
			</p>
			<p class="pole pole--zaskrtavaci">
				<label>
					<input type="checkbox" name="leasing" value="1" id="rez-leasing">
					<?php esc_html_e( 'Vozidlo je na leasing', 'pneukarnik' ); ?>
				</label>
			</p>
			<p class="pole" id="rez-leasing-spolecnost" hidden>
				<label for="rez-leasing_company"><?php esc_html_e( 'Leasingová společnost', 'pneukarnik' ); ?></label>
				<input id="rez-leasing_company" type="text" name="leasing_company" autocomplete="off" aria-describedby="chyba-leasing_company">
				<span class="pole__chyba" id="chyba-leasing_company"></span>
			</p>

			<fieldset class="kalendar" aria-describedby="kalendar-stav chyba-date">
				<legend><?php esc_html_e( 'Den', 'pneukarnik' ); ?></legend>
				<input type="hidden" name="date" id="rez-den">
				<p class="kalendar__hlavicka">
					<button type="button" id="kalendar-predchozi" aria-label="<?php esc_attr_e( 'Předchozí měsíc', 'pneukarnik' ); ?>">‹</button>
					<span id="kalendar-mesic" aria-live="polite"></span>
					<button type="button" id="kalendar-dalsi" aria-label="<?php esc_attr_e( 'Další měsíc', 'pneukarnik' ); ?>">›</button>
				</p>
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
				<p id="kalendar-stav" class="pole__napoveda"></p>
				<span class="pole__chyba" id="chyba-date"></span>
			</fieldset>

			<fieldset class="terminy">
				<legend><?php esc_html_e( 'Volné termíny', 'pneukarnik' ); ?></legend>
				<div id="terminy" aria-live="polite"><p><?php esc_html_e( 'Vyberte službu a den.', 'pneukarnik' ); ?></p></div>
				<span class="pole__chyba" id="chyba-time"></span>
			</fieldset>

			<fieldset class="udaje">
				<legend><?php esc_html_e( 'Vaše údaje', 'pneukarnik' ); ?></legend>
				<?php
				$pneukarnik_fields = [
					'name'    => [ __( 'Jméno nebo firma', 'pneukarnik' ), 'text', 'name' ],
					'phone'   => [ __( 'Telefon', 'pneukarnik' ), 'tel', 'tel' ],
					'email'   => [ __( 'E‑mail', 'pneukarnik' ), 'email', 'email' ],
					'plate'   => [ __( 'SPZ', 'pneukarnik' ), 'text', 'off' ],
					'vehicle' => [ __( 'Značka a model (nepovinné)', 'pneukarnik' ), 'text', 'off' ],
				];
				foreach ( $pneukarnik_fields as $pneukarnik_name => [ $pneukarnik_label, $pneukarnik_type, $pneukarnik_autocomplete ] ) :
					?>
					<p class="pole">
						<label for="rez-<?php echo esc_attr( $pneukarnik_name ); ?>"><?php echo esc_html( $pneukarnik_label ); ?></label>
						<input id="rez-<?php echo esc_attr( $pneukarnik_name ); ?>" type="<?php echo esc_attr( $pneukarnik_type ); ?>" name="<?php echo esc_attr( $pneukarnik_name ); ?>" autocomplete="<?php echo esc_attr( $pneukarnik_autocomplete ); ?>" aria-describedby="chyba-<?php echo esc_attr( $pneukarnik_name ); ?>">
						<span class="pole__chyba" id="chyba-<?php echo esc_attr( $pneukarnik_name ); ?>"></span>
					</p>
				<?php endforeach; ?>
				<p class="pole">
					<label for="rez-note"><?php esc_html_e( 'Poznámka (nepovinné)', 'pneukarnik' ); ?></label>
					<textarea id="rez-note" name="note" rows="3" aria-describedby="napoveda-note chyba-note"></textarea>
					<span class="pole__napoveda" id="napoveda-note"><?php esc_html_e( 'Další přání, která mezi službami nenajdete, napište sem.', 'pneukarnik' ); ?></span>
					<span class="pole__chyba" id="chyba-note"></span>
				</p>
			</fieldset>

			<p class="pole pole--zaskrtavaci">
				<label>
					<input type="checkbox" name="remember" value="1" id="rez-zapamatovat" aria-describedby="napoveda-zapamatovat">
					<?php esc_html_e( 'Zapamatovat údaje na tomto zařízení', 'pneukarnik' ); ?>
				</label>
				<span class="pole__napoveda" id="napoveda-zapamatovat"><?php esc_html_e( 'Jméno, telefon, e‑mail, SPZ a vozidlo se uloží jen v tomto prohlížeči, příště je nebudete vyplňovat.', 'pneukarnik' ); ?></span>
				<button type="button" id="rez-zapomenout" hidden><?php esc_html_e( 'Smazat uložené údaje', 'pneukarnik' ); ?></button>
				<span class="pole__napoveda" id="rez-zapomenuto" aria-live="polite"></span>
			</p>

			<p class="pole pole--souhlas">
				<label>
					<input type="checkbox" name="consent_gdpr" value="1" aria-describedby="chyba-consent_gdpr">
					<?php esc_html_e( 'Souhlasím se zpracováním osobních údajů pro vyřízení rezervace.', 'pneukarnik' ); ?>
					<a href="<?php echo esc_url( $pneukarnik_config['privacy_url'] ); ?>"><?php esc_html_e( 'Zásady ochrany osobních údajů', 'pneukarnik' ); ?></a>
				</label>
				<span class="pole__chyba" id="chyba-consent_gdpr"></span>
			</p>

			<p class="pole pole--zaskrtavaci">
				<label>
					<input type="checkbox" name="consent_reminder" value="1" aria-describedby="napoveda-consent_reminder">
					<?php esc_html_e( 'Připomeňte mi před každou sezónou, že je čas přezout (nepovinné).', 'pneukarnik' ); ?>
				</label>
				<span class="pole__napoveda" id="napoveda-consent_reminder"><?php esc_html_e( 'Přijde e‑mailem dvakrát do roka, odhlásit se jde jedním kliknutím v každé připomínce.', 'pneukarnik' ); ?></span>
			</p>

			<p id="rezervace-zprava" class="rezervace__zprava" role="alert"></p>
			<button type="submit"><?php esc_html_e( 'Rezervovat', 'pneukarnik' ); ?></button>
		</form>
	<?php endif; ?>
</main>
<?php
get_footer();
