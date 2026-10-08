<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Parametry v URL (hlášky po přesměrování) jen řídí zobrazení, nic nemění.
// phpcs:disable WordPress.Security.NonceVerification.Recommended

/**
 * Stránka administrace E‑maily Zákazníkům: všechno, co Zákazníkům chodí mimo potvrzení a Zrušení
 * Rezervace. Připomínka Termínu (zapnutí, hodina, zkušební odeslání), kolika Zákazníkům může který
 * druh Nabídek a připomínek dnes přijít, Připomínka přezutí (kolik dní před Sezónou, úvod, náhled,
 * zkušební odeslání), Žádost o hodnocení (zapnutí, úvod, upozornění na chybějící Place ID, zkušební
 * odeslání) a Rozesílky (seznam, složení, zkušební e‑mail a odeslání na mailing=new|{id}).
 * Oprávnění stejné jako Nastavení.
 */
class Pneukarnik_Admin_Customer_Emails {

	public const PAGE = 'pneukarnik-customer-emails';

	public static function url(): string {
		return admin_url( 'admin.php?page=' . self::PAGE );
	}

	/**
	 * Stránka Rozesílky, „new“ = nová.
	 */
	public static function mailing_url( int|string $id ): string {
		return add_query_arg( 'mailing', $id, self::url() );
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
		if ( isset( $_GET['mailing'] ) ) {
			self::handle_mailing_post();
		}
		check_admin_referer( 'pneukarnik_customer_emails' );

		Pneukarnik_Termin_Reminder::save( ! empty( $_POST['termin_reminder_enabled'] ), (int) ( $_POST['termin_reminder_hour'] ?? Pneukarnik_Termin_Reminder::hour() ) );
		update_option( Pneukarnik_Reminder::OPTION_DAYS, max( 0, min( 90, (int) ( $_POST['reminder_days'] ?? Pneukarnik_Reminder::days_before() ) ) ) );
		if ( isset( $_POST['reminder_intro'] ) ) {
			update_option( Pneukarnik_Reminder::OPTION_INTRO, sanitize_textarea_field( wp_unslash( $_POST['reminder_intro'] ) ) );
		}
		Pneukarnik_Review_Request::save(
			! empty( $_POST['review_request_enabled'] ),
			isset( $_POST['review_request_intro'] ) ? (string) wp_unslash( $_POST['review_request_intro'] ) : Pneukarnik_Review_Request::intro() // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitizuje save().
		);

		$result = [ 'saved' => '1' ];
		if ( ! empty( $_POST['termin_reminder_test'] ) ) {
			$result['termin_reminder_test'] = null !== Pneukarnik_Termin_Reminder::send_test() ? 'sent' : 'failed';
		}
		if ( ! empty( $_POST['reminder_test'] ) ) {
			$result['reminder_test'] = null !== Pneukarnik_Reminder::send_test() ? 'sent' : 'failed';
		}
		if ( ! empty( $_POST['review_request_test'] ) ) {
			$result['review_request_test'] = null !== Pneukarnik_Review_Request::send_test() ? 'sent' : 'failed';
		}
		wp_safe_redirect( add_query_arg( $result, self::url() ) );
		exit;
	}

