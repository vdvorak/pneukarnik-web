<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Parametry v URL (hlášky po přesměrování) jen řídí zobrazení, nic nemění.
// phpcs:disable WordPress.Security.NonceVerification.Recommended

/**
 * Stránka administrace E‑maily Zákazníkům: všechno, co Zákazníkům chodí mimo potvrzení a Zrušení
 * Rezervace. Kolika Zákazníkům může který druh Nabídek a připomínek dnes přijít, Připomínka přezutí
 * (kolik dní před Sezónou, úvod, náhled, zkušební odeslání). Oprávnění stejné jako Nastavení.
 */
class Pneukarnik_Admin_Customer_Emails {

	public const PAGE = 'pneukarnik-customer-emails';

	public static function url(): string {
		return admin_url( 'admin.php?page=' . self::PAGE );
	}

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
		check_admin_referer( 'pneukarnik_customer_emails' );

		update_option( Pneukarnik_Reminder::OPTION_DAYS, max( 0, min( 90, (int) ( $_POST['reminder_days'] ?? Pneukarnik_Reminder::days_before() ) ) ) );
		if ( isset( $_POST['reminder_intro'] ) ) {
			update_option( Pneukarnik_Reminder::OPTION_INTRO, sanitize_textarea_field( wp_unslash( $_POST['reminder_intro'] ) ) );
		}

