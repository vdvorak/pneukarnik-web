<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Žádost o hodnocení (viz CONTEXT.md): den po Termínu nezrušené Rezervace od 10:00 e‑mail s odkazem
 * na napsání hodnocení na Googlu každému e‑mailu, který smí dostávat Nabídky a připomínky druhu
 * Pneukarnik_Subscriptions::REVIEW, nejvýš jednou za celou dobu. Kdo ji po návštěvě nedostal (byla
 * vypnutá, odmítl ji), dostane ji po další návštěvě, ve které smí.
 *
 * Odkaz vede na místo z Nastavení Google recenzí (Place ID), bez něj se nic neposílá. Provozovatel
 * ji vypíná na stránce E‑maily Zákazníkům.
 *
 * Plánovaná úloha běží každou hodinu a posílá po dávkách. Řádek e‑mailu se před odesláním označí
 * (sent_at), takže opakované nebo souběžné spuštění nic nepošle dvakrát. Označení přežije anonymizaci
 * Rezervace, výmaz osobních údajů ho smaže. Když odeslání selže, označení se vrátí a zkusí se to příště.
 */
final class Pneukarnik_Review_Request {

	public const CRON_HOOK = 'pneukarnik_review_request_send';

	public const OPTION_ENABLED = 'pneukarnik_review_request_enabled';
	public const OPTION_INTRO   = 'pneukarnik_review_request_intro';

	/** Hodina místního času den po Termínu, od které se Žádosti posílají. */
	public const HOUR = 10;

	private const DEFAULT_INTRO = "Dobrý den,\nděkujeme, že jste u nás byli. Budeme rádi, když svou zkušenost popíšete v hodnocení na Googlu. Pomůžete tím ostatním při výběru servisu.";

	/** Kolik Žádostí nejvýš odejde za jedno spuštění (limity SMTP hostingu). */
	private const BATCH_SIZE = 50;

	public static function init(): void {
		add_action( self::CRON_HOOK, [ self::class, 'send_due' ] );
		add_action( 'init', [ self::class, 'schedule' ] );
	}

