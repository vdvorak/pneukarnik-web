<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Parametry v URL (filtry, stránkování, hlášky po přesměrování) jen řídí zobrazení, nic nemění.
// phpcs:disable WordPress.Security.NonceVerification.Recommended

/**
 * WP Admin bookings list page.
 * Filter: date_from, date_to, status, search (name/plate/email).
 * Actions: cancel booking.
 * Pagination: 25 per page.
 */
class Pneukarnik_Admin_Bookings {

	private const PER_PAGE = 25;

	public static function render_page(): void {
		if ( ! current_user_can( 'pneukarnik_view_bookings' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Nemáte oprávnění.', 'pneukarnik-booking' ) );
		}

		$can_cancel = current_user_can( 'pneukarnik_manage_bookings' ) || current_user_can( 'manage_options' );

		// Handle cancel POST first — form posts to URL that still has confirm_delete in GET
		if ( isset( $_POST['pneukarnik_cancel_nonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce ověří handle_cancel_action().
			self::handle_cancel_action();
			// handle_cancel_action() always redirects + exits
		}

		// New booking page
		if ( isset( $_GET['action'] ) && $_GET['action'] === 'new' && $can_cancel ) {
			Pneukarnik_Admin_Create::render_page();
			return;
		}

		// Confirm-delete page
		if ( isset( $_GET['confirm_delete'] ) && $can_cancel ) {
			self::render_confirm_delete( (int) $_GET['confirm_delete'] );
			return;
		}

		$filters    = self::get_filters();
		$page       = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
		$offset     = ( $page - 1 ) * self::PER_PAGE;
		$result     = self::query_bookings( $filters, $offset, self::PER_PAGE );
		$total      = $result['total'];
		$bookings   = $result['rows'];
		$page_count = (int) ceil( $total / self::PER_PAGE );

		?>
		<div class="wrap">
			<h1>
				<?php esc_html_e( 'Rezervace', 'pneukarnik-booking' ); ?>
				<?php if ( $can_cancel ) : ?>
					<a href="
					<?php
					echo esc_url(
						add_query_arg(
							[
								'page'   => 'pneukarnik-booking',
								'action' => 'new',
							],
							admin_url( 'admin.php' )
						)
					);
					?>
								" class="page-title-action">
						<?php esc_html_e( '+ Nová rezervace', 'pneukarnik-booking' ); ?>
					</a>
				<?php endif; ?>
			</h1>

			<?php if ( isset( $_GET['cancelled'] ) ) : ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Rezervace byla smazána.', 'pneukarnik-booking' ); ?></p></div>
			<?php endif; ?>

			<?php if ( isset( $_GET['created'] ) ) : ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Rezervace vytvořena.', 'pneukarnik-booking' ); ?></p></div>
			<?php endif; ?>

			<?php if ( isset( $_GET['cancel_error'] ) ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $_GET['cancel_error'] ); ?></p></div>
			<?php endif; ?>

			<!-- PDF export -->
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:12px">
				<input type="hidden" name="action" value="pneukarnik_export_day_pdf">
				<?php wp_nonce_field( 'pneukarnik_export_day_pdf', 'pneukarnik_pdf_nonce' ); ?>
				<label>
					<?php esc_html_e( 'Tisk dne', 'pneukarnik-booking' ); ?>
					<input type="text" name="pdf_date" value="<?php echo esc_attr( Pneukarnik_Clock::today()->format( 'd.m.Y' ) ); ?>" placeholder="DD.MM.RRRR" size="12">
				</label>
				<?php submit_button( __( 'Stáhnout PDF', 'pneukarnik-booking' ), 'secondary', '', false ); ?>
			</form>

			<!-- Filters -->
			<form method="get">
				<input type="hidden" name="page" value="pneukarnik-booking">
				<input type="hidden" name="filter_submitted" value="1">
				<div style="display:flex;gap:16px;align-items:flex-end;flex-wrap:wrap;margin-bottom:12px">
					<label>
						<?php esc_html_e( 'Od', 'pneukarnik-booking' ); ?>
						<input type="text" name="date_from" value="<?php echo esc_attr( $filters['date_from_raw'] ); ?>" placeholder="DD.MM.RRRR" size="12">
					</label>
					<label>
						<?php esc_html_e( 'Do', 'pneukarnik-booking' ); ?>
						<input type="text" name="date_to" value="<?php echo esc_attr( $filters['date_to_raw'] ); ?>" placeholder="DD.MM.RRRR" size="12">
					</label>
					<div>
						<?php esc_html_e( 'Stav', 'pneukarnik-booking' ); ?><br>
						<label style="font-weight:normal;margin-right:8px">
							<input type="checkbox" name="show_confirmed" value="1" <?php checked( $filters['show_confirmed'] ); ?>>
							<?php esc_html_e( 'Potvrzena', 'pneukarnik-booking' ); ?>
						</label>
						<label style="font-weight:normal">
							<input type="checkbox" name="show_cancelled" value="1" <?php checked( $filters['show_cancelled'] ); ?>>
							<?php esc_html_e( 'Zrušena', 'pneukarnik-booking' ); ?>
						</label>
					</div>
					<label>
						<?php esc_html_e( 'Hledat', 'pneukarnik-booking' ); ?>
						<input type="text" name="search" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="<?php esc_attr_e( 'Jméno, SPZ, email', 'pneukarnik-booking' ); ?>">
					</label>
					<?php submit_button( __( 'Filtrovat', 'pneukarnik-booking' ), 'secondary', '', false ); ?>
				</div>
			</form>

			<p>
				<?php
				/* translators: %d: počet rezervací */
				printf( esc_html__( 'Celkem: %d rezervací', 'pneukarnik-booking' ), (int) $total );
				?>
			</p>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th style="width:50px">#</th>
						<th><?php esc_html_e( 'Datum', 'pneukarnik-booking' ); ?></th>
						<th><?php esc_html_e( 'Čas', 'pneukarnik-booking' ); ?></th>
						<th><?php esc_html_e( 'Zákazník', 'pneukarnik-booking' ); ?></th>
						<th><?php esc_html_e( 'SPZ', 'pneukarnik-booking' ); ?></th>
						<th><?php esc_html_e( 'Email', 'pneukarnik-booking' ); ?></th>
						<th><?php esc_html_e( 'Telefon', 'pneukarnik-booking' ); ?></th>
						<th><?php esc_html_e( 'Služba', 'pneukarnik-booking' ); ?></th>
						<th><?php esc_html_e( 'Stav', 'pneukarnik-booking' ); ?></th>
						<?php if ( $can_cancel ) : ?>
						<th><?php esc_html_e( 'Akce', 'pneukarnik-booking' ); ?></th>
						<?php endif; ?>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $bookings ) ) : ?>
						<tr><td colspan="<?php echo $can_cancel ? 10 : 9; ?>"><?php esc_html_e( 'Žádné rezervace.', 'pneukarnik-booking' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $bookings as $b ) : ?>
							<tr>
								<td><?php echo (int) $b['id']; ?></td>
								<td><?php echo esc_html( pneukarnik_format_date( $b['booking_date'] ) ); ?></td>
								<td><?php echo esc_html( substr( $b['time_start'], 0, 5 ) . '–' . substr( $b['time_end'], 0, 5 ) ); ?></td>
								<td><?php echo esc_html( $b['customer_name'] ); ?></td>
								<td><?php echo esc_html( $b['customer_plate'] ); ?></td>
								<td><?php echo esc_html( $b['customer_email'] ); ?></td>
								<td><?php echo esc_html( $b['customer_phone'] ); ?></td>
								<td><?php echo esc_html( $b['service_name'] ); ?></td>
								<td>
									<?php if ( $b['status'] === 'CONFIRMED' ) : ?>
										<span style="color:green"><?php esc_html_e( 'Potvrzena', 'pneukarnik-booking' ); ?></span>
									<?php else : ?>
										<span style="color:red"><?php esc_html_e( 'Zrušena', 'pneukarnik-booking' ); ?></span>
										<?php if ( $b['cancelled_at'] ) : ?>
											<?php
											$dt = Pneukarnik_Clock::at( $b['cancelled_at'] );
											?>
											<br><small style="color:#999"><?php echo esc_html( $dt->format( 'd.m.Y H:i' ) ); ?></small>
										<?php endif; ?>
										<?php if ( $b['cancel_reason'] ) : ?>
											<br><small style="color:#999"><?php echo esc_html( $b['cancel_reason'] ); ?></small>
										<?php endif; ?>
									<?php endif; ?>
								</td>
								<?php if ( $can_cancel ) : ?>
								<td>
									<?php if ( $b['status'] === 'CONFIRMED' ) : ?>
										<a href="
										<?php
										echo esc_url(
											add_query_arg(
												[
													'page' => 'pneukarnik-booking',
													'confirm_delete' => $b['id'],
												],
												admin_url( 'admin.php' )
											)
										);
										?>
													"
											class="button button-small button-link-delete">
											<?php esc_html_e( 'Smazat', 'pneukarnik-booking' ); ?>
										</a>
									<?php endif; ?>
								</td>
								<?php endif; ?>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<!-- Pagination -->
			<?php if ( $page_count > 1 ) : ?>
				<div class="tablenav bottom">
					<div class="tablenav-pages">
						<?php
						echo wp_kses_post(
							paginate_links(
								[
									'base'    => add_query_arg( 'paged', '%#%' ),
									'format'  => '',
									'current' => $page,
									'total'   => $page_count,
								]
							)
						);
						?>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function handle_cancel_action(): void {
		$booking_id = (int) ( $_POST['cancel_booking_id'] ?? 0 );
		if ( ! $booking_id ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pneukarnik_cancel_nonce'] ) ), 'pneukarnik_cancel_booking_' . $booking_id ) ) {
			wp_die( 'Nonce chyba.' );
		}
		if ( ! current_user_can( 'pneukarnik_manage_bookings' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Nemáte oprávnění.' );
		}

		$reason = isset( $_POST['cancel_reason'] ) ? sanitize_text_field( wp_unslash( $_POST['cancel_reason'] ) ) : null;
		$result = Pneukarnik_Cancellation::cancel_by_provozovatel( $booking_id, $reason ?: null );

		if ( $result['ok'] ) {
			wp_safe_redirect( add_query_arg( 'cancelled', '1', admin_url( 'admin.php?page=pneukarnik-booking' ) ) );
		} else {
			wp_safe_redirect( add_query_arg( 'cancel_error', rawurlencode( $result['code'] ), admin_url( 'admin.php?page=pneukarnik-booking' ) ) );
		}
		exit;
	}

	private static function get_filters(): array {
		$raw_from = sanitize_text_field( $_GET['date_from'] ?? '' );
		$raw_to   = sanitize_text_field( $_GET['date_to'] ?? '' );
		return [
			'date_from'      => $raw_from ? pneukarnik_parse_date_cz( $raw_from ) : '',
			'date_from_raw'  => $raw_from,
			'date_to'        => $raw_to ? pneukarnik_parse_date_cz( $raw_to ) : '',
			'date_to_raw'    => $raw_to,
			'show_confirmed' => isset( $_GET['filter_submitted'] ) ? isset( $_GET['show_confirmed'] ) : true,
			'show_cancelled' => isset( $_GET['filter_submitted'] ) ? isset( $_GET['show_cancelled'] ) : false,
			'search'         => sanitize_text_field( $_GET['search'] ?? '' ),
		];
	}

	private static function query_bookings( array $filters, int $offset, int $limit ): array {
		global $wpdb;
		$table  = Pneukarnik_DB::bookings_table();
		$wheres = [ '1=1' ];
		$args   = [];

		if ( $filters['date_from'] ) {
			$wheres[] = 'booking_date >= %s';
			$args[]   = $filters['date_from'];
		}
		if ( $filters['date_to'] ) {
			$wheres[] = 'booking_date <= %s';
			$args[]   = $filters['date_to'];
		}
		if ( $filters['show_confirmed'] && ! $filters['show_cancelled'] ) {
			$wheres[] = "status = 'CONFIRMED'";
		} elseif ( $filters['show_cancelled'] && ! $filters['show_confirmed'] ) {
			$wheres[] = "status = 'CANCELLED'";
		} elseif ( ! $filters['show_confirmed'] ) {
			$wheres[] = '1=0';
		}
		if ( $filters['search'] ) {
			$like     = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
			$wheres[] = '(customer_name LIKE %s OR customer_plate LIKE %s OR customer_email LIKE %s)';
			$args[]   = $like;
			$args[]   = $like;
			$args[]   = $like;
		}

		$where_sql = implode( ' AND ', $wheres );

		// $where_sql skládá jen pevné fragmenty s placeholdery, hodnoty jdou přes prepare().
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		$total = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE {$where_sql}", $table, ...$args )
		);

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE {$where_sql} ORDER BY booking_date DESC, time_start DESC LIMIT %d OFFSET %d",
				$table,
				...[ ...$args, $limit, $offset ]
			),
			ARRAY_A
		);
		// phpcs:enable

		return [
			'total' => $total,
			'rows'  => Pneukarnik_Booking::with_service_names( $rows ?: [] ),
		];
	}

