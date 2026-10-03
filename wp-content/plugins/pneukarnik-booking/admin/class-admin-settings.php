<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Parametry v URL (filtry, stránkování, hlášky po přesměrování) jen řídí zobrazení, nic nemění.
// phpcs:disable WordPress.Security.NonceVerification.Recommended

/**
 * WP Admin settings page — working hours, season, time_gap, cancellation_days, contacts, iCal.
 */
class Pneukarnik_Admin_Settings {

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Nemáte oprávnění.', 'pneukarnik-booking' ) );
		}

		if ( isset( $_POST['pneukarnik_settings_nonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce ověří handle_save().
			self::handle_save();
		}

		$hours                = Pneukarnik_Working_Hours::get_all();
		$season               = Pneukarnik_Season::get_settings();
		$time_gap             = Pneukarnik_Working_Hours::get_time_gap();
		$cancellation_days    = Pneukarnik_Working_Hours::get_cancellation_days();
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
						<th><?php esc_html_e( 'Rozestup mezi službami (min)', 'pneukarnik-booking' ); ?></th>
						<td><input type="number" name="time_gap" value="<?php echo esc_attr( (string) $time_gap ); ?>" min="0" max="120"></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Min. dní pro zrušení', 'pneukarnik-booking' ); ?></th>
						<td><input type="number" name="cancellation_days" value="<?php echo esc_attr( (string) $cancellation_days ); ?>" min="0" max="30"></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Sezóna', 'pneukarnik-booking' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Od (MM-DD)', 'pneukarnik-booking' ); ?></th>
						<td><input type="text" name="season_from" value="<?php echo esc_attr( $season['from'] ?? '' ); ?>" placeholder="10-01"></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Do (MM-DD)', 'pneukarnik-booking' ); ?></th>
						<td><input type="text" name="season_to" value="<?php echo esc_attr( $season['to'] ?? '' ); ?>" placeholder="04-30"></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Vynutit sezónu', 'pneukarnik-booking' ); ?></th>
						<td><input type="checkbox" name="season_forced" value="1" <?php checked( $season['forced'] ); ?>></td>
					</tr>
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
		Pneukarnik_Working_Hours::save( $hours );

		// Online booking toggle
		update_option( 'pneukarnik_booking_enabled', ! empty( $_POST['booking_enabled'] ) ? '1' : '0' );
		update_option( 'pneukarnik_booking_disabled_msg', sanitize_textarea_field( wp_unslash( $_POST['booking_disabled_msg'] ?? '' ) ) );
		update_option( 'pneukarnik_rate_limit', max( 1, min( 100, (int) ( $_POST['rate_limit'] ?? 10 ) ) ) );

		// Scalar options
		update_option( 'pneukarnik_time_gap', (int) ( $_POST['time_gap'] ?? 0 ) );
		update_option( 'pneukarnik_cancellation_days', (int) ( $_POST['cancellation_days'] ?? 1 ) );
		update_option( 'pneukarnik_phone', sanitize_text_field( wp_unslash( $_POST['pneukarnik_phone'] ?? '' ) ) );
		update_option( 'pneukarnik_email', sanitize_email( wp_unslash( $_POST['pneukarnik_email'] ?? '' ) ) );
		update_option( 'pneukarnik_address', sanitize_text_field( wp_unslash( $_POST['pneukarnik_address'] ?? '' ) ) );
		update_option( 'pneukarnik_maps_embed_url', esc_url_raw( wp_unslash( $_POST['pneukarnik_maps_embed_url'] ?? '' ) ) );

		// Season
		Pneukarnik_Season::save(
			sanitize_text_field( wp_unslash( $_POST['season_from'] ?? '' ) ),
			sanitize_text_field( wp_unslash( $_POST['season_to'] ?? '' ) ),
			! empty( $_POST['season_forced'] )
		);

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

		wp_safe_redirect( add_query_arg( 'saved', '1', wp_get_referer() ?: admin_url( 'admin.php?page=pneukarnik-settings' ) ) );
		exit;
	}
}