	/**
	 * Každou hodinu, naplánuje se samo i u už aktivního pluginu.
	 */
	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( Pneukarnik_Clock::now()->getTimestamp(), 'hourly', self::CRON_HOOK );
		}
	}

	public static function enabled(): bool {
		return '1' === (string) get_option( self::OPTION_ENABLED, '1' );
	}

	/** Úvod e‑mailu, upravuje ho Provozovatel na stránce E‑maily Zákazníkům. */
	public static function intro(): string {
		return (string) get_option( self::OPTION_INTRO, self::DEFAULT_INTRO );
	}

	public static function save( bool $enabled, string $intro ): void {
		update_option( self::OPTION_ENABLED, $enabled ? '1' : '0' );
		update_option( self::OPTION_INTRO, sanitize_textarea_field( $intro ) );
	}

	/**
	 * Odkaz na napsání hodnocení místa z Nastavení Google recenzí, prázdný bez Place ID.
	 */
	public static function write_review_url(): string {
		$place = Pneukarnik_Reviews::place_id();
		return '' === $place ? '' : 'https://search.google.com/local/writereview?placeid=' . rawurlencode( $place );
	}

	/**
	 * Pošle další dávku Žádostí ke včerejším Termínům, když je čas (plánovaná úloha).
	 */
	public static function send_due(): void {
		$url = self::write_review_url();
		if ( ! self::enabled() || '' === $url || (int) Pneukarnik_Clock::now()->format( 'G' ) < self::HOUR ) {
			return;
		}
		$visited = Pneukarnik_Clock::today()->modify( '-1 day' )->format( 'Y-m-d' );
		foreach ( self::recipients( $visited, self::BATCH_SIZE ) as $recipient ) {
			if ( ! self::claim( $recipient['id'] ) ) {
				continue; // Mezitím ji poslalo souběžné spuštění.
			}
			$settings = Pneukarnik_Subscriptions::settings_url( $recipient['email'] );
			$email    = self::email( $url, $settings, $recipient['consented'] );
			if ( ! $email->send( $recipient['email'], Pneukarnik_Contact::email(), Pneukarnik_Subscriptions::unsubscribe_headers( $settings ) ) ) {
				self::release( $recipient['id'] );
			}
		}
	}

	/** Kolika e‑mailům už Žádost odešla. */
	public static function sent_count(): int {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE purpose = %s AND sent_at IS NOT NULL',
				Pneukarnik_DB::subscriptions_table(),
				Pneukarnik_Subscriptions::REVIEW
			)
		);
	}

	/**
	 * Zkušební Žádost na e‑mail Provozovatele z Nastavení, nikam jinam. Nic neoznačí.
	 *
	 * @return string|null Adresa, kam odešla, null = Provozovatel nemá e‑mail, chybí Place ID nebo odeslání selhalo.
	 */
	public static function send_test(): ?string {
		$to  = Pneukarnik_Contact::email();
		$url = self::write_review_url();
		if ( '' === $to || '' === $url ) {
			return null;
		}
		$email = self::email( $url, home_url( '/odhlaseni/' ), false, __( '[Zkouška] ', 'pneukarnik-booking' ) )
			->paragraph( __( 'Toto je zkušební Žádost o hodnocení pro Provozovatele. Zákazníci dostanou vlastní odkaz na nastavení e‑mailů, ten tady nic nenastaví.', 'pneukarnik-booking' ) );
		return $email->send( $to ) ? $to : null;
	}

	/**
	 * @param bool $consented Výslovný souhlas, jinak nárok po návštěvě.
	 */
	private static function email( string $review_url, string $settings_url, bool $consented, string $subject_prefix = '' ): Pneukarnik_Email {
		return ( new Pneukarnik_Email( $subject_prefix . __( 'Jak jste u nás byli spokojeni?', 'pneukarnik-booking' ) ) )
			->heading( __( 'Děkujeme za návštěvu', 'pneukarnik-booking' ) )
			->paragraph( self::intro() )
			->paragraph( __( 'Stačí vybrat počet hvězdiček, pár slov navíc nás potěší.', 'pneukarnik-booking' ) )
			->button( __( 'Napsat hodnocení na Googlu', 'pneukarnik-booking' ), $review_url )
			->signature( Pneukarnik_Notifications::text( 'signature' ) )
			->footer(
				$consented
					? __( 'Tento e‑mail dostáváte, protože jste souhlasili s e‑maily s nabídkami a připomínkami. Prosbu o hodnocení posíláme jen jednou.', 'pneukarnik-booking' )
					: __( 'Tento e‑mail dostáváte, protože jste u nás byli a při online rezervaci jste e‑maily s nabídkami a připomínkami neodmítli. Prosbu o hodnocení posíláme jen jednou.', 'pneukarnik-booking' ),
				__( 'Nastavit, co vám posíláme', 'pneukarnik-booking' ),
				$settings_url
			);
	}

	/**
	 * E‑maily, které Žádost smějí dostávat, ještě jim neodešla a v den $visited měly Termín
	 * nezrušené Rezervace.
	 *
	 * @return list<array{id:int,email:string,consented:bool}>
	 */
	private static function recipients( string $visited, int $limit ): array {
		global $wpdb;
		// Pneukarnik_Subscriptions::RECEIVES_SQL je pevný fragment s placeholdery.
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT s.id, s.email, s.consented_at IS NOT NULL AS consented FROM %i s
				 WHERE s.purpose = %s AND s.sent_at IS NULL AND ' . Pneukarnik_Subscriptions::RECEIVES_SQL . '
				   AND EXISTS (SELECT 1 FROM %i v WHERE v.customer_email = s.email AND v.status = %s AND v.booking_date = %s)
				 ORDER BY s.id LIMIT %d',
				Pneukarnik_DB::subscriptions_table(),
				Pneukarnik_Subscriptions::REVIEW,
				...[
					...Pneukarnik_Subscriptions::receives_args(),
					Pneukarnik_DB::bookings_table(),
					Pneukarnik_Booking::STATUS_CONFIRMED,
					$visited,
					$limit,
				]
			),
			ARRAY_A
		);
		// phpcs:enable
		return array_map(
			static fn( array $row ): array => [
				'id'        => (int) $row['id'],
				'email'     => (string) $row['email'],
				'consented' => (bool) $row['consented'],
			],
			$rows ?: []
		);
	}

	/**
	 * Označí řádek jako obsloužený. False, když už ho označilo jiné spuštění.
	 */
	private static function claim( int $id ): bool {
		global $wpdb;
		return 1 === (int) $wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET sent_at = %s WHERE id = %d AND sent_at IS NULL',
				Pneukarnik_DB::subscriptions_table(),
				Pneukarnik_Clock::now()->format( 'Y-m-d H:i:s' ),
				$id
			)
		);
	}

	private static function release( int $id ): void {
		global $wpdb;
		$wpdb->update( Pneukarnik_DB::subscriptions_table(), [ 'sent_at' => null ], [ 'id' => $id ] );
	}
}