		$result = [ 'saved' => '1' ];
		if ( ! empty( $_POST['reminder_test'] ) ) {
			$result['reminder_test'] = null !== Pneukarnik_Reminder::send_test() ? 'sent' : 'failed';
		}
		wp_safe_redirect( add_query_arg( $result, self::url() ) );
		exit;
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Nemáte oprávnění.', 'pneukarnik-booking' ) );
		}
		$audience           = Pneukarnik_Subscriptions::audience();
		$reminder           = Pneukarnik_Reminder::preview();
		$provozovatel_email = Pneukarnik_Contact::email();
		$kinds              = [
			Pneukarnik_Subscriptions::REMINDER   => [ __( 'Připomínka přezutí', 'pneukarnik-booking' ), '' ],
			Pneukarnik_Subscriptions::PROMOTIONS => [ __( 'Akce (Rozesílky)', 'pneukarnik-booking' ), __( 'Rozesílky zatím poslat nejde. Počítají se i souhlasy „informace o slevách“ ze starého webu.', 'pneukarnik-booking' ) ],
			Pneukarnik_Subscriptions::REVIEW     => [ __( 'Žádost o hodnocení', 'pneukarnik-booking' ), __( 'Zatím se neposílá.', 'pneukarnik-booking' ) ],
		];
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'E‑maily Zákazníkům', 'pneukarnik-booking' ); ?></h1>
			<p><?php esc_html_e( 'Všechno, co Zákazníkům chodí mimo potvrzení a Zrušení Rezervace. Texty potvrzení a podpis jsou v Nastavení.', 'pneukarnik-booking' ); ?></p>

			<?php if ( isset( $_GET['reminder_test'] ) ) : ?>
				<?php if ( 'sent' === $_GET['reminder_test'] ) : ?>
					<div class="notice notice-success"><p><?php echo esc_html( sprintf( /* translators: %s: e‑mail Provozovatele */ __( 'Uloženo a zkušební Připomínka odeslaná na %s.', 'pneukarnik-booking' ), $provozovatel_email ) ); ?></p></div>
				<?php else : ?>
					<div class="notice notice-error"><p><?php esc_html_e( 'Uloženo, ale zkušební Připomínku se nepodařilo odeslat. Zkontrolujte kontaktní e‑mail v Nastavení a nastavení odesílání pošty.', 'pneukarnik-booking' ); ?></p></div>
				<?php endif; ?>
			<?php elseif ( isset( $_GET['saved'] ) ) : ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Uloženo.', 'pneukarnik-booking' ); ?></p></div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Nabídky a připomínky', 'pneukarnik-booking' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Chodí Zákazníkům, kteří u vás už byli a při online rezervaci je neodmítli, nebo k nim dali souhlas. Každý si v e‑mailu může nastavit, co dostávat chce.', 'pneukarnik-booking' ); ?></p>
			<table class="widefat striped" style="max-width:48rem">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Druh', 'pneukarnik-booking' ); ?></th>
						<th><?php esc_html_e( 'Kolika Zákazníkům dnes může přijít', 'pneukarnik-booking' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $kinds as $kind => [ $label, $note ] ) : ?>
						<tr>
							<th scope="row"><?php echo esc_html( $label ); ?></th>
							<td>
								<?php echo esc_html( (string) $audience[ $kind ] ); ?>
								<?php if ( '' !== $note ) : ?>
									<p class="description"><?php echo esc_html( $note ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<form method="post">
				<?php wp_nonce_field( 'pneukarnik_customer_emails' ); ?>

				<h2><?php esc_html_e( 'Připomínka přezutí', 'pneukarnik-booking' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><label for="pnk-reminder-days"><?php esc_html_e( 'Kolik dní před Sezónou', 'pneukarnik-booking' ); ?></label></th>
						<td>
							<input id="pnk-reminder-days" type="number" name="reminder_days" value="<?php echo esc_attr( (string) $reminder['days_before'] ); ?>" min="0" max="90" step="1" class="small-text">
							<p class="description"><?php esc_html_e( 'Připomínka odchází Zákazníkům, kterým smí chodit, nejvýš jednou za Sezónu. 0 = neposílat.', 'pneukarnik-booking' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="pnk-reminder-intro"><?php esc_html_e( 'Úvod e‑mailu', 'pneukarnik-booking' ); ?></label></th>
						<td><textarea id="pnk-reminder-intro" name="reminder_intro" rows="3" class="large-text"><?php echo esc_textarea( Pneukarnik_Reminder::intro() ); ?></textarea></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Náhled', 'pneukarnik-booking' ); ?></th>
						<td>
							<?php if ( null === $reminder['season'] ) : ?>
								<p><?php esc_html_e( 'Není nastavená žádná Sezóna, Připomínky se neposílají.', 'pneukarnik-booking' ); ?></p>
							<?php elseif ( ! $reminder['enabled'] ) : ?>
								<p><?php esc_html_e( 'Připomínky jsou vypnuté.', 'pneukarnik-booking' ); ?></p>
							<?php else : ?>
								<?php $sending = Pneukarnik_Clock::today()->format( 'Y-m-d' ) >= $reminder['send_from']; ?>
								<p>
									<?php
									echo esc_html(
										sprintf(
											/* translators: 1: název Sezóny, 2: začátek Sezóny, 3: od kdy se posílá */
											$sending ? __( '%1$s Sezóna začíná %2$s, Připomínky se posílají od %3$s.', 'pneukarnik-booking' ) : __( '%1$s Sezóna začíná %2$s, Připomínky se začnou posílat %3$s.', 'pneukarnik-booking' ),
											Pneukarnik_Season::names()[ $reminder['season'] ],
											Pneukarnik_Clock::at( (string) $reminder['season_from'] )->format( 'j. n. Y' ),
											Pneukarnik_Clock::at( (string) $reminder['send_from'] )->format( 'j. n. Y' )
										)
									);
									?>
								</p>
								<p>
									<strong>
										<?php
										/* translators: %d: počet Zákazníků */
										echo esc_html( sprintf( $sending ? __( 'Zbývá odeslat: %d', 'pneukarnik-booking' ) : __( 'Počet příjemců: %d', 'pneukarnik-booking' ), $reminder['recipients'] ) );
										?>
									</strong>
								</p>
							<?php endif; ?>
							<?php if ( '' !== $provozovatel_email ) : ?>
								<label>
									<input type="checkbox" name="reminder_test" value="1">
									<?php
									/* translators: %s: e‑mail Provozovatele */
									echo esc_html( sprintf( __( 'Po uložení poslat zkušební Připomínku na %s', 'pneukarnik-booking' ), $provozovatel_email ) );
									?>
								</label>
							<?php else : ?>
								<p class="description"><?php esc_html_e( 'Zkušební Připomínka jde poslat jen na kontaktní e‑mail, vyplňte ho v Nastavení.', 'pneukarnik-booking' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Uložit', 'pneukarnik-booking' ) ); ?>
			</form>
		</div>
		<?php
	}
}
