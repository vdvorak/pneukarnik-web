<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Připomínka přezutí (viz CONTEXT.md): nastavený počet dní před začátkem každé Sezóny dostane
 * každý e‑mail, který smí dostávat Nabídky a připomínky druhu Pneukarnik_Subscriptions::REMINDER,
 * jednu Připomínku s odkazem na předvyplněnou rezervaci a na stránku nastavení e‑mailů.
 *
 * Plánovaná úloha běží každou hodinu a posílá po dávkách. Příjemce se před odesláním označí
 * Sezónou (last_season), takže opakované nebo souběžné spuštění nic nepošle dvakrát.
 * Když odeslání selže, označení se vrátí a zkusí se to příště.
 */
final class Pneukarnik_Reminder {

	public const CRON_HOOK = 'pneukarnik_reminder_send';

	public const OPTION_DAYS = 'pneukarnik_reminder_days';

	/** Úvod e‑mailu (dřív mezi texty e‑mailů v Nastavení, proto ten název). */
	public const OPTION_INTRO = 'pneukarnik_email_reminder';

	public const NO_PROVOZOVATEL_EMAIL = 'reminder.no_provozovatel_email';
	public const SEND_FAILED           = 'reminder.send_failed';

	private const DEFAULT_DAYS = 14;

	private const DEFAULT_INTRO = "Dobrý den,\nblíží se sezóna přezouvání a o termíny bývá velký zájem.";

	/** Kolik Připomínek nejvýš odejde za jedno spuštění (limity SMTP hostingu). */
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

	/** Kolik dní před začátkem Sezóny se Připomínky posílají, 0 = neposílají se. */
	public static function days_before(): int {
		return max( 0, (int) get_option( self::OPTION_DAYS, self::DEFAULT_DAYS ) );
	}

	/** Úvod e‑mailu, upravuje ho Provozovatel na stránce E‑maily Zákazníkům. */
	public static function intro(): string {
		return (string) get_option( self::OPTION_INTRO, self::DEFAULT_INTRO );
	}

	/**
	 * Nejbližší Sezóna, která ještě nezačala, a od kdy se před ní posílají Připomínky.
	 * Null, když Provozovatel žádnou Sezónu nenastavil.
	 *
	 * @return array{name:string,key:string,from:string,send_from:string}|null YYYY-MM-DD, key např. 2027-spring
	 */
	public static function upcoming(): ?array {
		$today    = Pneukarnik_Clock::today();
		$upcoming = null;
		foreach ( Pneukarnik_Season::get_all() as $name => $season ) {
			if ( '' === $season['from'] ) {
				continue;
			}
			$year = (int) $today->format( 'Y' );
			$from = Pneukarnik_Clock::at( "{$year}-{$season['from']}" );
			if ( $from <= $today ) {
				$from = Pneukarnik_Clock::at( ( $year + 1 ) . "-{$season['from']}" );
			}
			if ( null === $upcoming || $from->format( 'Y-m-d' ) < $upcoming['from'] ) {
				$upcoming = [
					'name'      => $name,
					'key'       => $from->format( 'Y' ) . '-' . $name,
					'from'      => $from->format( 'Y-m-d' ),
					'send_from' => $from->modify( '-' . self::days_before() . ' days' )->format( 'Y-m-d' ),
				];
			}
		}
		return $upcoming;
	}

	/**
	 * Pošle další dávku Připomínek, když je čas (plánovaná úloha).
	 */
	public static function send_due(): void {
		$season = self::upcoming();
		if ( self::days_before() <= 0 || null === $season || Pneukarnik_Clock::today()->format( 'Y-m-d' ) < $season['send_from'] ) {
			return;
		}
		foreach ( self::recipients( $season['key'], self::BATCH_SIZE ) as $recipient ) {
			if ( ! self::claim( $recipient['id'], $season['key'] ) ) {
				continue; // Mezitím ji poslalo souběžné spuštění.
			}
			$settings = Pneukarnik_Subscriptions::settings_url( $recipient['email'] );
			$email    = self::email( $season, $recipient['email'], $settings, $recipient['consented'] );
			if ( ! $email->send( $recipient['email'], Pneukarnik_Contact::email(), self::unsubscribe_headers( $settings ) ) ) {
				self::release( $recipient['id'], $season['key'], $recipient['last_season'] );
			}
		}
	}

	/**
	 * Náhled pro Provozovatele: nejbližší Sezóna, od kdy se posílá a kolik lidí Připomínku dostane.
	 *
	 * @return array{enabled:bool,days_before:int,season:string|null,season_from:string|null,send_from:string|null,recipients:int}
	 */
	public static function preview(): array {
		$season = self::upcoming();
		return [
			'enabled'     => self::days_before() > 0,
			'days_before' => self::days_before(),
			'season'      => $season['name'] ?? null,
			'season_from' => $season['from'] ?? null,
			'send_from'   => $season['send_from'] ?? null,
			'recipients'  => self::count_recipients( $season['key'] ?? '' ),
		];
	}

	/**
	 * Zkušební Připomínka na e‑mail Provozovatele z Nastavení, nikam jinam. Nic neoznačí.
	 *
	 * @return string|null Adresa, kam odešla, null = Provozovatel nemá e‑mail nebo odeslání selhalo.
	 */
	public static function send_test(): ?string {
		$to = Pneukarnik_Contact::email();
		if ( '' === $to ) {
			return null;
		}
		$today  = Pneukarnik_Clock::today();
		$season = self::upcoming() ?? [
			'name'      => Pneukarnik_Season::SPRING,
			'key'       => '',
			'from'      => $today->format( 'Y-m-d' ),
			'send_from' => $today->format( 'Y-m-d' ),
		];
		$email  = self::email( $season, $to, home_url( '/odhlaseni/' ), false, __( '[Zkouška] ', 'pneukarnik-booking' ) )
			->paragraph( __( 'Toto je zkušební Připomínka pro Provozovatele. Zákazníci dostanou vlastní odkaz na nastavení e‑mailů, ten tady nic nenastaví.', 'pneukarnik-booking' ) );
		return $email->send( $to ) ? $to : null;
	}

