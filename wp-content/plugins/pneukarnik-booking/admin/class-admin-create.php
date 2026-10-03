<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Parametry v URL (filtry, stránkování, hlášky po přesměrování) jen řídí zobrazení, nic nemění.
// phpcs:disable WordPress.Security.NonceVerification.Recommended

/**
 * WP Admin page: create a booking on behalf of a customer.
 * Full validation via Pneukarnik_Booking::create() — slot engine, race protection.
 */
class Pneukarnik_Admin_Create {

	public static function render_page(): void {
		if ( ! current_user_can( 'pneukarnik_manage_bookings' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Nemáte oprávnění.', 'pneukarnik-booking' ) );
		}

		$error   = '';
		$success = isset( $_GET['created'] );
		$values  = [];

		if ( isset( $_POST['pneukarnik_create_nonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce ověří handle_create().
			$result = self::handle_create( $values, $error );
			if ( $result ) {
				wp_safe_redirect( add_query_arg( 'created', '1', admin_url( 'admin.php?page=pneukarnik-booking' ) ) );
				exit;
			}
		}

		$services = self::get_bookable_services();

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Nová rezervace', 'pneukarnik-booking' ); ?></h1>

			<?php if ( $error ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
			<?php endif; ?>

			<form method="post" style="max-width:600px">
				<?php wp_nonce_field( 'pneukarnik_create_booking', 'pneukarnik_create_nonce' ); ?>

				<table class="form-table">
					<tr>
						<th><label for="service_id"><?php esc_html_e( 'Služba *', 'pneukarnik-booking' ); ?></label></th>
						<td>
							<select id="service_id" name="service_id" required>
								<option value=""><?php esc_html_e( '— vyberte —', 'pneukarnik-booking' ); ?></option>
								<?php foreach ( $services as $s ) : ?>
									<option value="<?php echo (int) $s['id']; ?>" <?php selected( (string) ( $values['service_id'] ?? '' ), (string) $s['id'] ); ?>>
										<?php echo esc_html( $s['name'] . ' (' . $s['duration'] . ' min)' ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="booking_date"><?php esc_html_e( 'Datum *', 'pneukarnik-booking' ); ?></label></th>
						<td>
							<input id="booking_date" name="booking_date" type="text"
								value="<?php echo esc_attr( $values['booking_date_raw'] ?? '' ); ?>"
								placeholder="DD.MM.RRRR" size="12" required>
						</td>
					</tr>
					<tr>
						<th><label for="time_start"><?php esc_html_e( 'Čas (HH:MM) *', 'pneukarnik-booking' ); ?></label></th>
						<td>
							<input id="time_start" name="time_start" type="time"
								value="<?php echo esc_attr( $values['time_start'] ?? '' ); ?>" required>
							<p class="description"><?php esc_html_e( 'Slot musí být dostupný dle pracovní doby.', 'pneukarnik-booking' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="customer_name"><?php esc_html_e( 'Jméno a příjmení *', 'pneukarnik-booking' ); ?></label></th>
						<td><input id="customer_name" name="customer_name" type="text" class="regular-text"
							value="<?php echo esc_attr( $values['customer_name'] ?? '' ); ?>" required></td>
					</tr>
					<tr>
						<th><label for="customer_company"><?php esc_html_e( 'Firma', 'pneukarnik-booking' ); ?></label></th>
						<td><input id="customer_company" name="customer_company" type="text" class="regular-text"
							value="<?php echo esc_attr( $values['customer_company'] ?? '' ); ?>"></td>
					</tr>
					<tr>
						<th><label for="customer_plate"><?php esc_html_e( 'SPZ *', 'pneukarnik-booking' ); ?></label></th>
						<td><input id="customer_plate" name="customer_plate" type="text"
							value="<?php echo esc_attr( $values['customer_plate'] ?? '' ); ?>" required></td>
					</tr>
					<tr>
						<th><label for="customer_email"><?php esc_html_e( 'Email *', 'pneukarnik-booking' ); ?></label></th>
						<td><input id="customer_email" name="customer_email" type="email" class="regular-text"
							value="<?php echo esc_attr( $values['customer_email'] ?? '' ); ?>" required></td>
					</tr>
					<tr>
						<th><label for="customer_phone"><?php esc_html_e( 'Telefon *', 'pneukarnik-booking' ); ?></label></th>
						<td><input id="customer_phone" name="customer_phone" type="tel"
							value="<?php echo esc_attr( $values['customer_phone'] ?? '' ); ?>" required></td>
					</tr>
					<tr>
						<th><label for="customer_note"><?php esc_html_e( 'Poznámka', 'pneukarnik-booking' ); ?></label></th>
						<td><textarea id="customer_note" name="customer_note" rows="3" cols="40" maxlength="500"><?php echo esc_textarea( $values['customer_note'] ?? '' ); ?></textarea></td>
					</tr>
				</table>

				<?php submit_button( __( 'Vytvořit rezervaci', 'pneukarnik-booking' ) ); ?>
			</form>
		</div>
		<?php
	}

	private static function handle_create( array &$values, string &$error ): bool {
		if ( ! wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST['pneukarnik_create_nonce'] ) ),
			'pneukarnik_create_booking'
		) ) {
			wp_die( 'Nonce chyba.' );
		}

		$raw_date = sanitize_text_field( wp_unslash( $_POST['booking_date'] ?? '' ) );
		$values   = [
			'service_id'       => (int) ( $_POST['service_id'] ?? 0 ),
			'booking_date'     => pneukarnik_parse_date_cz( $raw_date ),
			'booking_date_raw' => $raw_date,
			'time_start'       => sanitize_text_field( wp_unslash( $_POST['time_start'] ?? '' ) ),
			'customer_name'    => sanitize_text_field( wp_unslash( $_POST['customer_name'] ?? '' ) ),
			'customer_company' => sanitize_text_field( wp_unslash( $_POST['customer_company'] ?? '' ) ) ?: null,
			'customer_plate'   => sanitize_text_field( wp_unslash( $_POST['customer_plate'] ?? '' ) ),
			'customer_email'   => sanitize_email( wp_unslash( $_POST['customer_email'] ?? '' ) ),
			'customer_phone'   => sanitize_text_field( wp_unslash( $_POST['customer_phone'] ?? '' ) ),
			'customer_note'    => sanitize_textarea_field( wp_unslash( $_POST['customer_note'] ?? '' ) ),
		];

		// Telefonická objednávka: souhlas GDPR se neuděluje přes web. Celé přepracování formuláře
		// (včetně víc Služeb) je #11.
		$result = Pneukarnik_Booking::create(
			[
				'service_ids' => [ $values['service_id'] ],
				'date'        => $values['booking_date'],
				'time'        => $values['time_start'],
				'name'        => $values['customer_name'],
				'company'     => (string) $values['customer_company'],
				'phone'       => $values['customer_phone'],
				'email'       => $values['customer_email'],
				'plate'       => $values['customer_plate'],
				'note'        => $values['customer_note'],
			],
			Pneukarnik_Booking::SOURCE_ADMIN
		);

		if ( $result['ok'] ) {
			return true;
		}

		$messages = [
			'booking.invalid_fields'       => 'Zkontrolujte pole: %s.',
			'booking.service_not_found'    => 'Služba neexistuje.',
			'booking.service_not_bookable' => 'Tato služba není rezervovatelná.',
			'booking.slot_unavailable'     => 'Vybraný čas není v nabídce (mimo Pracovní dobu, mřížku nebo horizont).',
			'booking.slot_taken'           => 'V tomto čase už je jiná Rezervace.',
		];

		$error = sprintf( $messages[ $result['code'] ] ?? $result['code'], implode( ', ', array_keys( $result['errors'] ?? [] ) ) );
		return false;
	}

	/**
	 * @return list<array{id:int,name:string,duration:int}>
	 */
	private static function get_bookable_services(): array {
		$services = [];
		foreach ( Pneukarnik_Service::published() as $service ) {
			if ( $service->bookable ) {
				$services[] = [
					'id'       => $service->id,
					'name'     => $service->title,
					'duration' => $service->duration,
				];
			}
		}
		return $services;
	}
}
