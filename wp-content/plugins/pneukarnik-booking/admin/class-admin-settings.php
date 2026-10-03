<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Parametry v URL (filtry, stránkování, hlášky po přesměrování) jen řídí zobrazení, nic nemění.
// phpcs:disable WordPress.Security.NonceVerification.Recommended

/**
 * WP Admin settings page — Pracovní doba, pravidla Termínů, Lhůta zrušení, Sezóny, e‑maily, kontakty, iCal.
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
		$phone                = get_option( 'pneukarnik_phone', '' );
		$email                = get_option( 'pneukarnik_email', '' );
		$address              = get_option( 'pneukarnik_address', '' );
		$maps_embed_url       = get_option( 'pneukarnik_maps_embed_url', '' );
		$social_raw           = get_option( 'pneukarnik_social_links', '[]' );
		$booking_enabled      = (bool) get_option( 'pneukarnik_booking_enabled', '1' );
		$booking_disabled_msg = get_option( 'pneukarnik_booking_disabled_msg', 'Online rezervace jsou momentálně nedostupné. Kontaktujte nás telefonicky.' );
		$rate_limit           = (int) get_option( 'pneukarnik_rate_limit', '10' );
		$ical_token           = Pneukarnik_Rest_Calendar::get_or_create_token();
		$ical_url             = rest_url( PNEUKARNIK_REST_NAMESPACE . '/calendar' ) . '?token=' . $ical_token;
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
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Zpráva při vypnutí', 'pneukarnik-booking' ); ?></th>
						<td>
							<textarea name="booking_disabled_msg" rows="3" class="regular-text"><?php echo esc_textarea( $booking_disabled_msg ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Zobrazí se zákazníkům místo formuláře, pokud jsou rezervace vypnuté.', 'pneukarnik-booking' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Max rezervací per IP / hod', 'pneukarnik-booking' ); ?></th>
						<td>
							<input type="number" name="rate_limit" value="<?php echo esc_attr( (string) $rate_limit ); ?>" min="1" max="100">
							<p class="description"><?php esc_html_e( 'Platí jen pro nepřihlášené uživatele. Při překročení: HTTP 429.', 'pneukarnik-booking' ); ?></p>
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

				<h2><?php esc_html_e( 'E‑maily', 'pneukarnik-booking' ); ?></h2>
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
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Telefon', 'pneukarnik-booking' ); ?></th>
						<td><input type="text" name="pneukarnik_phone" value="<?php echo esc_attr( $phone ); ?>"></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Kontaktní email', 'pneukarnik-booking' ); ?></th>
						<td><input type="email" name="pneukarnik_email" value="<?php echo esc_attr( $email ); ?>"></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Adresa', 'pneukarnik-booking' ); ?></th>
						<td><input type="text" name="pneukarnik_address" value="<?php echo esc_attr( $address ); ?>" class="regular-text" placeholder="Např. Testovací 1, Mladá Boleslav"></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Google Maps embed URL', 'pneukarnik-booking' ); ?></th>
						<td>
							<input type="url" name="pneukarnik_maps_embed_url" value="<?php echo esc_attr( $maps_embed_url ); ?>" class="large-text">
							<p class="description"><?php esc_html_e( 'URL z Google Maps → Sdílet → Vložit mapu → atribut src iframe.', 'pneukarnik-booking' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Sociální sítě (JSON)', 'pneukarnik-booking' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Social links JSON', 'pneukarnik-booking' ); ?></th>
						<td>
							<textarea name="social_links" rows="6" cols="60"><?php echo esc_textarea( $social_raw ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Format: [{"platform":"facebook","url":"https://...","label":"Facebook"}]', 'pneukarnik-booking' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'iCal feed', 'pneukarnik-booking' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'URL pro kalendář', 'pneukarnik-booking' ); ?></th>
						<td>
							<input type="text" readonly value="<?php echo esc_attr( $ical_url ); ?>" class="large-text" onclick="this.select()">
							<p class="description"><?php esc_html_e( 'Přidejte tuto URL do Google Calendar / Apple Calendar jako webový kalendář.', 'pneukarnik-booking' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Resetovat token', 'pneukarnik-booking' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="regen_ical_token" value="1">
								<?php esc_html_e( 'Vygenerovat nový token (stará URL přestane fungovat)', 'pneukarnik-booking' ); ?>
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
		update_option( 'pneukarnik_booking_enabled', ! empty( $_POST['booking_enabled'] ) ? '1' : '0' );
		update_option( 'pneukarnik_booking_disabled_msg', sanitize_textarea_field( wp_unslash( $_POST['booking_disabled_msg'] ?? '' ) ) );
		update_option( 'pneukarnik_rate_limit', max( 1, min( 100, (int) ( $_POST['rate_limit'] ?? 10 ) ) ) );

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
		update_option( 'pneukarnik_phone', sanitize_text_field( wp_unslash( $_POST['pneukarnik_phone'] ?? '' ) ) );
		update_option( 'pneukarnik_email', sanitize_email( wp_unslash( $_POST['pneukarnik_email'] ?? '' ) ) );
		update_option( 'pneukarnik_address', sanitize_text_field( wp_unslash( $_POST['pneukarnik_address'] ?? '' ) ) );
		update_option( 'pneukarnik_maps_embed_url', esc_url_raw( wp_unslash( $_POST['pneukarnik_maps_embed_url'] ?? '' ) ) );

		// Sezóny
		$posted  = (array) wp_unslash( $_POST['season'] ?? [] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- hodnoty projdou parse_day_month().
		$seasons = [];
		foreach ( array_keys( Pneukarnik_Season::names() ) as $key ) {
			foreach ( [ 'from', 'to', 'leasing_from' ] as $field ) {
				$seasons[ $key ][ $field ] = self::parse_day_month( (string) ( $posted[ $key ][ $field ] ?? '' ) );
			}
		}
		$seasons_saved = Pneukarnik_Season::save( $seasons );

		// iCal token regeneration
		if ( ! empty( $_POST['regen_ical_token'] ) ) {
			Pneukarnik_Rest_Calendar::regenerate_token();
		}

		// Social links — validate JSON
		$social_raw     = wp_unslash( $_POST['social_links'] ?? '[]' );
		$social_decoded = json_decode( $social_raw, true );
		if ( is_array( $social_decoded ) ) {
			update_option( 'pneukarnik_social_links', wp_json_encode( $social_decoded ) );
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
