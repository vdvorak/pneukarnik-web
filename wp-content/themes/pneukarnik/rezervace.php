<?php
/**
 * Rezervace termínu: výběr Služby, dne a Termínu, kontaktní údaje.
 * Data a pravidla dodává plugin (REST), tady je jen formulář.
 *
 * @package Pneukarnik
 */

$pneukarnik_config = Pneukarnik_Booking_Pages::form_config();

get_header();
?>
<main id="obsah" class="site-main rezervace">
	<h1><?php esc_html_e( 'Rezervace termínu', 'pneukarnik' ); ?></h1>

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
			<p class="pole">
				<label for="rez-sluzba"><?php esc_html_e( 'Služba', 'pneukarnik' ); ?></label>
				<select id="rez-sluzba" name="service_id" required aria-describedby="chyba-service_id">
					<option value=""><?php esc_html_e( '— vyberte službu —', 'pneukarnik' ); ?></option>
					<?php foreach ( $pneukarnik_config['services'] as $pneukarnik_service ) : ?>
						<option value="<?php echo (int) $pneukarnik_service['id']; ?>" <?php selected( $pneukarnik_config['selected'], $pneukarnik_service['id'] ); ?>><?php echo esc_html( $pneukarnik_service['name'] ); ?></option>
					<?php endforeach; ?>
				</select>
				<span class="pole__chyba" id="chyba-service_id"></span>
			</p>

			<p class="pole">
				<label for="rez-den"><?php esc_html_e( 'Den', 'pneukarnik' ); ?></label>
				<input id="rez-den" type="date" name="date" required min="<?php echo esc_attr( $pneukarnik_config['min_date'] ); ?>" max="<?php echo esc_attr( $pneukarnik_config['max_date'] ); ?>" aria-describedby="chyba-date">
				<span class="pole__chyba" id="chyba-date"></span>
			</p>

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
					<span class="pole__napoveda" id="napoveda-note"><?php esc_html_e( 'Další přání, třeba víc prací najednou, napište sem.', 'pneukarnik' ); ?></span>
					<span class="pole__chyba" id="chyba-note"></span>
				</p>
			</fieldset>

			<p class="pole pole--souhlas">
				<label>
					<input type="checkbox" name="consent_gdpr" value="1" aria-describedby="chyba-consent_gdpr">
					<?php esc_html_e( 'Souhlasím se zpracováním osobních údajů pro vyřízení rezervace.', 'pneukarnik' ); ?>
					<a href="<?php echo esc_url( $pneukarnik_config['privacy_url'] ); ?>"><?php esc_html_e( 'Zásady ochrany osobních údajů', 'pneukarnik' ); ?></a>
				</label>
				<span class="pole__chyba" id="chyba-consent_gdpr"></span>
			</p>

			<p id="rezervace-zprava" class="rezervace__zprava" role="alert"></p>
			<button type="submit"><?php esc_html_e( 'Rezervovat', 'pneukarnik' ); ?></button>
		</form>
	<?php endif; ?>
</main>
<?php
get_footer();
