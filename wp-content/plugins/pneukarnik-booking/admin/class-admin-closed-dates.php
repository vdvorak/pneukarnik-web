<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Parametry v URL (filtry, stránkování, hlášky po přesměrování) jen řídí zobrazení, nic nemění.
// phpcs:disable WordPress.Security.NonceVerification.Recommended

/**
 * WP Admin page — správa uzavřených termínů.
 * Podporuje: celý den zavřeno, zkrácená pracovní doba, datum i rozsah dat.
 */
class Pneukarnik_Admin_Closed_Dates {

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Nemáte oprávnění.', 'pneukarnik-booking' ) );
		}

		if ( isset( $_POST['pneukarnik_closed_nonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce ověří handle_post().
			self::handle_post();
		}
		if ( isset( $_GET['delete_date'] ) && isset( $_GET['_wpnonce'] ) ) {
			self::handle_delete();
		}

		$upcoming = Pneukarnik_Closed_Dates::get_all_from( '-30 days' );
		$today    = Pneukarnik_Clock::today()->format( 'Y-m-d' );

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Uzavřené termíny', 'pneukarnik-booking' ); ?></h1>
			<p><?php esc_html_e( 'Nastavte dny, kdy servis nebude pracovat nebo bude mít zkrácenou dobu. Na zavřené dny nebude možné rezervovat, na zkrácené dny budou sloty jen ve vámi zadaném čase.', 'pneukarnik-booking' ); ?></p>

			<?php if ( isset( $_GET['error'] ) && $_GET['error'] === 'overlap' ) : ?>
				<div class="notice notice-error is-dismissible">
					<p><?php esc_html_e( 'Fáze pracovní doby se překrývají. Zkontrolujte zadané časy.', 'pneukarnik-booking' ); ?></p>
				</div>
			<?php endif; ?>
			<?php if ( isset( $_GET['saved'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p>
						<?php
						/* translators: %d: počet uložených dnů */
						printf( esc_html__( 'Uloženo %d termínů.', 'pneukarnik-booking' ), (int) $_GET['saved'] );
						?>
					</p>
				</div>
			<?php endif; ?>
			<?php if ( isset( $_GET['deleted'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Termín odstraněn.', 'pneukarnik-booking' ); ?></p>
				</div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Přidat termín', 'pneukarnik-booking' ); ?></h2>
			<form method="post">
				<?php wp_nonce_field( 'pneukarnik_add_closed', 'pneukarnik_closed_nonce' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="date_from"><?php esc_html_e( 'Datum od', 'pneukarnik-booking' ); ?></label></th>
						<td>
							<input type="date" id="date_from" name="date_from" required class="regular-text" />
							<p class="description" style="color:#d63638"><?php esc_html_e( 'Minulá data jsou povolena (pro doplnění záznamu), ale sloty jsou již uzavřeny automaticky.', 'pneukarnik-booking' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="date_to"><?php esc_html_e( 'Datum do', 'pneukarnik-booking' ); ?></label></th>
						<td>
							<input type="date" id="date_to" name="date_to" class="regular-text" />
							<p class="description"><?php esc_html_e( 'Nechte prázdné pro jeden den. Vyplňte pro rozsah — každý den v rozsahu dostane stejné nastavení.', 'pneukarnik-booking' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Typ uzavření', 'pneukarnik-booking' ); ?></th>
						<td>
							<label style="display:block;margin-bottom:8px">
								<input type="radio" name="closure_type" value="fully_closed" checked
									onchange="document.getElementById('custom-hours-row').style.display='none'" />
								<?php esc_html_e( 'Celý den zavřeno', 'pneukarnik-booking' ); ?>
							</label>
							<label style="display:block">
								<input type="radio" name="closure_type" value="custom_hours"
									onchange="document.getElementById('custom-hours-row').style.display='table-row'" />
								<?php esc_html_e( 'Zkrácená pracovní doba (vlastní hodiny)', 'pneukarnik-booking' ); ?>
							</label>
						</td>
					</tr>
					<tr id="custom-hours-row" style="display:none">
						<th scope="row"><?php esc_html_e( 'Vlastní hodiny', 'pneukarnik-booking' ); ?></th>
						<td>
							<div id="hours-phases">
								<div class="hours-phase" style="margin-bottom:8px">
									<label><?php esc_html_e( 'Od', 'pneukarnik-booking' ); ?></label>
									<input type="time" name="hours_from[]" value="08:00" step="900" style="margin:0 8px" />
									<label><?php esc_html_e( 'Do', 'pneukarnik-booking' ); ?></label>
									<input type="time" name="hours_to[]" value="12:00" step="900" style="margin:0 8px" />
								</div>
							</div>
							<button type="button" class="button button-small" onclick="addPhase()">
								+ <?php esc_html_e( 'Přidat další fázi', 'pneukarnik-booking' ); ?>
							</button>
							<p class="description"><?php esc_html_e( 'Např. 08:00–12:00 pro dopolední provoz. Lze přidat více fází (dopoledne + odpoledne).', 'pneukarnik-booking' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="note"><?php esc_html_e( 'Poznámka', 'pneukarnik-booking' ); ?></label></th>
						<td>
							<input type="text" id="note" name="note" class="regular-text"
								placeholder="<?php esc_attr_e( 'např. Státní svátek, dovolená, školení…', 'pneukarnik-booking' ); ?>" />
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Uložit', 'pneukarnik-booking' ) ); ?>
			</form>

			<script>
			function addPhase() {
				var container = document.getElementById('hours-phases');
				var div = document.createElement('div');
				div.className = 'hours-phase';
				div.style.marginBottom = '8px';
				div.innerHTML = '<label><?php echo esc_js( __( 'Od', 'pneukarnik-booking' ) ); ?></label><input type="time" name="hours_from[]" value="13:00" step="900" style="margin:0 8px" /><label><?php echo esc_js( __( 'Do', 'pneukarnik-booking' ) ); ?></label><input type="time" name="hours_to[]" value="17:00" step="900" style="margin:0 8px" /><button type="button" onclick="this.parentNode.remove()" style="color:red;background:none;border:none;cursor:pointer">✕</button>';
				container.appendChild(div);
			}
			</script>

			<!-- Tabulka termínů -->
			<h2><?php esc_html_e( 'Naplánované termíny', 'pneukarnik-booking' ); ?></h2>
			<?php if ( empty( $upcoming ) ) : ?>
				<p><?php esc_html_e( 'Žádné termíny.', 'pneukarnik-booking' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th style="width:110px"><?php esc_html_e( 'Datum', 'pneukarnik-booking' ); ?></th>
							<th style="width:80px"><?php esc_html_e( 'Den', 'pneukarnik-booking' ); ?></th>
							<th><?php esc_html_e( 'Typ', 'pneukarnik-booking' ); ?></th>
							<th><?php esc_html_e( 'Hodiny', 'pneukarnik-booking' ); ?></th>
							<th><?php esc_html_e( 'Poznámka', 'pneukarnik-booking' ); ?></th>
							<th style="width:80px"><?php esc_html_e( 'Akce', 'pneukarnik-booking' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( $upcoming as $entry ) :
							$dt      = Pneukarnik_Clock::at( $entry['date'] );
							$is_past = $entry['date'] < $today;
							$day_cz  = self::day_name_cz( (int) $dt->format( 'N' ) );

							if ( $entry['is_fully_closed'] ) {
								$type_label  = __( 'Zavřeno', 'pneukarnik-booking' );
								$hours_label = '—';
							} else {
								$type_label  = __( 'Zkrácená doba', 'pneukarnik-booking' );
								$phases      = $entry['custom_hours'] ?? [];
								$hours_label = implode(
									', ',
									array_map(
										fn( $p ) => $p['from'] . '–' . $p['to'],
										$phases
									)
								);
							}
							?>
							<tr<?php echo $is_past ? ' style="opacity:.5"' : ''; ?>>
								<td><strong><?php echo esc_html( $dt->format( 'd.m.Y' ) ); ?></strong></td>
								<td><?php echo esc_html( $day_cz ); ?></td>
								<td><?php echo esc_html( $type_label ); ?></td>
								<td><?php echo esc_html( $hours_label ); ?></td>
								<td><?php echo esc_html( $entry['note'] ?? '—' ); ?></td>
								<td>
									<a href="
									<?php
									echo esc_url(
										wp_nonce_url(
											add_query_arg(
												[
													'page' => 'pneukarnik-closed-dates',
													'delete_date' => $entry['date'],
												],
												admin_url( 'admin.php' )
											),
											'pneukarnik_delete_closed_' . $entry['date']
										)
									);
									?>
									"
									onclick="return confirm('<?php esc_attr_e( 'Opravdu smazat?', 'pneukarnik-booking' ); ?>')"
									class="button button-small button-link-delete">
										<?php esc_html_e( 'Smazat', 'pneukarnik-booking' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function handle_post(): void {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pneukarnik_closed_nonce'] ) ), 'pneukarnik_add_closed' ) ) {
			wp_die( 'Neplatný bezpečnostní token.' );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Nedostatečná oprávnění.' );
		}

		$date_from    = sanitize_text_field( $_POST['date_from'] ?? '' );
		$date_to      = sanitize_text_field( $_POST['date_to'] ?? '' );
		$note         = sanitize_text_field( $_POST['note'] ?? '' ) ?: null;
		$closure_type = sanitize_text_field( $_POST['closure_type'] ?? 'fully_closed' );

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_from ) ) {
			wp_safe_redirect( add_query_arg( 'error', '1', wp_get_referer() ) );
			exit;
		}

		$is_fully_closed = ( $closure_type !== 'custom_hours' );
		$custom_hours    = null;

		if ( ! $is_fully_closed ) {
			$hours_from = array_map( 'sanitize_text_field', (array) ( $_POST['hours_from'] ?? [] ) );
			$hours_to   = array_map( 'sanitize_text_field', (array) ( $_POST['hours_to'] ?? [] ) );
			$phases     = [];
			foreach ( $hours_from as $i => $from ) {
				$to = $hours_to[ $i ] ?? '';
				if ( preg_match( '/^\d{2}:\d{2}$/', $from ) && preg_match( '/^\d{2}:\d{2}$/', $to ) && $to > $from ) {
					$phases[] = [
						'from' => $from,
						'to'   => $to,
					];
				}
			}
			// Sort by start time and check for overlaps
			usort( $phases, fn( $a, $b ) => strcmp( $a['from'], $b['from'] ) );
			$phase_count = count( $phases );
			for ( $i = 1; $i < $phase_count; $i++ ) {
				if ( $phases[ $i ]['from'] < $phases[ $i - 1 ]['to'] ) {
					wp_safe_redirect(
						add_query_arg(
							[
								'page'  => 'pneukarnik-closed-dates',
								'error' => 'overlap',
							],
							admin_url( 'admin.php' )
						)
					);
					exit;
				}
			}
			if ( empty( $phases ) ) {
				$is_fully_closed = true;
			} else {
				$custom_hours = $phases;
			}
		}

		// Build list of dates
		$dates = [ $date_from ];
		if ( $date_to && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_to ) && $date_to > $date_from ) {
			$cursor = Pneukarnik_Clock::at( $date_from );
			$end    = Pneukarnik_Clock::at( $date_to );
			$dates  = [];
			while ( $cursor <= $end ) {
				$dates[] = $cursor->format( 'Y-m-d' );
				$cursor  = $cursor->modify( '+1 day' );
				if ( count( $dates ) > 366 ) {
					break;
				}
			}
		}

		$saved = 0;
		foreach ( $dates as $date ) {
			if ( Pneukarnik_Closed_Dates::upsert( $date, $is_fully_closed, $custom_hours, $note ) ) {
				++$saved;
			}
		}

		wp_safe_redirect(
			add_query_arg(
				[
					'page'  => 'pneukarnik-closed-dates',
					'saved' => $saved,
				],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	private static function handle_delete(): void {
		$date = sanitize_text_field( $_GET['delete_date'] );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			wp_die( 'Neplatné datum.' );
		}
		if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'pneukarnik_delete_closed_' . $date ) ) {
			wp_die( 'Neplatný bezpečnostní token.' );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Nedostatečná oprávnění.' );
		}

		Pneukarnik_Closed_Dates::delete( $date );

		wp_safe_redirect(
			add_query_arg(
				[
					'page'    => 'pneukarnik-closed-dates',
					'deleted' => '1',
				],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	private static function day_name_cz( int $iso_day ): string {
		$names = [
			1 => 'Pondělí',
			'Úterý',
			'Středa',
			'Čtvrtek',
			'Pátek',
			'Sobota',
			'Neděle',
		];
		return $names[ $iso_day ] ?? '';
	}
}