	private static function render_confirm_delete( int $booking_id ): void {
		$booking = Pneukarnik_Booking::get_by_id( $booking_id );

		if ( ! $booking || $booking['status'] !== 'CONFIRMED' ) {
			?>
			<div class="wrap">
				<h1><?php esc_html_e( 'Smazat rezervaci', 'pneukarnik-booking' ); ?></h1>
				<div class="notice notice-error"><p><?php esc_html_e( 'Rezervace nenalezena nebo již zrušena.', 'pneukarnik-booking' ); ?></p></div>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=pneukarnik-booking' ) ); ?>" class="button"><?php esc_html_e( '← Zpět na seznam', 'pneukarnik-booking' ); ?></a>
			</div>
			<?php
			return;
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Potvrdit smazání rezervace', 'pneukarnik-booking' ); ?></h1>

			<div class="notice notice-warning" style="padding:16px">
				<p><strong><?php esc_html_e( 'Chystáte se smazat tuto rezervaci:', 'pneukarnik-booking' ); ?></strong></p>
				<table style="border-collapse:collapse;margin-top:8px">
					<tr><td style="padding:4px 12px 4px 0;color:#666"><?php esc_html_e( 'Zákazník', 'pneukarnik-booking' ); ?></td><td><strong><?php echo esc_html( $booking['customer_name'] ); ?></strong></td></tr>
					<tr><td style="padding:4px 12px 4px 0;color:#666"><?php esc_html_e( 'Firma', 'pneukarnik-booking' ); ?></td><td><?php echo esc_html( $booking['customer_company'] ?: '—' ); ?></td></tr>
					<tr><td style="padding:4px 12px 4px 0;color:#666"><?php esc_html_e( 'SPZ', 'pneukarnik-booking' ); ?></td><td><?php echo esc_html( $booking['customer_plate'] ); ?></td></tr>
					<tr><td style="padding:4px 12px 4px 0;color:#666"><?php esc_html_e( 'Datum', 'pneukarnik-booking' ); ?></td><td><?php echo esc_html( pneukarnik_format_date( $booking['booking_date'] ) ); ?></td></tr>
					<tr><td style="padding:4px 12px 4px 0;color:#666"><?php esc_html_e( 'Čas', 'pneukarnik-booking' ); ?></td><td><?php echo esc_html( $booking['time_start'] . '–' . $booking['time_end'] ); ?></td></tr>
					<tr><td style="padding:4px 12px 4px 0;color:#666"><?php esc_html_e( 'Služba', 'pneukarnik-booking' ); ?></td><td><?php echo esc_html( $booking['service_name'] ); ?></td></tr>
					<tr><td style="padding:4px 12px 4px 0;color:#666"><?php esc_html_e( 'Email', 'pneukarnik-booking' ); ?></td><td><?php echo esc_html( $booking['customer_email'] ); ?></td></tr>
				</table>
				<p style="margin-top:12px;color:#666"><?php esc_html_e( 'Zákazníkovi bude odeslán email o zrušení.', 'pneukarnik-booking' ); ?></p>
			</div>

			<form method="post" style="margin-top:16px">
				<?php wp_nonce_field( 'pneukarnik_cancel_booking_' . $booking_id, 'pneukarnik_cancel_nonce' ); ?>
				<input type="hidden" name="cancel_booking_id" value="<?php echo (int) $booking_id; ?>">
				<table class="form-table" style="width:auto">
					<tr>
						<th><label for="cancel_reason"><?php esc_html_e( 'Důvod zrušení', 'pneukarnik-booking' ); ?></label></th>
						<td><input id="cancel_reason" name="cancel_reason" type="text" class="regular-text" placeholder="<?php esc_attr_e( 'Volitelné', 'pneukarnik-booking' ); ?>"></td>
					</tr>
				</table>
				<p>
					<button type="submit" class="button button-primary" style="background:#d63638;border-color:#d63638">
						<?php esc_html_e( 'Potvrdit smazání', 'pneukarnik-booking' ); ?>
					</button>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=pneukarnik-booking' ) ); ?>" class="button" style="margin-left:8px">
						<?php esc_html_e( 'Zrušit', 'pneukarnik-booking' ); ?>
					</a>
				</p>
			</form>
		</div>
		<?php
	}
}