	/**
	 * @param array{name:string,from:string} $season
	 * @param bool                           $consented Výslovný souhlas, jinak nárok po návštěvě.
	 */
	private static function email( array $season, string $to, string $settings_url, bool $consented, string $subject_prefix = '' ): Pneukarnik_Email {
		$name  = mb_strtolower( Pneukarnik_Season::names()[ $season['name'] ] ?? '' );
		$from  = Pneukarnik_Clock::at( $season['from'] );
		$phone = Pneukarnik_Contact::phone();

		/* translators: 1: název Sezóny malým písmenem (jarní, podzimní), 2: den začátku, např. 15. 3. */
		return ( new Pneukarnik_Email( $subject_prefix . sprintf( __( 'Je čas přezout: %1$s sezóna začíná %2$s', 'pneukarnik-booking' ), $name, $from->format( 'j. n.' ) ) ) )
			->heading( __( 'Je čas přezout', 'pneukarnik-booking' ) )
			->paragraph( self::intro() )
			/* translators: 1: název Sezóny malým písmenem, 2: datum začátku, např. 15. 3. 2027 */
			->paragraph( sprintf( __( 'Naše %1$s sezóna přezouvání začíná %2$s. Objednejte se včas, ať máte termín, který vám vyhovuje.', 'pneukarnik-booking' ), $name, $from->format( 'j. n. Y' ) ) )
			->button( __( 'Objednat přezutí', 'pneukarnik-booking' ), Pneukarnik_Prefill::url_for_email( $to ) )
			/* translators: %s: telefon Provozovatele */
			->paragraph( '' !== $phone ? sprintf( __( 'Raději zavoláte? Jsme na %s.', 'pneukarnik-booking' ), $phone ) : '' )
			->signature( Pneukarnik_Notifications::text( 'signature' ) )
			->footer(
				$consented
					? __( 'Připomínku dostáváte, protože jste s ní souhlasili.', 'pneukarnik-booking' )
					: __( 'Připomínku dostáváte, protože jste u nás byli a při online rezervaci jste e‑maily s nabídkami a připomínkami neodmítli.', 'pneukarnik-booking' ),
				__( 'Nastavit, co vám posíláme', 'pneukarnik-booking' ),
				$settings_url
			);
	}

	/**
	 * Odhlášení přímo z pošty jedním kliknutím (RFC 8058), vyžadují ho Gmail i Seznam u hromadné pošty.
	 * POST na stránku nastavení odvolá všechny Nabídky a připomínky (Pneukarnik_Booking_Pages).
	 *
	 * @return list<string>
	 */
	private static function unsubscribe_headers( string $url ): array {
		return [
			'List-Unsubscribe: <' . $url . '>',
			'List-Unsubscribe-Post: List-Unsubscribe=One-Click',
		];
	}

	/**
	 * E‑maily, které Připomínku smějí dostávat a pro Sezónu jim ještě neodešla.
	 *
	 * @return list<array{id:int,email:string,last_season:string|null,consented:bool}>
	 */
	private static function recipients( string $key, int $limit ): array {
		global $wpdb;
		// Pneukarnik_Subscriptions::RECEIVES_SQL je pevný fragment s placeholdery.
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT s.id, s.email, s.last_season, s.consented_at IS NOT NULL AS consented FROM %i s
				 WHERE s.purpose = %s AND ' . Pneukarnik_Subscriptions::RECEIVES_SQL . ' AND (s.last_season IS NULL OR s.last_season <> %s)
				 ORDER BY s.id LIMIT %d',
				Pneukarnik_DB::subscriptions_table(),
				Pneukarnik_Subscriptions::REMINDER,
				...[ ...Pneukarnik_Subscriptions::receives_args(), $key, $limit ]
			),
			ARRAY_A
		);
		// phpcs:enable
		return array_map(
			static fn( array $row ): array => [
				'id'          => (int) $row['id'],
				'email'       => (string) $row['email'],
				'last_season' => null === $row['last_season'] ? null : (string) $row['last_season'],
				'consented'   => (bool) $row['consented'],
			],
			$rows ?: []
		);
	}

	private static function count_recipients( string $key ): int {
		global $wpdb;
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i s
				 WHERE s.purpose = %s AND ' . Pneukarnik_Subscriptions::RECEIVES_SQL . ' AND (s.last_season IS NULL OR s.last_season <> %s)',
				Pneukarnik_DB::subscriptions_table(),
				Pneukarnik_Subscriptions::REMINDER,
				...[ ...Pneukarnik_Subscriptions::receives_args(), $key ]
			)
		);
		// phpcs:enable
	}

	/**
	 * Označí řádek jako obsloužený pro Sezónu. False, když už ho označilo jiné spuštění.
	 */
	private static function claim( int $id, string $key ): bool {
		global $wpdb;
		return 1 === (int) $wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET last_season = %s WHERE id = %d AND (last_season IS NULL OR last_season <> %s)',
				Pneukarnik_DB::subscriptions_table(),
				$key,
				$id,
				$key
			)
		);
	}

	private static function release( int $id, string $key, ?string $previous ): void {
		global $wpdb;
		$wpdb->update( Pneukarnik_DB::subscriptions_table(), [ 'last_season' => $previous ], [ 'id' => $id, 'last_season' => $key ] ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	}
}
