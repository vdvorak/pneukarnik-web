<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Parametry v URL (hlášky po přesměrování) jen řídí zobrazení, nic nemění.
// phpcs:disable WordPress.Security.NonceVerification.Recommended

/**
 * Administrace Výjimek: den nebo rozsah dní zavřeno / jiná Pracovní doba, „opakovat každý rok“,
 * a státní svátky ČR, které jde jednotlivě vypnout.
 */
class Pneukarnik_Admin_Day_Exceptions {

	public const PAGE = 'pneukarnik-day-exceptions';

	/**
	 * Zpracování formulářů před výstupem administrace (háček load-{stránka}), aby šlo přesměrovat.
	 */
	public static function handle_post(): void {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Nemáte oprávnění.', 'pneukarnik-booking' ) );
		}
		$action = sanitize_key( wp_unslash( $_POST['pnk_action'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce ověří check_admin_referer níže.
		check_admin_referer( 'pneukarnik_day_exceptions_' . $action );

		$result = match ( $action ) {
			'add'      => self::add(),
			'delete'   => Pneukarnik_Day_Exceptions::delete( absint( $_POST['id'] ?? 0 ) ) ? [ 'deleted' => '1' ] : [],
			'holidays' => self::save_holidays(),
			default    => [],
		};
		wp_safe_redirect( add_query_arg( $result, self::url() ) );
		exit;
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Nemáte oprávnění.', 'pneukarnik-booking' ) );
		}

		$today      = Pneukarnik_Clock::today();
		$exceptions = Pneukarnik_Day_Exceptions::listed( $today->format( 'Y-m-d' ) );
		$year       = (int) $today->format( 'Y' );
		$disabled   = Pneukarnik_Holidays::disabled();
		$errors     = [
			'invalid_date'  => __( 'Zadejte platné datum.', 'pneukarnik-booking' ),
			'invalid_range' => __( 'Konec musí být stejný nebo pozdější než začátek. Opakovaná Výjimka může trvat nejvýš rok.', 'pneukarnik-booking' ),
			'invalid_hours' => __( 'Pracovní doba musí mít 1–2 bloky „od–do“, které se nepřekrývají.', 'pneukarnik-booking' ),
		];
		$error      = sanitize_key( wp_unslash( $_GET['error'] ?? '' ) );

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Výjimky', 'pneukarnik-booking' ); ?></h1>
			<p><?php esc_html_e( 'Dny, kdy je zavřeno nebo platí jiná Pracovní doba než obvykle (dovolená, zkrácený den). Výjimka má přednost před Pracovní dobou i před státním svátkem.', 'pneukarnik-booking' ); ?></p>

			<?php if ( isset( $errors[ $error ] ) ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $errors[ $error ] ); ?></p></div>
			<?php elseif ( isset( $_GET['added'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Výjimka uložena.', 'pneukarnik-booking' ); ?></p></div>
			<?php elseif ( isset( $_GET['deleted'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Výjimka smazána.', 'pneukarnik-booking' ); ?></p></div>
			<?php elseif ( isset( $_GET['holidays'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Svátky uloženy.', 'pneukarnik-booking' ); ?></p></div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Přidat Výjimku', 'pneukarnik-booking' ); ?></h2>
			<form method="post">
				<?php wp_nonce_field( 'pneukarnik_day_exceptions_add' ); ?>
				<input type="hidden" name="pnk_action" value="add">
				<table class="form-table">
					<tr>
						<th scope="row"><label for="pnk-date-from"><?php esc_html_e( 'Od', 'pneukarnik-booking' ); ?></label></th>
						<td><input type="date" id="pnk-date-from" name="date_from" required></td>
					</tr>
					<tr>
						<th scope="row"><label for="pnk-date-to"><?php esc_html_e( 'Do (včetně)', 'pneukarnik-booking' ); ?></label></th>
						<td>
							<input type="date" id="pnk-date-to" name="date_to">
							<p class="description"><?php esc_html_e( 'Prázdné = jen jeden den.', 'pneukarnik-booking' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Pracovní doba', 'pneukarnik-booking' ); ?></th>
						<td>
							<fieldset>
								<label><input type="radio" name="kind" value="closed" checked> <?php esc_html_e( 'Zavřeno', 'pneukarnik-booking' ); ?></label><br>
								<label><input type="radio" name="kind" value="hours"> <?php esc_html_e( 'Jiná Pracovní doba', 'pneukarnik-booking' ); ?></label>
							</fieldset>
							<p>
								<?php for ( $i = 1; $i <= 2; $i++ ) : ?>
									<label>
										<?php
										/* translators: %d: číslo bloku */
										echo esc_html( sprintf( __( 'Blok %d od', 'pneukarnik-booking' ), $i ) );
										?>
										<input type="time" name="<?php echo esc_attr( "from{$i}" ); ?>">
									</label>
									<label>
										<?php esc_html_e( 'do', 'pneukarnik-booking' ); ?>
										<input type="time" name="<?php echo esc_attr( "to{$i}" ); ?>">
									</label>
									<br>
								<?php endfor; ?>
							</p>
							<p class="description"><?php esc_html_e( 'Vyplňte jen u „Jiná Pracovní doba“. Druhý blok je nepovinný (např. odpoledne po polední pauze).', 'pneukarnik-booking' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Opakování', 'pneukarnik-booking' ); ?></th>
						<td><label><input type="checkbox" name="yearly" value="1"> <?php esc_html_e( 'Opakovat každý rok', 'pneukarnik-booking' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><label for="pnk-note"><?php esc_html_e( 'Poznámka', 'pneukarnik-booking' ); ?></label></th>
						<td><input type="text" id="pnk-note" name="note" class="regular-text" maxlength="255" placeholder="<?php esc_attr_e( 'např. Dovolená', 'pneukarnik-booking' ); ?>"></td>
					</tr>
				</table>
				<?php submit_button( __( 'Přidat Výjimku', 'pneukarnik-booking' ) ); ?>
			</form>

			<h2><?php esc_html_e( 'Platné Výjimky', 'pneukarnik-booking' ); ?></h2>
			<?php if ( ! $exceptions ) : ?>
				<p><?php esc_html_e( 'Žádné Výjimky.', 'pneukarnik-booking' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Kdy', 'pneukarnik-booking' ); ?></th>
							<th><?php esc_html_e( 'Pracovní doba', 'pneukarnik-booking' ); ?></th>
							<th><?php esc_html_e( 'Poznámka', 'pneukarnik-booking' ); ?></th>
							<th style="width:90px"></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $exceptions as $exception ) : ?>
							<tr>
								<td><?php echo esc_html( self::period_label( $exception ) ); ?></td>
								<td><?php echo esc_html( self::hours_label( $exception['hours'] ) ); ?></td>
								<td><?php echo esc_html( '' !== $exception['note'] ? $exception['note'] : '—' ); ?></td>
								<td>
									<form method="post">
										<?php wp_nonce_field( 'pneukarnik_day_exceptions_delete' ); ?>
										<input type="hidden" name="pnk_action" value="delete">
										<input type="hidden" name="id" value="<?php echo (int) $exception['id']; ?>">
										<button type="submit" class="button button-small button-link-delete"><?php esc_html_e( 'Smazat', 'pneukarnik-booking' ); ?></button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Státní svátky', 'pneukarnik-booking' ); ?></h2>
			<p><?php esc_html_e( 'Zaškrtnuté svátky jsou každý rok zavřeno. Jiná Pracovní doba ve svátek se zadá Výjimkou.', 'pneukarnik-booking' ); ?></p>
			<form method="post">
				<?php wp_nonce_field( 'pneukarnik_day_exceptions_holidays' ); ?>
				<input type="hidden" name="pnk_action" value="holidays">
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th style="width:60px"><?php esc_html_e( 'Zavřeno', 'pneukarnik-booking' ); ?></th>
							<th><?php esc_html_e( 'Svátek', 'pneukarnik-booking' ); ?></th>
							<th><?php echo esc_html( (string) $year ); ?></th>
							<th><?php echo esc_html( (string) ( $year + 1 ) ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						$this_year = Pneukarnik_Holidays::dates_in_year( $year );
						$next_year = Pneukarnik_Holidays::dates_in_year( $year + 1 );
						foreach ( Pneukarnik_Holidays::names() as $key => $name ) :
							?>
							<tr>
								<td><input type="checkbox" id="pnk-holiday-<?php echo esc_attr( $key ); ?>" name="holidays[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( ! in_array( $key, $disabled, true ) ); ?>></td>
								<td><label for="pnk-holiday-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $name ); ?></label></td>
								<td><?php echo esc_html( self::short_date( $this_year[ $key ] ) ); ?></td>
								<td><?php echo esc_html( self::short_date( $next_year[ $key ] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php submit_button( __( 'Uložit svátky', 'pneukarnik-booking' ) ); ?>
			</form>
		</div>
		<?php
	}

	public static function url(): string {
		return admin_url( 'admin.php?page=' . self::PAGE );
	}

	/**
	 * @return array<string,string> Parametry hlášky po přesměrování.
	 */
	private static function add(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce ověřil handle_post().
		$from  = sanitize_text_field( wp_unslash( $_POST['date_from'] ?? '' ) );
		$to    = sanitize_text_field( wp_unslash( $_POST['date_to'] ?? '' ) );
		$hours = null;
		if ( 'hours' === ( $_POST['kind'] ?? '' ) ) {
			$hours = [];
			for ( $i = 1; $i <= 2; $i++ ) {
				$block_from = sanitize_text_field( wp_unslash( $_POST[ "from{$i}" ] ?? '' ) );
				$block_to   = sanitize_text_field( wp_unslash( $_POST[ "to{$i}" ] ?? '' ) );
				if ( '' !== $block_from || '' !== $block_to ) {
					$hours[] = [
						'from' => $block_from,
						'to'   => $block_to,
					];
				}
			}
		}
		$error = Pneukarnik_Day_Exceptions::add(
			$from,
			'' !== $to ? $to : $from,
			! empty( $_POST['yearly'] ),
			$hours,
			sanitize_text_field( wp_unslash( $_POST['note'] ?? '' ) )
		);
		// phpcs:enable
		return null === $error ? [ 'added' => '1' ] : [ 'error' => $error ];
	}

	/**
	 * @return array<string,string>
	 */
	private static function save_holidays(): array {
		$enabled = array_map( 'sanitize_key', (array) wp_unslash( $_POST['holidays'] ?? [] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce ověřil handle_post().
		Pneukarnik_Holidays::save_disabled( array_values( array_diff( array_keys( Pneukarnik_Holidays::names() ), $enabled ) ) );
		return [ 'holidays' => '1' ];
	}

	/**
	 * @param array{date_from:string,date_to:string,yearly:bool} $exception
	 */
	private static function period_label( array $exception ): string {
		if ( $exception['yearly'] ) {
			$from = self::short_date( $exception['date_from'] );
			$to   = self::short_date( $exception['date_to'] );
			/* translators: %s: den a měsíc nebo jejich rozsah */
			return sprintf( __( 'každý rok %s', 'pneukarnik-booking' ), $from === $to ? $from : $from . '–' . $to );
		}
		$from = pneukarnik_format_date( $exception['date_from'] );
		return $exception['date_from'] === $exception['date_to'] ? $from : $from . '–' . pneukarnik_format_date( $exception['date_to'] );
	}

	/**
	 * @param list<array{from:string,to:string}>|null $hours
	 */
	private static function hours_label( ?array $hours ): string {
		if ( null === $hours ) {
			return __( 'Zavřeno', 'pneukarnik-booking' );
		}
		return implode( ', ', array_map( static fn( array $block ): string => $block['from'] . '–' . $block['to'], $hours ) );
	}

	private static function short_date( string $ymd ): string {
		return Pneukarnik_Clock::at( $ymd )->format( 'j. n.' );
	}
}