	/**
	 * Formulář Rozesílky: uložit, uložit a poslat zkušební e‑mail, odeslat hned, nebo smazat rozepsanou.
	 */
	private static function handle_mailing_post(): void {
		check_admin_referer( 'pneukarnik_mailing' );
		$param  = sanitize_key( wp_unslash( $_GET['mailing'] ?? '' ) );
		$id     = 'new' === $param ? null : absint( $param );
		$action = sanitize_key( wp_unslash( $_POST['mailing_action'] ?? 'save' ) );

		if ( 'delete' === $action ) {
			Pneukarnik_Mailing::delete( (int) $id );
			wp_safe_redirect( add_query_arg( 'mailing_deleted', '1', self::url() ) );
			exit;
		}

		$saved = Pneukarnik_Mailing::save(
			$id,
			isset( $_POST['intro'] ) ? (string) wp_unslash( $_POST['intro'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitizuje save().
			array_map( 'intval', (array) wp_unslash( $_POST['promotions'] ?? [] ) )
		);
		if ( is_string( $saved ) ) {
			wp_safe_redirect( add_query_arg( 'error', $saved, self::mailing_url( $id ?? 'new' ) ) );
			exit;
		}

		$result = [ 'saved' => '1' ];
		if ( 'test' === $action ) {
			$result['test'] = null !== Pneukarnik_Mailing::send_test( $saved ) ? 'sent' : 'failed';
		}
		if ( 'send' === $action ) {
			$sent = Pneukarnik_Mailing::send( $saved );
			if ( Pneukarnik_Mailing::DONE === $sent ) {
				wp_safe_redirect( add_query_arg( 'mailing_sent', '1', self::url() ) );
				exit;
			}
			$result = [ 'error' => $sent ];
		}
		wp_safe_redirect( add_query_arg( $result, self::mailing_url( $saved ) ) );
		exit;
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Nemáte oprávnění.', 'pneukarnik-booking' ) );
		}
		if ( isset( $_GET['mailing'] ) ) {
			self::render_mailing( sanitize_key( wp_unslash( $_GET['mailing'] ) ) );
			return;
		}
		$audience           = Pneukarnik_Subscriptions::audience();
		$reminder           = Pneukarnik_Reminder::preview();
		$provozovatel_email = Pneukarnik_Contact::email();
		$kinds              = [
			Pneukarnik_Subscriptions::REMINDER   => [ __( 'Připomínka přezutí', 'pneukarnik-booking' ), '' ],
			Pneukarnik_Subscriptions::PROMOTIONS => [ __( 'Akce (Rozesílky)', 'pneukarnik-booking' ), __( 'Počítají se i souhlasy „informace o slevách“ ze starého webu.', 'pneukarnik-booking' ) ],
			Pneukarnik_Subscriptions::REVIEW     => [ __( 'Žádost o hodnocení', 'pneukarnik-booking' ), __( 'Jen těm, kterým ještě neodešla.', 'pneukarnik-booking' ) ],
		];
		// Zkušební odeslání: parametr výsledku => hláška o odeslání a o chybě.
		$tests = [
			'termin_reminder_test' => [
				/* translators: %s: e‑mail Provozovatele */
				__( 'Zkušební Připomínka Termínu odeslaná na %s.', 'pneukarnik-booking' ),
				__( 'Zkušební Připomínku Termínu se nepodařilo odeslat.', 'pneukarnik-booking' ),
			],
			'reminder_test'        => [
				/* translators: %s: e‑mail Provozovatele */
				__( 'Zkušební Připomínka přezutí odeslaná na %s.', 'pneukarnik-booking' ),
				__( 'Zkušební Připomínku přezutí se nepodařilo odeslat.', 'pneukarnik-booking' ),
			],
			'review_request_test'  => [
				/* translators: %s: e‑mail Provozovatele */
				__( 'Zkušební Žádost o hodnocení odeslaná na %s.', 'pneukarnik-booking' ),
				__( 'Zkušební Žádost o hodnocení se nepodařilo odeslat.', 'pneukarnik-booking' ),
			],
		];
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'E‑maily Zákazníkům', 'pneukarnik-booking' ); ?></h1>
			<p><?php esc_html_e( 'Všechno, co Zákazníkům chodí mimo potvrzení a Zrušení Rezervace. Texty potvrzení a podpis jsou v Nastavení.', 'pneukarnik-booking' ); ?></p>

			<?php if ( isset( $_GET['saved'] ) ) : ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Uloženo.', 'pneukarnik-booking' ); ?></p></div>
			<?php endif; ?>
			<?php foreach ( $tests as $param => [ $sent, $failed ] ) : ?>
				<?php if ( 'sent' === ( $_GET[ $param ] ?? '' ) ) : ?>
					<div class="notice notice-success"><p><?php echo esc_html( sprintf( $sent, $provozovatel_email ) ); ?></p></div>
				<?php elseif ( isset( $_GET[ $param ] ) ) : ?>
					<div class="notice notice-error"><p><?php echo esc_html( $failed . ' ' . __( 'Zkontrolujte kontaktní e‑mail v Nastavení a nastavení odesílání pošty.', 'pneukarnik-booking' ) ); ?></p></div>
				<?php endif; ?>
			<?php endforeach; ?>
			<?php if ( isset( $_GET['mailing_sent'] ) ) : ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Rozesílka se odesílá. Odchází po dávkách, u větších během několika hodin.', 'pneukarnik-booking' ); ?></p></div>
			<?php endif; ?>
			<?php if ( isset( $_GET['mailing_deleted'] ) ) : ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Rozepsaná Rozesílka je smazaná.', 'pneukarnik-booking' ); ?></p></div>
			<?php endif; ?>

			<?php self::render_mailings(); ?>

			<form method="post">
				<?php wp_nonce_field( 'pneukarnik_customer_emails' ); ?>

				<h2><?php esc_html_e( 'Připomínka Termínu', 'pneukarnik-booking' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Den před Termínem každé potvrzené Rezervaci s e‑mailem, i té, kterou jste zadali vy. Zákazník ji odmítnout nemůže. K Rezervaci vytvořené méně než 24 hodin předem se neposílá.', 'pneukarnik-booking' ); ?></p>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Posílat', 'pneukarnik-booking' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="termin_reminder_enabled" value="1" <?php checked( Pneukarnik_Termin_Reminder::enabled() ); ?>>
								<?php esc_html_e( 'Posílat Připomínku Termínu', 'pneukarnik-booking' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th><label for="pnk-termin-reminder-hour"><?php esc_html_e( 'Kdy den před Termínem', 'pneukarnik-booking' ); ?></label></th>
						<td>
							<select id="pnk-termin-reminder-hour" name="termin_reminder_hour">
								<?php for ( $hour = 0; $hour <= 23; $hour++ ) : ?>
									<option value="<?php echo esc_attr( (string) $hour ); ?>" <?php selected( Pneukarnik_Termin_Reminder::hour(), $hour ); ?>><?php echo esc_html( $hour . ':00' ); ?></option>
								<?php endfor; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Zkouška', 'pneukarnik-booking' ); ?></th>
						<td>
							<?php if ( '' !== $provozovatel_email ) : ?>
								<label>
									<input type="checkbox" name="termin_reminder_test" value="1">
									<?php
									/* translators: %s: e‑mail Provozovatele */
									echo esc_html( sprintf( __( 'Po uložení poslat zkušební Připomínku Termínu na %s', 'pneukarnik-booking' ), $provozovatel_email ) );
									?>
								</label>
							<?php else : ?>
								<p class="description"><?php esc_html_e( 'Zkušební Připomínka Termínu jde poslat jen na kontaktní e‑mail, vyplňte ho v Nastavení.', 'pneukarnik-booking' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
				</table>

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
									echo esc_html( sprintf( __( 'Po uložení poslat zkušební Připomínku přezutí na %s', 'pneukarnik-booking' ), $provozovatel_email ) );
									?>
								</label>
							<?php else : ?>
								<p class="description"><?php esc_html_e( 'Zkušební Připomínka přezutí jde poslat jen na kontaktní e‑mail, vyplňte ho v Nastavení.', 'pneukarnik-booking' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Žádost o hodnocení', 'pneukarnik-booking' ); ?></h2>
				<p class="description">
					<?php
					/* translators: %d: hodina */
					echo esc_html( sprintf( __( 'Den po Termínu nezrušené Rezervace od %d:00 s odkazem na napsání hodnocení na Googlu, Zákazníkům, kterým smějí chodit Nabídky a připomínky. Každému e‑mailu nejvýš jednou, po dalších návštěvách už ne.', 'pneukarnik-booking' ), Pneukarnik_Review_Request::HOUR ) );
					?>
				</p>
				<?php if ( '' === Pneukarnik_Review_Request::write_review_url() ) : ?>
					<div class="notice notice-warning inline">
						<p>
							<?php esc_html_e( 'Žádost o hodnocení se neposílá: chybí ID místa (Place ID) v Nastavení, části Google recenze.', 'pneukarnik-booking' ); ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=pneukarnik-settings' ) ); ?>"><?php esc_html_e( 'Otevřít Nastavení', 'pneukarnik-booking' ); ?></a>
						</p>
					</div>
				<?php endif; ?>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Posílat', 'pneukarnik-booking' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="review_request_enabled" value="1" <?php checked( Pneukarnik_Review_Request::enabled() ); ?>>
								<?php esc_html_e( 'Posílat Žádost o hodnocení', 'pneukarnik-booking' ); ?>
							</label>
							<p class="description">
								<?php
								/* translators: %d: počet Zákazníků */
								echo esc_html( sprintf( __( 'Už ji dostalo: %d', 'pneukarnik-booking' ), Pneukarnik_Review_Request::sent_count() ) );
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th><label for="pnk-review-request-intro"><?php esc_html_e( 'Úvod Žádosti o hodnocení', 'pneukarnik-booking' ); ?></label></th>
						<td><textarea id="pnk-review-request-intro" name="review_request_intro" rows="3" class="large-text"><?php echo esc_textarea( Pneukarnik_Review_Request::intro() ); ?></textarea></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Zkouška', 'pneukarnik-booking' ); ?></th>
						<td>
							<?php if ( '' === $provozovatel_email ) : ?>
								<p class="description"><?php esc_html_e( 'Zkušební Žádost o hodnocení jde poslat jen na kontaktní e‑mail, vyplňte ho v Nastavení.', 'pneukarnik-booking' ); ?></p>
							<?php elseif ( '' === Pneukarnik_Review_Request::write_review_url() ) : ?>
								<p class="description"><?php esc_html_e( 'Zkušební Žádost o hodnocení jde poslat až s vyplněným ID místa.', 'pneukarnik-booking' ); ?></p>
							<?php else : ?>
								<label>
									<input type="checkbox" name="review_request_test" value="1">
									<?php
									/* translators: %s: e‑mail Provozovatele */
									echo esc_html( sprintf( __( 'Po uložení poslat zkušební Žádost o hodnocení na %s', 'pneukarnik-booking' ), $provozovatel_email ) );
									?>
								</label>
							<?php endif; ?>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Uložit', 'pneukarnik-booking' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Seznam Rozesílek se stavem a odkaz na novou.
	 */
	private static function render_mailings(): void {
		$mailings = Pneukarnik_Mailing::all();
		?>
		<h2><?php esc_html_e( 'Rozesílky', 'pneukarnik-booking' ); ?></h2>
		<p class="description">
			<?php
			/* translators: %d: počet dní */
			echo esc_html( sprintf( __( 'E‑mail o Akcích, které platí nebo začnou do %d dní, všem Zákazníkům, kterým smějí chodit Akce.', 'pneukarnik-booking' ), Pneukarnik_Mailing::DAYS_AHEAD ) );
			?>
		</p>
		<p><a class="button" href="<?php echo esc_url( self::mailing_url( 'new' ) ); ?>"><?php esc_html_e( 'Nová Rozesílka', 'pneukarnik-booking' ); ?></a></p>
		<?php if ( $mailings ) : ?>
			<table class="widefat striped" style="max-width:48rem">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Založená', 'pneukarnik-booking' ); ?></th>
						<th><?php esc_html_e( 'Akce', 'pneukarnik-booking' ); ?></th>
						<th><?php esc_html_e( 'Stav', 'pneukarnik-booking' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $mailings as $mailing ) : ?>
						<tr>
							<td><a href="<?php echo esc_url( self::mailing_url( $mailing['id'] ) ); ?>"><?php echo esc_html( Pneukarnik_Clock::at( $mailing['created_at'] )->format( 'j. n. Y H:i' ) ); ?></a></td>
							<td><?php echo esc_html( implode( ', ', array_map( [ self::class, 'promotion_label' ], Pneukarnik_Mailing::mailing_promotions( $mailing ) ) ) ); ?></td>
							<td><?php echo esc_html( self::mailing_status( $mailing ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<?php
	}

	/**
	 * Rozesílka: rozepsaná jde upravit, otestovat a odeslat, odesílaná a odeslaná jen prohlédnout.
	 */
	private static function render_mailing( string $param ): void {
		$mailing = 'new' === $param ? null : Pneukarnik_Mailing::find( absint( $param ) );
		if ( 'new' !== $param && null === $mailing ) {
			wp_die( esc_html__( 'Rozesílka neexistuje.', 'pneukarnik-booking' ) );
		}
		$draft              = null === $mailing || Pneukarnik_Mailing::DRAFT === $mailing['status'];
		$provozovatel_email = Pneukarnik_Contact::email();
		$audience           = Pneukarnik_Mailing::audience();
		$errors             = [
			Pneukarnik_Mailing::NO_PROMOTIONS => __( 'Vyberte aspoň jednu Akci.', 'pneukarnik-booking' ),
			Pneukarnik_Mailing::NOT_TESTED    => __( 'Před odesláním si pošlete zkušební e‑mail, po každé změně znovu.', 'pneukarnik-booking' ),
			Pneukarnik_Mailing::NOT_DRAFT     => __( 'Rozesílka už se odesílá, změnit ji nejde.', 'pneukarnik-booking' ),
			Pneukarnik_Mailing::NOT_FOUND     => __( 'Rozesílka neexistuje.', 'pneukarnik-booking' ),
		];
		$error              = sanitize_key( wp_unslash( $_GET['error'] ?? '' ) );
		?>
		<div class="wrap">
			<h1><?php echo esc_html( null === $mailing ? __( 'Nová Rozesílka', 'pneukarnik-booking' ) : __( 'Rozesílka', 'pneukarnik-booking' ) ); ?></h1>
			<p><a href="<?php echo esc_url( self::url() ); ?>"><?php esc_html_e( '← E‑maily Zákazníkům', 'pneukarnik-booking' ); ?></a></p>

			<?php if ( isset( $errors[ $error ] ) ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $errors[ $error ] ); ?></p></div>
			<?php elseif ( isset( $_GET['saved'] ) ) : ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Uloženo.', 'pneukarnik-booking' ); ?></p></div>
			<?php endif; ?>
			<?php if ( 'sent' === ( $_GET['test'] ?? '' ) ) : ?>
				<div class="notice notice-success">
					<p>
						<?php
						/* translators: %s: e‑mail Provozovatele */
						echo esc_html( sprintf( __( 'Zkušební Rozesílka odeslaná na %s.', 'pneukarnik-booking' ), $provozovatel_email ) );
						?>
					</p>
				</div>
			<?php elseif ( isset( $_GET['test'] ) ) : ?>
				<div class="notice notice-error"><p><?php esc_html_e( 'Zkušební Rozesílku se nepodařilo odeslat. Zkontrolujte kontaktní e‑mail v Nastavení a nastavení odesílání pošty.', 'pneukarnik-booking' ); ?></p></div>
			<?php endif; ?>

			<?php if ( null !== $mailing && ! $draft ) : ?>
				<p><strong><?php echo esc_html( self::mailing_status( $mailing ) ); ?></strong></p>
			<?php else : ?>
				<?php $promotions = Pneukarnik_Mailing::promotions(); ?>
				<form method="post">
					<?php wp_nonce_field( 'pneukarnik_mailing' ); ?>
					<table class="form-table">
						<tr>
							<th><?php esc_html_e( 'Akce', 'pneukarnik-booking' ); ?></th>
							<td>
								<?php if ( ! $promotions ) : ?>
									<p>
										<?php
										/* translators: %d: počet dní */
										echo esc_html( sprintf( __( 'Žádná Akce neplatí ani nezačne do %d dní.', 'pneukarnik-booking' ), Pneukarnik_Mailing::DAYS_AHEAD ) );
										?>
										<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . Pneukarnik_Promotion::POST_TYPE ) ); ?>"><?php esc_html_e( 'Přidat Akci', 'pneukarnik-booking' ); ?></a>
									</p>
								<?php endif; ?>
								<fieldset>
									<?php foreach ( $promotions as $promotion ) : ?>
										<label style="display:block;margin-bottom:.4em">
											<input type="checkbox" name="promotions[]" value="<?php echo esc_attr( (string) $promotion->id ); ?>" <?php checked( in_array( $promotion->id, $mailing['promotion_ids'] ?? [], true ) ); ?>>
											<?php echo esc_html( self::promotion_label( $promotion ) . ' (' . $promotion->validity_label() . ')' ); ?>
										</label>
									<?php endforeach; ?>
								</fieldset>
							</td>
						</tr>
						<tr>
							<th><label for="pnk-mailing-intro"><?php esc_html_e( 'Úvodní věta', 'pneukarnik-booking' ); ?></label></th>
							<td><textarea id="pnk-mailing-intro" name="intro" rows="3" class="large-text"><?php echo esc_textarea( $mailing['intro'] ?? Pneukarnik_Mailing::DEFAULT_INTRO ); ?></textarea></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Příjemci', 'pneukarnik-booking' ); ?></th>
							<td>
								<p>
									<strong>
										<?php
										/* translators: %d: počet Zákazníků */
										echo esc_html( sprintf( __( 'Počet příjemců: %d', 'pneukarnik-booking' ), $audience ) );
										?>
									</strong>
								</p>
								<p class="description"><?php esc_html_e( 'Všichni, kterým smějí chodit Akce, i se souhlasem „informace o slevách“ ze starého webu. Každý ji dostane jednou.', 'pneukarnik-booking' ); ?></p>
							</td>
						</tr>
					</table>
					<p class="submit">
						<button type="submit" name="mailing_action" value="save" class="button"><?php esc_html_e( 'Uložit', 'pneukarnik-booking' ); ?></button>
						<?php if ( '' !== $provozovatel_email ) : ?>
							<button type="submit" name="mailing_action" value="test" class="button">
								<?php
								/* translators: %s: e‑mail Provozovatele */
								echo esc_html( sprintf( __( 'Uložit a poslat zkušební e‑mail na %s', 'pneukarnik-booking' ), $provozovatel_email ) );
								?>
							</button>
						<?php endif; ?>
						<?php
						/* translators: %d: počet Zákazníků */
						$send_label = sprintf( __( 'Odeslat hned (%d)', 'pneukarnik-booking' ), $audience );
						/* translators: %d: počet Zákazníků */
						$confirm = sprintf( __( 'Odeslat Rozesílku %d Zákazníkům? Vrátit to nejde.', 'pneukarnik-booking' ), $audience );
						?>
						<button type="submit" name="mailing_action" value="send" class="button button-primary" <?php disabled( null === ( $mailing['test_sent_at'] ?? null ) ); ?> onclick="return confirm(<?php echo esc_attr( (string) wp_json_encode( $confirm ) ); ?>)"><?php echo esc_html( $send_label ); ?></button>
						<?php if ( null !== $mailing ) : ?>
							<button type="submit" name="mailing_action" value="delete" class="button-link button-link-delete" style="margin-left:1em" onclick="return confirm(<?php echo esc_attr( (string) wp_json_encode( __( 'Smazat rozepsanou Rozesílku?', 'pneukarnik-booking' ) ) ); ?>)"><?php esc_html_e( 'Smazat', 'pneukarnik-booking' ); ?></button>
						<?php endif; ?>
					</p>
					<?php if ( '' === $provozovatel_email ) : ?>
						<p class="description"><?php esc_html_e( 'Zkušební e‑mail jde poslat jen na kontaktní e‑mail, vyplňte ho v Nastavení. Bez něj Rozesílku odeslat nejde.', 'pneukarnik-booking' ); ?></p>
					<?php elseif ( null === ( $mailing['test_sent_at'] ?? null ) ) : ?>
						<p class="description"><?php esc_html_e( 'Odeslat jde až po zkušebním e‑mailu, po každé změně znovu.', 'pneukarnik-booking' ); ?></p>
					<?php endif; ?>
				</form>
			<?php endif; ?>

			<?php if ( null !== $mailing ) : ?>
				<h2><?php esc_html_e( 'Náhled e‑mailu', 'pneukarnik-booking' ); ?></h2>
				<iframe title="<?php esc_attr_e( 'Náhled e‑mailu', 'pneukarnik-booking' ); ?>" srcdoc="<?php echo esc_attr( Pneukarnik_Mailing::preview( $mailing ) ); ?>" sandbox="" style="width:100%;max-width:640px;height:720px;border:1px solid #c3c4c7;background:#fff"></iframe>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Akce ve výběru a v seznamu: „Služba: název Akce“.
	 */
	private static function promotion_label( Pneukarnik_Promotion $promotion ): string {
		return (string) $promotion->service()?->title . ': ' . $promotion->title;
	}

	/**
	 * @param array{status:string,sent_count:int,finished_at:string|null} $mailing
	 */
	private static function mailing_status( array $mailing ): string {
		return match ( $mailing['status'] ) {
			/* translators: %d: počet Zákazníků */
			Pneukarnik_Mailing::SENDING => sprintf( __( 'odesílá se (zatím odesláno: %d)', 'pneukarnik-booking' ), $mailing['sent_count'] ),
			Pneukarnik_Mailing::SENT    => sprintf(
				/* translators: 1: počet Zákazníků, 2: den dokončení */
				__( 'odeslaná: %1$d (%2$s)', 'pneukarnik-booking' ),
				$mailing['sent_count'],
				Pneukarnik_Clock::at( (string) $mailing['finished_at'] )->format( 'j. n. Y' )
			),
			default                     => __( 'rozepsaná', 'pneukarnik-booking' ),
		};
	}
}
