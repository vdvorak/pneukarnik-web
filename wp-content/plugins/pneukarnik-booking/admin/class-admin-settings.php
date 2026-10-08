<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Parametry v URL (filtry, stránkování, hlášky po přesměrování) jen řídí zobrazení, nic nemění.
// phpcs:disable WordPress.Security.NonceVerification.Recommended

/**
 * WP Admin settings page — Pracovní doba, pravidla Termínů, Lhůta zrušení, Sezóny, e‑maily k Rezervacím, kontakty a sociální sítě, Pohotovost, Úvod, Google recenze, vyhledávače a Matomo, iCal.
 */
class Pneukarnik_Admin_Settings {

	/**
	 * Zpracování formuláře před výstupem administrace (háček load-{stránka}), aby šlo přesměrovat.
	 */
	public static function handle_post(): void {
		if ( isset( $_POST['pneukarnik_settings_nonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce ověří handle_save().
			self::handle_save();
		}
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Nemáte oprávnění.', 'pneukarnik-booking' ) );
		}

		$hours                = Pneukarnik_Working_Hours::get_all();
		$seasons              = Pneukarnik_Season::get_all();
		$grid_step            = Pneukarnik_Working_Hours::get_grid_step();
		$lead_minutes         = Pneukarnik_Working_Hours::get_lead_minutes();
		$horizon_days         = Pneukarnik_Working_Hours::get_horizon_days();
		$cancellation_hours   = Pneukarnik_Working_Hours::get_cancellation_hours();
		$maps_embed_url       = (string) get_option( Pneukarnik_Contact::OPTION_MAPS_EMBED_URL, '' );
		$booking_enabled      = Pneukarnik_Booking::online_enabled();
		$booking_disabled_msg = Pneukarnik_Booking::online_disabled_message();
		$create_limit         = Pneukarnik_Rate_Limit::limit( Pneukarnik_Rate_Limit::CREATE );
		$cancel_limit         = Pneukarnik_Rate_Limit::limit( Pneukarnik_Rate_Limit::CANCEL );
		$ical_url             = Pneukarnik_Rest_Calendar::url();
		$days_labels          = [
			'mon' => 'Pondělí',
			'tue' => 'Úterý',
			'wed' => 'Středa',
			'thu' => 'Čtvrtek',
			'fri' => 'Pátek',
			'sat' => 'Sobota',
			'sun' => 'Neděle',
		];

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Nastavení pneukarník', 'pneukarnik-booking' ); ?></h1>

			<?php if ( isset( $_GET['hours_error'] ) ) : ?>
				<div class="notice notice-error"><p><?php esc_html_e( 'Pracovní doba se neuložila: každý blok musí mít začátek před koncem a dva bloky se nesmí překrývat. Ostatní nastavení se uložilo.', 'pneukarnik-booking' ); ?></p></div>
			<?php endif; ?>
			<?php if ( isset( $_GET['season_error'] ) ) : ?>
				<div class="notice notice-error"><p><?php esc_html_e( 'Sezóny se neuložily: zadejte den a měsíc (např. 15. 3.), od i do, od před do, leasingové datum uvnitř Sezóny a Sezóny se nesmí překrývat. Ostatní nastavení se uložilo.', 'pneukarnik-booking' ); ?></p></div>
			<?php endif; ?>
			<?php if ( isset( $_GET['saved'] ) ) : ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Nastavení uložena.', 'pneukarnik-booking' ); ?></p></div>
			<?php endif; ?>

			<form method="post">
				<?php wp_nonce_field( 'pneukarnik_save_settings', 'pneukarnik_settings_nonce' ); ?>

				<h2><?php esc_html_e( 'Online rezervace', 'pneukarnik-booking' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Povolit online rezervace', 'pneukarnik-booking' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="booking_enabled" value="1" <?php checked( $booking_enabled ); ?>>
								<?php esc_html_e( 'Zákazníci mohou rezervovat online', 'pneukarnik-booking' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Při vypnutí web nové online Rezervace nepřijme. Zrušení odkazem z e‑mailu funguje dál.', 'pneukarnik-booking' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="pnk-disabled-msg"><?php esc_html_e( 'Zpráva při vypnutí', 'pneukarnik-booking' ); ?></label></th>
						<td>
							<textarea id="pnk-disabled-msg" name="booking_disabled_msg" rows="3" class="regular-text"><?php echo esc_textarea( $booking_disabled_msg ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Zobrazí se Zákazníkům místo formuláře, pod ní telefon z Kontaktů.', 'pneukarnik-booking' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="pnk-create-limit"><?php esc_html_e( 'Limit Rezervací z jedné IP za hodinu', 'pneukarnik-booking' ); ?></label></th>
						<td>
							<input id="pnk-create-limit" type="number" name="rate_limit" value="<?php echo esc_attr( (string) $create_limit ); ?>" min="<?php echo (int) Pneukarnik_Rate_Limit::MIN; ?>" max="<?php echo (int) Pneukarnik_Rate_Limit::MAX; ?>">
						</td>
					</tr>
					<tr>
						<th><label for="pnk-cancel-limit"><?php esc_html_e( 'Limit pokusů o Zrušení z jedné IP za hodinu', 'pneukarnik-booking' ); ?></label></th>
						<td>
							<input id="pnk-cancel-limit" type="number" name="cancel_rate_limit" value="<?php echo esc_attr( (string) $cancel_limit ); ?>" min="<?php echo (int) Pneukarnik_Rate_Limit::MIN; ?>" max="<?php echo (int) Pneukarnik_Rate_Limit::MAX; ?>">
							<p class="description"><?php esc_html_e( 'Limity chrání kalendář před zahlcením. Platí jen pro nepřihlášené, přihlášený Provozovatel je nemá.', 'pneukarnik-booking' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Pracovní doba', 'pneukarnik-booking' ); ?></h2>
				<table class="form-table">
					<?php foreach ( $days_labels as $key => $label ) : ?>
						<?php
						$phases = $hours[ $key ] ?? null;
						$from1  = $phases[0]['from'] ?? '';
						$to1    = $phases[0]['to'] ?? '';
						$from2  = $phases[1]['from'] ?? '';
						$to2    = $phases[1]['to'] ?? '';
						?>
						<tr>
							<th><?php echo esc_html( $label ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="day_open[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( $phases !== null ); ?>>
									<?php esc_html_e( 'Otevřeno', 'pneukarnik-booking' ); ?>
								</label>
								<?php esc_html_e( 'Fáze 1:', 'pneukarnik-booking' ); ?>
								<input type="time" name="day_from1[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $from1 ); ?>">
								–
								<input type="time" name="day_to1[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $to1 ); ?>">
								<?php esc_html_e( 'Fáze 2:', 'pneukarnik-booking' ); ?>
								<input type="time" name="day_from2[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $from2 ); ?>">
								–
								<input type="time" name="day_to2[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $to2 ); ?>">
							</td>
						</tr>
					<?php endforeach; ?>
				</table>

				<h2><?php esc_html_e( 'Rezervace', 'pneukarnik-booking' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><label for="pnk-grid-step"><?php esc_html_e( 'Krok mřížky Termínů (min)', 'pneukarnik-booking' ); ?></label></th>
						<td>
							<input id="pnk-grid-step" type="number" name="grid_step" value="<?php echo esc_attr( (string) $grid_step ); ?>" min="5" max="240" step="5">
							<p class="description"><?php esc_html_e( 'Termíny začínají od začátku každého bloku Pracovní doby po tomto kroku.', 'pneukarnik-booking' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="pnk-lead"><?php esc_html_e( 'Předstih pro dnešek (min)', 'pneukarnik-booking' ); ?></label></th>
						<td>
							<input id="pnk-lead" type="number" name="lead_minutes" value="<?php echo esc_attr( (string) $lead_minutes ); ?>" min="0" max="1440" step="5">
							<p class="description"><?php esc_html_e( 'Dnešní Termín jde rezervovat nejdřív tolik minut předem.', 'pneukarnik-booking' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="pnk-horizon"><?php esc_html_e( 'Horizont (dny dopředu)', 'pneukarnik-booking' ); ?></label></th>
						<td><input id="pnk-horizon" type="number" name="horizon_days" value="<?php echo esc_attr( (string) $horizon_days ); ?>" min="1" max="365"></td>
					</tr>
					<tr>
						<th><label for="pnk-cancellation-hours"><?php esc_html_e( 'Lhůta zrušení (hodiny)', 'pneukarnik-booking' ); ?></label></th>
						<td>
							<input id="pnk-cancellation-hours" type="number" name="cancellation_hours" value="<?php echo esc_attr( (string) $cancellation_hours ); ?>" min="0" max="720">
							<p class="description"><?php esc_html_e( 'Zákazník může Rezervaci zrušit odkazem z e‑mailu nejpozději tolik hodin před Termínem. Potom mu web nabídne telefon.', 'pneukarnik-booking' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Sezóny', 'pneukarnik-booking' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Každý rok stejně. Termín v Sezóně (včetně dnů od a do) jde online rezervovat jen se sezónními Službami. Leasingoví zákazníci dostanou v Sezóně Termíny až od leasingového data. Prázdné od a do = Sezóna se nepoužije.', 'pneukarnik-booking' ); ?></p>
				<table class="form-table">
					<?php foreach ( Pneukarnik_Season::names() as $key => $label ) : ?>
						<tr>
							<th><?php echo esc_html( $label ); ?></th>
							<td>
								<?php
								$fields = [
									'from'         => __( 'od', 'pneukarnik-booking' ),
									'to'           => __( 'do', 'pneukarnik-booking' ),
									'leasing_from' => __( 'leasing od', 'pneukarnik-booking' ),
								];
								?>
								<?php foreach ( $fields as $field => $field_label ) : ?>
									<label>
										<?php echo esc_html( $field_label ); ?>
										<input type="text" name="season[<?php echo esc_attr( $key ); ?>][<?php echo esc_attr( $field ); ?>]" value="<?php echo esc_attr( self::day_month_label( $seasons[ $key ][ $field ] ) ); ?>" placeholder="<?php esc_attr_e( 'D. M.', 'pneukarnik-booking' ); ?>" size="7" aria-label="<?php echo esc_attr( $label . ' ' . $field_label ); ?>">
									</label>
								<?php endforeach; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>

				<h2><?php esc_html_e( 'E‑maily k Rezervacím', 'pneukarnik-booking' ); ?></h2>
				<p class="description">
					<?php
					printf(
						/* translators: %s: odkaz na stránku E‑maily Zákazníkům */
						esc_html__( 'Připomínku přezutí a další e‑maily Zákazníkům mimo potvrzení a Zrušení nastavíte na stránce %s.', 'pneukarnik-booking' ),
						'<a href="' . esc_url( Pneukarnik_Admin_Customer_Emails::url() ) . '">' . esc_html__( 'E‑maily Zákazníkům', 'pneukarnik-booking' ) . '</a>'
					);
					?>
				</p>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Upozornění Provozovateli', 'pneukarnik-booking' ); ?></th>
						<td>
							<label style="display:block"><input type="checkbox" name="notify_created" value="1" <?php checked( '1' === (string) get_option( Pneukarnik_Notifications::OPTION_NOTIFY_CREATED, '1' ) ); ?>> <?php esc_html_e( 'E‑mail o každé nové online Rezervaci', 'pneukarnik-booking' ); ?></label>
							<label style="display:block"><input type="checkbox" name="notify_cancelled" value="1" <?php checked( '1' === (string) get_option( Pneukarnik_Notifications::OPTION_NOTIFY_CANCELLED, '1' ) ); ?>> <?php esc_html_e( 'E‑mail o každé Rezervaci, kterou Zákazník zrušil', 'pneukarnik-booking' ); ?></label>
							<p class="description"><?php esc_html_e( 'Chodí na kontaktní e‑mail níže (bez něj na e‑mail správce webu).', 'pneukarnik-booking' ); ?></p>
						</td>
					</tr>
					<?php foreach ( Pneukarnik_Notifications::texts() as $key => [ $label ] ) : ?>
						<tr>
							<th><label for="pnk-email-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
							<td><textarea id="pnk-email-<?php echo esc_attr( $key ); ?>" name="email_text[<?php echo esc_attr( $key ); ?>]" rows="3" class="large-text"><?php echo esc_textarea( Pneukarnik_Notifications::text( $key ) ); ?></textarea></td>
						</tr>
					<?php endforeach; ?>
				</table>

				<h2><?php esc_html_e( 'Kontakt', 'pneukarnik-booking' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Jedno místo pro celý web: hlavička, patička, Kontakt, e‑maily Zákazníkům i iCal.', 'pneukarnik-booking' ); ?></p>
				<table class="form-table">
					<tr>
						<th><label for="pnk-company"><?php esc_html_e( 'Název firmy', 'pneukarnik-booking' ); ?></label></th>
						<td><input id="pnk-company" type="text" name="pneukarnik_company" value="<?php echo esc_attr( Pneukarnik_Contact::company() ); ?>" class="regular-text"></td>
					</tr>
					<tr>
						<th><label for="pnk-phone"><?php esc_html_e( 'Telefon', 'pneukarnik-booking' ); ?></label></th>
						<td><input id="pnk-phone" type="text" name="pneukarnik_phone" value="<?php echo esc_attr( Pneukarnik_Contact::phone() ); ?>"></td>
					</tr>
					<tr>
						<th><label for="pnk-email"><?php esc_html_e( 'Kontaktní e‑mail', 'pneukarnik-booking' ); ?></label></th>
						<td><input id="pnk-email" type="email" name="pneukarnik_email" value="<?php echo esc_attr( Pneukarnik_Contact::email() ); ?>"></td>
					</tr>
					<tr>
						<th><label for="pnk-address"><?php esc_html_e( 'Adresa', 'pneukarnik-booking' ); ?></label></th>
						<td><input id="pnk-address" type="text" name="pneukarnik_address" value="<?php echo esc_attr( Pneukarnik_Contact::address() ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Ulice 1, 669 02 Znojmo', 'pneukarnik-booking' ); ?>"></td>
					</tr>
					<tr>
						<th><label for="pnk-ico"><?php esc_html_e( 'IČ', 'pneukarnik-booking' ); ?></label></th>
						<td><input id="pnk-ico" type="text" name="pneukarnik_ico" value="<?php echo esc_attr( Pneukarnik_Contact::ico() ); ?>"></td>
					</tr>
					<tr>
						<th><label for="pnk-dic"><?php esc_html_e( 'DIČ', 'pneukarnik-booking' ); ?></label></th>
						<td><input id="pnk-dic" type="text" name="pneukarnik_dic" value="<?php echo esc_attr( Pneukarnik_Contact::dic() ); ?>"></td>
					</tr>
					<tr>
						<th><label for="pnk-maps"><?php esc_html_e( 'Google Maps embed URL', 'pneukarnik-booking' ); ?></label></th>
						<td>
							<input id="pnk-maps" type="url" name="pneukarnik_maps_embed_url" value="<?php echo esc_attr( $maps_embed_url ); ?>" class="large-text">
							<p class="description"><?php esc_html_e( 'URL z Google Maps → Sdílet → Vložit mapu → atribut src iframe. Prázdné = mapa podle adresy. Mapa se na webu načte až po kliknutí Zákazníka.', 'pneukarnik-booking' ); ?></p>
						</td>
					</tr>
					<?php foreach ( Pneukarnik_Contact::social_networks() as $network => $label ) : ?>
						<tr>
							<th><label for="pnk-social-<?php echo esc_attr( $network ); ?>"><?php echo esc_html( $label ); ?></label></th>
							<td><input id="pnk-social-<?php echo esc_attr( $network ); ?>" type="url" name="social[<?php echo esc_attr( $network ); ?>]" value="<?php echo esc_attr( (string) get_option( Pneukarnik_Contact::OPTION_SOCIAL_PREFIX . $network, '' ) ); ?>" class="large-text" placeholder="https://"></td>
						</tr>
					<?php endforeach; ?>
				</table>

				<h2><?php esc_html_e( 'Pohotovost', 'pneukarnik-booking' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Nabízet Pohotovost', 'pneukarnik-booking' ); ?></th>
						<td>
							<label><input type="checkbox" name="emergency_enabled" value="1" <?php checked( '1' === (string) get_option( Pneukarnik_Contact::OPTION_EMERGENCY_ENABLED ) ); ?>> <?php esc_html_e( 'Zobrazit Pohotovost výrazně v hlavičce webu', 'pneukarnik-booking' ); ?></label>
							<p class="description"><?php esc_html_e( 'Bez zapnutí a telefonu se Pohotovost nikde nezobrazí.', 'pneukarnik-booking' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="pnk-emergency-phone"><?php esc_html_e( 'Telefon Pohotovosti', 'pneukarnik-booking' ); ?></label></th>
						<td><input id="pnk-emergency-phone" type="text" name="emergency_phone" value="<?php echo esc_attr( (string) get_option( Pneukarnik_Contact::OPTION_EMERGENCY_PHONE, '' ) ); ?>"></td>
					</tr>
					<tr>
						<th><label for="pnk-emergency-text"><?php esc_html_e( 'Popis Pohotovosti', 'pneukarnik-booking' ); ?></label></th>
						<td><input id="pnk-emergency-text" type="text" name="emergency_text" value="<?php echo esc_attr( (string) get_option( Pneukarnik_Contact::OPTION_EMERGENCY_TEXT, '' ) ); ?>" class="large-text" placeholder="<?php esc_attr_e( 'Nonstop pomoc při defektu na cestě', 'pneukarnik-booking' ); ?>"></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Úvod', 'pneukarnik-booking' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><label for="pnk-founded-year"><?php esc_html_e( 'Rok založení', 'pneukarnik-booking' ); ?></label></th>
						<td>
							<input id="pnk-founded-year" type="number" name="founded_year" value="<?php echo esc_attr( (string) pneukarnik_founded_year() ); ?>" min="1900" max="<?php echo esc_attr( Pneukarnik_Clock::today()->format( 'Y' ) ); ?>" step="1" class="small-text">
							<p class="description"><?php esc_html_e( 'Na Úvodu nad nadpisem „Znojmo · od roku …“. Prázdné = jen „Znojmo“.', 'pneukarnik-booking' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="pnk-why-us"><?php esc_html_e( 'Proč k nám', 'pneukarnik-booking' ); ?></label></th>
						<td>
							<textarea id="pnk-why-us" name="why_us" rows="5" class="large-text" placeholder="<?php esc_attr_e( 'Partner sítě BestDrive | Věrnostní karta BestDrive platí i u nás.', 'pneukarnik-booking' ); ?>"><?php echo esc_textarea( (string) get_option( 'pneukarnik_why_us', '' ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Jeden důvod na řádek ve tvaru „Nadpis | text“. Text za svislou čarou je nepovinný, řádek bez ní je jen nadpis. Prázdné = sekce se na Úvodu nezobrazí.', 'pneukarnik-booking' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Google recenze', 'pneukarnik-booking' ); ?></h2>
				<?php $reviews_status = Pneukarnik_Reviews::status(); ?>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Zobrazit recenze', 'pneukarnik-booking' ); ?></th>
						<td>
							<label><input type="checkbox" name="reviews_enabled" value="1" <?php checked( Pneukarnik_Reviews::enabled() ); ?>> <?php esc_html_e( 'Hodnocení a recenze z Google na Úvodu', 'pneukarnik-booking' ); ?></label>
							<p class="description"><?php esc_html_e( 'Recenze se stahují na serveru jednou denně (a hned po zapnutí nebo změně klíče či místa), prohlížeč Zákazníka od Googlu nic nenačítá. Při vypnutí se nic nestahuje ani nezobrazuje.', 'pneukarnik-booking' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="pnk-reviews-key"><?php esc_html_e( 'Klíč Places API', 'pneukarnik-booking' ); ?></label></th>
						<td>
							<input id="pnk-reviews-key" type="password" name="reviews_api_key" value="" class="regular-text" autocomplete="off">
							<p class="description">
								<?php
								$key_hint = Pneukarnik_Reviews::api_key_hint();
								echo esc_html(
									'' === $key_hint
										? __( 'Zatím nezadaný. Klíč z Google Cloud Console s povoleným Places API (New).', 'pneukarnik-booking' )
										/* translators: %s: poslední čtyři znaky klíče */
										: sprintf( __( 'Uložený klíč končí %s. Prázdné pole klíč ponechá.', 'pneukarnik-booking' ), $key_hint )
								);
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th><label for="pnk-reviews-place"><?php esc_html_e( 'ID místa (Place ID)', 'pneukarnik-booking' ); ?></label></th>
						<td><input id="pnk-reviews-place" type="text" name="reviews_place_id" value="<?php echo esc_attr( Pneukarnik_Reviews::place_id() ); ?>" class="regular-text" placeholder="ChIJ…"></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Stav', 'pneukarnik-booking' ); ?></th>
						<td>
							<?php if ( '' !== $reviews_status['updated_at'] ) : ?>
								<p><?php echo esc_html( sprintf( /* translators: %s: datum a čas posledního stažení */ __( 'Naposledy staženo %s.', 'pneukarnik-booking' ), Pneukarnik_Clock::at( $reviews_status['updated_at'] )->format( 'j. n. Y H:i' ) ) ); ?></p>
							<?php else : ?>
								<p><?php esc_html_e( 'Zatím nestaženo, na webu se recenze nezobrazí.', 'pneukarnik-booking' ); ?></p>
							<?php endif; ?>
							<?php if ( '' !== $reviews_status['error'] ) : ?>
								<p style="color:#b32d2e"><?php echo esc_html( sprintf( /* translators: %s: chyba z Google */ __( 'Poslední pokus selhal: %s. Na webu zůstávají poslední stažená data.', 'pneukarnik-booking' ), $reviews_status['error'] ) ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Vyhledávače a návštěvnost', 'pneukarnik-booking' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><label for="pnk-matomo-url"><?php esc_html_e( 'Adresa Matomo', 'pneukarnik-booking' ); ?></label></th>
						<td>
							<input id="pnk-matomo-url" type="url" name="matomo_url" value="<?php echo esc_attr( (string) get_option( Pneukarnik_Seo::OPTION_MATOMO_URL, '' ) ); ?>" class="regular-text" placeholder="https://matomo.example.cz/">
							<p class="description"><?php esc_html_e( 'Měření návštěvnosti v Matomu bez cookies, web proto nepotřebuje cookie lištu. Bez adresy a ID webu se nic neměří.', 'pneukarnik-booking' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="pnk-matomo-site"><?php esc_html_e( 'ID webu v Matomu', 'pneukarnik-booking' ); ?></label></th>
						<td><input id="pnk-matomo-site" type="number" name="matomo_site_id" value="<?php echo esc_attr( (string) get_option( Pneukarnik_Seo::OPTION_MATOMO_SITE_ID, '' ) ); ?>" min="1" step="1" class="small-text"></td>
					</tr>
					<tr>
						<th><label for="pnk-google-verification"><?php esc_html_e( 'Ověření Google Search Console', 'pneukarnik-booking' ); ?></label></th>
						<td>
							<input id="pnk-google-verification" type="text" name="google_verification" value="<?php echo esc_attr( Pneukarnik_Seo::google_verification() ); ?>" class="regular-text">
							<p class="description">
								<?php
								/* translators: %s: adresa sitemapy */
								echo esc_html( sprintf( __( 'Kód z ověření značkou HTML (stačí vložit celou značku). Prázdné, když je web ověřený jinak (DNS). Sitemapa pro Search Console: %s', 'pneukarnik-booking' ), home_url( '/wp-sitemap.xml' ) ) );
								?>
							</p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Rezervace v kalendáři telefonu (iCal)', 'pneukarnik-booking' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><label for="pnk-ical-url"><?php esc_html_e( 'Tajný odkaz', 'pneukarnik-booking' ); ?></label></th>
						<td>
							<input id="pnk-ical-url" type="text" readonly value="<?php echo esc_attr( $ical_url ); ?>" class="large-text" onclick="this.select()">
							<p class="description"><?php esc_html_e( 'Přidejte odkaz do kalendáře v telefonu (Google Kalendář: Přidat kalendář → Z URL, iPhone: Kalendáře → Přidat odebíraný kalendář). Odkaz obsahuje jména a telefony Zákazníků, nikomu ho neposílejte.', 'pneukarnik-booking' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Nový odkaz', 'pneukarnik-booking' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="regen_ical_token" value="1">
								<?php esc_html_e( 'Vytvořit nový tajný odkaz (starý hned přestane fungovat, třeba při ztrátě telefonu)', 'pneukarnik-booking' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Uložit nastavení', 'pneukarnik-booking' ) ); ?>
			</form>
		</div>
		<?php
	}

	private static function handle_save(): void {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pneukarnik_settings_nonce'] ) ), 'pneukarnik_save_settings' ) ) {
			wp_die( 'Nonce chyba.' );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Nemáte oprávnění.' );
		}

		// Working hours
		$days  = [ 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' ];
		$open  = $_POST['day_open'] ?? [];
		$from1 = $_POST['day_from1'] ?? [];
		$to1   = $_POST['day_to1'] ?? [];
		$from2 = $_POST['day_from2'] ?? [];
		$to2   = $_POST['day_to2'] ?? [];

		$hours = [];
		foreach ( $days as $day ) {
			if ( empty( $open[ $day ] ) ) {
				$hours[ $day ] = null;
				continue;
			}
			$phases = [];
			if ( ! empty( $from1[ $day ] ) && ! empty( $to1[ $day ] ) ) {
				$phases[] = [
					'from' => sanitize_text_field( $from1[ $day ] ),
					'to'   => sanitize_text_field( $to1[ $day ] ),
				];
			}
			if ( ! empty( $from2[ $day ] ) && ! empty( $to2[ $day ] ) ) {
				$phases[] = [
					'from' => sanitize_text_field( $from2[ $day ] ),
					'to'   => sanitize_text_field( $to2[ $day ] ),
				];
			}
			$hours[ $day ] = $phases ?: null;
		}
		$hours_saved = Pneukarnik_Working_Hours::save( $hours );

		// Online booking toggle
		Pneukarnik_Booking::save_online( ! empty( $_POST['booking_enabled'] ), (string) wp_unslash( $_POST['booking_disabled_msg'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitizuje save_online().
		Pneukarnik_Rate_Limit::save_limit( Pneukarnik_Rate_Limit::CREATE, (int) ( $_POST['rate_limit'] ?? Pneukarnik_Rate_Limit::limit( Pneukarnik_Rate_Limit::CREATE ) ) );
		Pneukarnik_Rate_Limit::save_limit( Pneukarnik_Rate_Limit::CANCEL, (int) ( $_POST['cancel_rate_limit'] ?? Pneukarnik_Rate_Limit::limit( Pneukarnik_Rate_Limit::CANCEL ) ) );

		// Scalar options
		update_option( 'pneukarnik_grid_step', max( 5, min( 240, (int) ( $_POST['grid_step'] ?? 30 ) ) ) );
		update_option( 'pneukarnik_lead_minutes', max( 0, min( 1440, (int) ( $_POST['lead_minutes'] ?? 60 ) ) ) );
		update_option( 'pneukarnik_horizon_days', max( 1, min( 365, (int) ( $_POST['horizon_days'] ?? 60 ) ) ) );
		update_option( 'pneukarnik_cancellation_hours', max( 0, min( 720, (int) ( $_POST['cancellation_hours'] ?? 24 ) ) ) );

		// E‑maily
		update_option( Pneukarnik_Notifications::OPTION_NOTIFY_CREATED, empty( $_POST['notify_created'] ) ? '0' : '1' );
		update_option( Pneukarnik_Notifications::OPTION_NOTIFY_CANCELLED, empty( $_POST['notify_cancelled'] ) ? '0' : '1' );
		$email_texts = (array) wp_unslash( $_POST['email_text'] ?? [] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitizuje se po položkách.
		foreach ( array_keys( Pneukarnik_Notifications::texts() ) as $key ) {
			update_option( 'pneukarnik_email_' . $key, sanitize_textarea_field( (string) ( $email_texts[ $key ] ?? '' ) ) );
		}
		// Kontakt a Pohotovost
		foreach ( [ 'company', 'phone', 'address', 'ico', 'dic' ] as $field ) {
			update_option( 'pneukarnik_' . $field, sanitize_text_field( wp_unslash( $_POST[ 'pneukarnik_' . $field ] ?? '' ) ) );
		}
		update_option( Pneukarnik_Contact::OPTION_EMAIL, sanitize_email( wp_unslash( $_POST['pneukarnik_email'] ?? '' ) ) );
		update_option( Pneukarnik_Contact::OPTION_MAPS_EMBED_URL, esc_url_raw( wp_unslash( $_POST['pneukarnik_maps_embed_url'] ?? '' ) ) );
		update_option( Pneukarnik_Contact::OPTION_EMERGENCY_ENABLED, empty( $_POST['emergency_enabled'] ) ? '0' : '1' );
		update_option( Pneukarnik_Contact::OPTION_EMERGENCY_PHONE, sanitize_text_field( wp_unslash( $_POST['emergency_phone'] ?? '' ) ) );
		update_option( Pneukarnik_Contact::OPTION_EMERGENCY_TEXT, sanitize_text_field( wp_unslash( $_POST['emergency_text'] ?? '' ) ) );
		update_option( 'pneukarnik_why_us', sanitize_textarea_field( wp_unslash( $_POST['why_us'] ?? '' ) ) );
		update_option( 'pneukarnik_founded_year', sanitize_text_field( wp_unslash( $_POST['founded_year'] ?? '' ) ) ); // Nesmyslný rok pneukarnik_founded_year() nevydá.
		$social = (array) wp_unslash( $_POST['social'] ?? [] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitizuje esc_url_raw po položkách.
		foreach ( array_keys( Pneukarnik_Contact::social_networks() ) as $network ) {
			update_option( Pneukarnik_Contact::OPTION_SOCIAL_PREFIX . $network, esc_url_raw( (string) ( $social[ $network ] ?? '' ), [ 'http', 'https' ] ) );
		}

		// Sezóny
		$posted  = (array) wp_unslash( $_POST['season'] ?? [] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- hodnoty projdou parse_day_month().
		$seasons = [];
		foreach ( array_keys( Pneukarnik_Season::names() ) as $key ) {
			foreach ( [ 'from', 'to', 'leasing_from' ] as $field ) {
				$seasons[ $key ][ $field ] = self::parse_day_month( (string) ( $posted[ $key ][ $field ] ?? '' ) );
			}
		}
		$seasons_saved = Pneukarnik_Season::save( $seasons );

		Pneukarnik_Reviews::save_settings(
			! empty( $_POST['reviews_enabled'] ),
			(string) wp_unslash( $_POST['reviews_api_key'] ?? '' ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitizuje save_settings().
			(string) wp_unslash( $_POST['reviews_place_id'] ?? '' ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitizuje save_settings().
		);

		Pneukarnik_Seo::save_settings(
			(string) wp_unslash( $_POST['matomo_url'] ?? '' ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitizuje save_settings().
			(string) wp_unslash( $_POST['matomo_site_id'] ?? '' ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitizuje save_settings().
			(string) wp_unslash( $_POST['google_verification'] ?? '' ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitizuje save_settings().
		);

		// iCal token regeneration
		if ( ! empty( $_POST['regen_ical_token'] ) ) {
			Pneukarnik_Rest_Calendar::regenerate_token();
		}

		$result = [];
		if ( ! $hours_saved ) {
			$result['hours_error'] = '1';
		}
		if ( ! $seasons_saved ) {
			$result['season_error'] = '1';
		}
		$result = $result ?: [ 'saved' => '1' ];
		wp_safe_redirect( add_query_arg( $result, admin_url( 'admin.php?page=pneukarnik-settings' ) ) );
		exit;
	}

	/**
	 * „15. 3.“ (i „15.3“) → „03-15“. Prázdné zůstane prázdné, neplatné se vrátí tak, jak je,
	 * a Pneukarnik_Season::save() ho odmítne.
	 */
	private static function parse_day_month( string $value ): string {
		$value = trim( sanitize_text_field( $value ) );
		if ( preg_match( '/^(\d{1,2})\s*\.\s*(\d{1,2})\s*\.?$/', $value, $m ) ) {
			return sprintf( '%02d-%02d', (int) $m[2], (int) $m[1] );
		}
		return $value;
	}

	/**
	 * „03-15“ → „15. 3.“
	 */
	private static function day_month_label( string $month_day ): string {
		if ( ! preg_match( '/^(\d{2})-(\d{2})$/', $month_day, $m ) ) {
			return $month_day;
		}
		return sprintf( '%d. %d.', (int) $m[2], (int) $m[1] );
	}
}
