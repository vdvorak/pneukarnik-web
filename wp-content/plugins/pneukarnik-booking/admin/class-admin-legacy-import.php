<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Parametry v URL (hlášky po přesměrování) jen řídí zobrazení, nic nemění.
// phpcs:disable WordPress.Security.NonceVerification.Recommended

/**
 * Administrace převodu dat ze starého webu (Pneukarnik_Legacy_Import): spuštění a report.
 * Report posledního běhu si stránka pamatuje pro přihlášeného uživatele.
 */
class Pneukarnik_Admin_Legacy_Import {

	public const PAGE = 'pneukarnik-legacy-import';

	private const REPORT_TRANSIENT = 'pneukarnik_legacy_import_report_';

	/**
	 * Zpracování formuláře před výstupem administrace (háček load-{stránka}), aby šlo přesměrovat.
	 */
	public static function handle_post(): void {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Nemáte oprávnění.', 'pneukarnik-booking' ) );
		}
		check_admin_referer( 'pneukarnik_legacy_import' );

		$result = Pneukarnik_Legacy_Import::run( ! empty( $_POST['send_cancel_links'] ) );
		if ( $result['ok'] ) {
			set_transient( self::REPORT_TRANSIENT . get_current_user_id(), $result['report'], DAY_IN_SECONDS );
		}
		wp_safe_redirect( add_query_arg( $result['ok'] ? [ 'done' => '1' ] : [ 'error' => 'no_source' ], self::url() ) );
		exit;
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Nemáte oprávnění.', 'pneukarnik-booking' ) );
		}
		$report   = get_transient( self::REPORT_TRANSIENT . get_current_user_id() );
		$sections = [
			'services' => __( 'Služby', 'pneukarnik-booking' ),
			'bookings' => __( 'Rezervace', 'pneukarnik-booking' ),
			'consents' => __( 'Souhlasy „informace o slevách“', 'pneukarnik-booking' ),
		];
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Převod ze starého webu', 'pneukarnik-booking' ); ?></h1>
			<p><?php esc_html_e( 'Převede Služby ze starého webu jako koncepty, všechny budoucí Rezervace a minulé do 1 roku a souhlasy „informace o slevách“ (jen s původním účelem, tedy pro Akce, ne pro Připomínku přezutí). Zrušené Rezervace se nepřevádějí. Převod jde spustit opakovaně: co už převedené je, zůstane, jak je.', 'pneukarnik-booking' ); ?></p>

			<?php if ( isset( $_GET['error'] ) ) : ?>
				<div class="notice notice-error"><p><?php esc_html_e( 'Stará tabulka Rezervací v databázi není.', 'pneukarnik-booking' ); ?></p></div>
			<?php elseif ( isset( $_GET['done'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Převod doběhl.', 'pneukarnik-booking' ); ?></p></div>
			<?php endif; ?>

			<?php if ( ! Pneukarnik_Legacy_Import::available() ) : ?>
				<p>
					<?php
					/* translators: %s: název tabulky */
					echo esc_html( sprintf( __( 'Stará tabulka Rezervací %s v databázi není, není co převést.', 'pneukarnik-booking' ), Pneukarnik_Legacy_Import::old_table() ) );
					?>
				</p>
			<?php else : ?>
				<form method="post">
					<?php wp_nonce_field( 'pneukarnik_legacy_import' ); ?>
					<p>
						<label>
							<input type="checkbox" name="send_cancel_links" value="1">
							<?php esc_html_e( 'Poslat nově převedeným budoucím Rezervacím e‑mail s novým odkazem na Zrušení (starý klíč přestane platit)', 'pneukarnik-booking' ); ?>
						</label>
					</p>
					<?php submit_button( __( 'Spustit převod', 'pneukarnik-booking' ), 'primary', 'submit', false ); ?>
				</form>
			<?php endif; ?>

			<?php if ( is_array( $report ) ) : ?>
				<h2><?php esc_html_e( 'Poslední převod', 'pneukarnik-booking' ); ?></h2>
				<table class="widefat striped" style="max-width:48rem">
					<thead>
						<tr>
							<th></th>
							<th><?php esc_html_e( 'Převedeno', 'pneukarnik-booking' ); ?></th>
							<th><?php esc_html_e( 'Přeskočeno', 'pneukarnik-booking' ); ?></th>
							<th><?php esc_html_e( 'Chybné', 'pneukarnik-booking' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $sections as $key => $label ) : ?>
							<tr>
								<th scope="row"><?php echo esc_html( $label ); ?></th>
								<td><?php echo esc_html( (string) ( $report[ $key ]['imported'] ?? 0 ) ); ?></td>
								<td><?php echo esc_html( (string) ( $report[ $key ]['skipped'] ?? 0 ) ); ?></td>
								<td><?php echo esc_html( (string) ( $report[ $key ]['failed'] ?? 0 ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p>
					<?php
					/* translators: %d: počet e‑mailů */
					echo esc_html( sprintf( __( 'E‑mailů s novým odkazem na Zrušení: %d', 'pneukarnik-booking' ), (int) ( $report['bookings']['emailed'] ?? 0 ) ) );
					?>
				</p>
				<?php foreach ( $sections as $key => $label ) : ?>
					<?php if ( ! empty( $report[ $key ]['problems'] ) ) : ?>
						<h3><?php echo esc_html( $label ); ?></h3>
						<ul class="ul-disc">
							<?php foreach ( (array) $report[ $key ]['problems'] as $problem ) : ?>
								<li><?php echo esc_html( (string) $problem ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function url(): string {
		return admin_url( 'admin.php?page=' . self::PAGE );
	}
}
