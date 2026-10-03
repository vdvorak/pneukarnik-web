<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Parametry v URL (filtry, stránkování) jen řídí zobrazení, nic nemění.
// phpcs:disable WordPress.Security.NonceVerification.Recommended

/**
 * Seznam Rezervací v administraci: filtr podle data a stavu, hledání podle jména, SPZ,
 * telefonu a e‑mailu (stejné hledání jako GET /admin/bookings). Úprava a Zrušení jsou
 * v detailu Rezervace v Kalendáři.
 */
final class Pneukarnik_Admin_Bookings {

	public const PAGE = 'pneukarnik-bookings-list';

	public static function url(): string {
		return add_query_arg( 'page', self::PAGE, admin_url( 'admin.php' ) );
	}

	public static function render_page(): void {
		if ( ! Pneukarnik_Access::can_view() ) {
			wp_die( esc_html__( 'Nemáte oprávnění.', 'pneukarnik-booking' ) );
		}

		$filters = self::filters();
		$page    = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
		$result  = Pneukarnik_Booking::search( $filters, $page, Pneukarnik_Rest_Admin::PER_PAGE );
		$pages   = (int) ceil( $result['total'] / Pneukarnik_Rest_Admin::PER_PAGE );
		$sources = [
			Pneukarnik_Booking::SOURCE_WEB          => __( 'web', 'pneukarnik-booking' ),
			Pneukarnik_Booking::SOURCE_PROVOZOVATEL => __( 'Provozovatel', 'pneukarnik-booking' ),
		];
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Seznam rezervací', 'pneukarnik-booking' ); ?></h1>
			<a class="page-title-action" href="<?php echo esc_url( Pneukarnik_Admin_Calendar::url() ); ?>"><?php esc_html_e( 'Kalendář', 'pneukarnik-booking' ); ?></a>
			<hr class="wp-header-end">

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:12px 0">
				<input type="hidden" name="action" value="pneukarnik_export_day_pdf">
				<?php wp_nonce_field( 'pneukarnik_export_day_pdf', 'pneukarnik_pdf_nonce' ); ?>
				<label>
					<?php esc_html_e( 'Tisk dne', 'pneukarnik-booking' ); ?>
					<input type="text" name="pdf_date" value="<?php echo esc_attr( Pneukarnik_Clock::today()->format( 'd.m.Y' ) ); ?>" placeholder="DD.MM.RRRR" size="12">
				</label>
				<?php submit_button( __( 'Stáhnout PDF', 'pneukarnik-booking' ), 'secondary', '', false ); ?>
			</form>

			<form method="get" class="pnk-list-filters" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;margin-bottom:12px">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>">
				<input type="hidden" name="filtr" value="1">
				<p>
					<label for="pnk-from"><?php esc_html_e( 'Od', 'pneukarnik-booking' ); ?></label><br>
					<input id="pnk-from" type="date" name="from" value="<?php echo esc_attr( $filters['from'] ); ?>">
				</p>
				<p>
					<label for="pnk-to"><?php esc_html_e( 'Do', 'pneukarnik-booking' ); ?></label><br>
					<input id="pnk-to" type="date" name="to" value="<?php echo esc_attr( $filters['to'] ); ?>">
				</p>
				<p>
					<label for="pnk-status"><?php esc_html_e( 'Stav', 'pneukarnik-booking' ); ?></label><br>
					<select id="pnk-status" name="status">
						<option value="CONFIRMED" <?php selected( $filters['status'], 'CONFIRMED' ); ?>><?php esc_html_e( 'Potvrzené', 'pneukarnik-booking' ); ?></option>
						<option value="CANCELLED" <?php selected( $filters['status'], 'CANCELLED' ); ?>><?php esc_html_e( 'Zrušené', 'pneukarnik-booking' ); ?></option>
						<option value="" <?php selected( $filters['status'], '' ); ?>><?php esc_html_e( 'Všechny', 'pneukarnik-booking' ); ?></option>
					</select>
				</p>
				<p>
					<label for="pnk-search"><?php esc_html_e( 'Hledat', 'pneukarnik-booking' ); ?></label><br>
					<input id="pnk-search" type="search" name="search" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="<?php esc_attr_e( 'Jméno, SPZ, telefon, e‑mail', 'pneukarnik-booking' ); ?>">
				</p>
				<p><?php submit_button( __( 'Filtrovat', 'pneukarnik-booking' ), 'secondary', '', false ); ?></p>
			</form>

			<p>
				<?php
				/* translators: %d: počet Rezervací */
				echo esc_html( sprintf( _n( '%d rezervace', '%d rezervací', $result['total'], 'pneukarnik-booking' ), $result['total'] ) );
				?>
			</p>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Termín', 'pneukarnik-booking' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Služby', 'pneukarnik-booking' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Zákazník', 'pneukarnik-booking' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Telefon', 'pneukarnik-booking' ); ?></th>
						<th scope="col"><?php esc_html_e( 'E‑mail', 'pneukarnik-booking' ); ?></th>
						<th scope="col"><?php esc_html_e( 'SPZ', 'pneukarnik-booking' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Leasing / kola', 'pneukarnik-booking' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Zdroj', 'pneukarnik-booking' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Stav', 'pneukarnik-booking' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! $result['bookings'] ) : ?>
						<tr><td colspan="9"><?php esc_html_e( 'Žádné rezervace.', 'pneukarnik-booking' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $result['bookings'] as $b ) : ?>
						<tr>
							<td>
								<a href="<?php echo esc_url( Pneukarnik_Admin_Calendar::url( $b['booking_date'], $b['id'] ) ); ?>">
									<?php echo esc_html( pneukarnik_format_day( $b['booking_date'] ) ); ?><br>
									<?php echo esc_html( $b['time_start'] . '–' . $b['time_end'] ); ?>
								</a>
							</td>
							<td><?php echo esc_html( $b['service_name'] ); ?></td>
							<td>
								<?php echo esc_html( $b['customer_name'] ); ?>
								<?php if ( $b['customer_company'] ) : ?>
									<br><small><?php echo esc_html( $b['customer_company'] ); ?></small>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $b['customer_phone'] ); ?></td>
							<td><?php echo esc_html( $b['customer_email'] ); ?></td>
							<td><?php echo esc_html( $b['customer_plate'] ); ?></td>
							<td>
								<?php echo esc_html( $b['leasing'] ? sprintf( /* translators: %s: leasingová společnost */ __( 'leasing: %s', 'pneukarnik-booking' ), $b['leasing_company'] ) : '' ); ?>
								<?php if ( $b['stored_wheels'] ) : ?>
									<br><?php esc_html_e( 'kola uskladněná', 'pneukarnik-booking' ); ?>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $sources[ $b['source'] ] ?? $b['source'] ); ?></td>
							<td>
								<?php if ( Pneukarnik_Booking::STATUS_CONFIRMED === $b['status'] ) : ?>
									<?php esc_html_e( 'Potvrzená', 'pneukarnik-booking' ); ?>
								<?php else : ?>
									<?php esc_html_e( 'Zrušená', 'pneukarnik-booking' ); ?>
									<?php if ( $b['cancelled_at'] ) : ?>
										<br><small><?php echo esc_html( Pneukarnik_Clock::at( $b['cancelled_at'] )->format( 'j. n. Y G:i' ) ); ?></small>
									<?php endif; ?>
									<?php if ( $b['cancel_reason'] ) : ?>
										<br><small><?php echo esc_html( $b['cancel_reason'] ); ?></small>
									<?php endif; ?>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ( $pages > 1 ) : ?>
				<div class="tablenav bottom">
					<div class="tablenav-pages">
						<?php
						echo wp_kses_post(
							(string) paginate_links(
								[
									'base'    => add_query_arg( 'paged', '%#%' ),
									'format'  => '',
									'current' => $page,
									'total'   => $pages,
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

	/**
	 * Filtry z adresy. Bez odeslaného filtru: potvrzené od dneška.
	 *
	 * @return array{from:string,to:string,status:string,search:string}
	 */
	private static function filters(): array {
		$date      = static function ( string $key ): string {
			$value = isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : '';
			return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : '';
		};
		$submitted = isset( $_GET['filtr'] );
		$status    = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : Pneukarnik_Booking::STATUS_CONFIRMED;
		return [
			'from'   => $submitted ? $date( 'from' ) : Pneukarnik_Clock::today()->format( 'Y-m-d' ),
			'to'     => $date( 'to' ),
			'status' => in_array( $status, [ Pneukarnik_Booking::STATUS_CONFIRMED, Pneukarnik_Booking::STATUS_CANCELLED ], true ) ? $status : '',
			'search' => isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '',
		];
	}
}
