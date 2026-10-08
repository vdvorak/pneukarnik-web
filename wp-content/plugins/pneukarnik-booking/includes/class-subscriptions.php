<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Vztah e‑mailu k Nabídkám a připomínkám (ADR 0003), jeden řádek na e‑mail a druh:
 *   REMINDER    Připomínka přezutí,
 *   PROMOTIONS  Akce (Rozesílky),
 *   REVIEW      Žádost o hodnocení,
 *   LEGACY      „informace o slevách“ ze starého webu, jen s původním účelem, tedy pro Akce (převod #21).
 *
 * Řádek nese, proč e‑mail smí chodit, s časem a zdrojem kvůli doložení:
 *   claimed_at    nárok z online Rezervace, ve které Zákazník Nabídky a připomínky neodmítl (soft opt‑in).
 *                 Platí až po návštěvě: Termín nezrušené Rezervace s tímto e‑mailem proběhl, nebo visited_at.
 *   consented_at  výslovný souhlas (consent_source), platí hned.
 *   withdrawn_at  odmítnutí ve formuláři nebo odvolání (withdrawn_source: rezervace, odkaz, stary-odkaz).
 *                 Má přednost a změní ho jen nový výslovný souhlas, další Rezervace ne.
 *   visited_at    Termín proběhlé Rezervace zapamatovaný při její anonymizaci, aby nárok přežil.
 * Rezervace zadaná Provozovatelem nárok nezakládá, za návštěvu se ale počítá.
 * Výmaz osobních údajů smaže všechny řádky e‑mailu.
 *
 * Odhlašovací odkaz /odhlaseni/?t={id}.{podpis} nese id řádku podepsané HMAC-SHA256 tajným klíčem
 * webu spolu s časem souhlasu (nebo nároku), e‑mail v něm není. Po odvolání a novém souhlasu staré
 * odkazy přestanou platit. Starý odkaz /cancel-subscription?email=… odhlašuje jen LEGACY
 * a zapamatuje si i e‑mail, který zatím nezná, aby ho převod starých souhlasů (#21) znovu
 * nepřihlásil: převod proto existující záznam nikdy nepřepisuje.
 */
final class Pneukarnik_Subscriptions {

	public const REMINDER   = 'reminder';
	public const PROMOTIONS = 'promotions';
	public const REVIEW     = 'review';
	public const LEGACY     = 'legacy';

	/** Druhy Nabídek a připomínek, které odmítá a zakládá online Rezervace. */
	public const KINDS = [ self::REMINDER, self::PROMOTIONS, self::REVIEW ];

	public const DONE          = 'unsubscribe.done';
	public const INVALID_TOKEN = 'unsubscribe.invalid_token';

	/**
	 * SQL podmínka pro řádek s aliasem s: smí teď dostávat svůj druh e‑mailu.
	 * Hodnoty placeholderů dává receives_args().
	 */
	public const RECEIVES_SQL = 's.withdrawn_at IS NULL AND (s.consented_at IS NOT NULL OR (s.claimed_at IS NOT NULL AND (s.visited_at IS NOT NULL OR EXISTS (
		SELECT 1 FROM %i b WHERE b.customer_email = s.email AND b.status = %s AND TIMESTAMP(b.booking_date, b.time_start) <= %s))))';

	/**
	 * @return array{0:string,1:string,2:string}
	 */
	public static function receives_args(): array {
		return [ Pneukarnik_DB::bookings_table(), Pneukarnik_Booking::STATUS_CONFIRMED, Pneukarnik_Clock::now()->format( 'Y-m-d H:i:s' ) ];
	}

	/**
	 * Online Rezervace: zaškrtnuté „Neposílat“ odmítne všechny druhy, nezaškrtnuté založí nárok
	 * u druhů, ke kterým e‑mail ještě nemá vztah. Odmítnutí ani souhlas nepřepíše.
	 */
	public static function after_online_booking( string $email, bool $refused ): void {
		if ( $refused ) {
			self::refuse( $email, 'rezervace' );
			return;
		}
		$email = self::normalize( $email );
		if ( ! is_email( $email ) ) {
			return;
		}
		global $wpdb;
		$now = Pneukarnik_Clock::now()->format( 'Y-m-d H:i:s' );
		foreach ( self::KINDS as $purpose ) {
			$wpdb->query(
				$wpdb->prepare(
					'INSERT INTO %i (email, purpose, claimed_at) VALUES (%s, %s, %s)
					 ON DUPLICATE KEY UPDATE claimed_at = COALESCE(claimed_at, VALUES(claimed_at))',
					Pneukarnik_DB::subscriptions_table(),
					$email,
					$purpose,
					$now
				)
			);
		}
	}

	/**
	 * Odmítne všechny druhy Nabídek a připomínek. Dřívější odmítnutí zůstane, jak bylo.
	 */
	public static function refuse( string $email, string $source ): void {
		$email = self::normalize( $email );
		if ( ! is_email( $email ) ) {
			return;
		}
		global $wpdb;
		$now = Pneukarnik_Clock::now()->format( 'Y-m-d H:i:s' );
		foreach ( self::KINDS as $purpose ) {
			$wpdb->query(
				$wpdb->prepare(
					'INSERT INTO %i (email, purpose, withdrawn_at, withdrawn_source) VALUES (%s, %s, %s, %s)
					 ON DUPLICATE KEY UPDATE
					   withdrawn_source = IF(withdrawn_at IS NULL, VALUES(withdrawn_source), withdrawn_source),
					   withdrawn_at = COALESCE(withdrawn_at, VALUES(withdrawn_at))',
					Pneukarnik_DB::subscriptions_table(),
					$email,
					$purpose,
					$now,
					$source
				)
			);
		}
	}

	/**
	 * Zaznamená výslovný souhlas. Platný souhlas zůstane, jak byl (čas a zdroj prvního udělení),
	 * odmítnutý nebo odvolaný platí znovu od teď. Odeslané Připomínky se pamatují dál.
	 */
	public static function consent( string $email, string $purpose, string $source ): void {
		global $wpdb;
		$email = self::normalize( $email );
		if ( ! is_email( $email ) ) {
			return;
		}
		$wpdb->query(
			$wpdb->prepare(
				'INSERT INTO %i (email, purpose, consent_source, consented_at) VALUES (%s, %s, %s, %s)
				 ON DUPLICATE KEY UPDATE
				   consent_source = IF(withdrawn_at IS NULL AND consented_at IS NOT NULL, consent_source, VALUES(consent_source)),
				   consented_at = IF(withdrawn_at IS NULL AND consented_at IS NOT NULL, consented_at, VALUES(consented_at)),
				   withdrawn_source = NULL,
				   withdrawn_at = NULL',
				Pneukarnik_DB::subscriptions_table(),
				$email,
				$purpose,
				$source,
				Pneukarnik_Clock::now()->format( 'Y-m-d H:i:s' )
			)
		);
	}

	/**
	 * Zapamatuje si návštěvu e‑mailů nezrušených Rezervací, než se anonymizují (Termín už proběhl).
	 * Jen u e‑mailů, které k Nabídkám a připomínkám nějaký vztah mají, jiné e‑maily neukládá.
	 *
	 * @param array<int|string> $booking_ids
	 */
	public static function remember_visits( array $booking_ids ): void {
		if ( ! $booking_ids ) {
			return;
		}
		global $wpdb;
		$ids = implode( ',', array_map( 'intval', $booking_ids ) );
		// $ids jsou jen celá čísla.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE %i s JOIN (
				   SELECT customer_email, MIN(TIMESTAMP(booking_date, time_start)) AS visited_at FROM %i
				   WHERE id IN ({$ids}) AND status = %s GROUP BY customer_email
				 ) v ON v.customer_email = s.email
				 SET s.visited_at = LEAST(COALESCE(s.visited_at, v.visited_at), v.visited_at)",
				Pneukarnik_DB::subscriptions_table(),
				Pneukarnik_DB::bookings_table(),
				Pneukarnik_Booking::STATUS_CONFIRMED
			)
		);
		// phpcs:enable
	}

	/**
	 * Souhlas „informace o slevách“ ze starého webu (převod #21) jen s původním účelem.
	 * Existující záznam e‑mailu (i odhlášení starým odkazem) nepřepíše.
	 *
	 * @return bool Jestli se souhlas zapsal.
	 */
	public static function import_legacy( string $email, string $consented_at ): bool {
		global $wpdb;
		return 1 === $wpdb->query(
			$wpdb->prepare(
				'INSERT IGNORE INTO %i (email, purpose, consent_source, consented_at) VALUES (%s, %s, %s, %s)',
				Pneukarnik_DB::subscriptions_table(),
				self::normalize( $email ),
				self::LEGACY,
				Pneukarnik_Booking::SOURCE_STARY_WEB,
				$consented_at
			)
		);
	}

	/**
	 * Jestli má e‑mail k druhu platný souhlas nebo nárok (u nároku bez ohledu na návštěvu).
	 */
	public static function is_active( string $email, string $purpose ): bool {
		global $wpdb;
		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT 1 FROM %i WHERE email = %s AND purpose = %s AND withdrawn_at IS NULL AND COALESCE(consented_at, claimed_at) IS NOT NULL',
				Pneukarnik_DB::subscriptions_table(),
				self::normalize( $email ),
				$purpose
			)
		);
	}

	/**
	 * Odhlášení odkazem z Připomínky. Opakované odhlášení je taky v pořádku.
	 *
	 * @return string DONE nebo INVALID_TOKEN
	 */
	public static function withdraw_by_token( mixed $token ): string {
		$id = self::id_from_token( $token );
		if ( null === $id ) {
			return self::INVALID_TOKEN;
		}
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET withdrawn_at = %s, withdrawn_source = %s WHERE id = %d AND withdrawn_at IS NULL',
				Pneukarnik_DB::subscriptions_table(),
				Pneukarnik_Clock::now()->format( 'Y-m-d H:i:s' ),
				'odkaz',
				$id
			)
		);
		return self::DONE;
	}

	/**
	 * Odhlášení starým odkazem /cancel-subscription?email=… (bez podpisu, jako na starém webu),
	 * proto jen ze starého odběru. Neznámý e‑mail dopadne stejně, odpověď neprozradí, kdo odebírá.
	 */
	public static function withdraw_legacy( mixed $email ): string {
		$email = is_string( $email ) ? self::normalize( $email ) : '';
		if ( is_email( $email ) ) {
			global $wpdb;
			$wpdb->query(
				$wpdb->prepare(
					'INSERT INTO %i (email, purpose, withdrawn_source, withdrawn_at) VALUES (%s, %s, %s, %s)
					 ON DUPLICATE KEY UPDATE
					   withdrawn_source = IF(withdrawn_at IS NULL, VALUES(withdrawn_source), withdrawn_source),
					   withdrawn_at = COALESCE(withdrawn_at, VALUES(withdrawn_at))',
					Pneukarnik_DB::subscriptions_table(),
					$email,
					self::LEGACY,
					'stary-odkaz',
					Pneukarnik_Clock::now()->format( 'Y-m-d H:i:s' )
				)
			);
		}
		return self::DONE;
	}

	/**
	 * Smaže vztah e‑mailu ke všem druhům i zapamatovanou návštěvu (výmaz osobních údajů
	 * v nástrojích WordPressu).
	 */
	public static function erase( string $email ): int {
		global $wpdb;
		return (int) $wpdb->delete( Pneukarnik_DB::subscriptions_table(), [ 'email' => self::normalize( $email ) ] );
	}

	/**
	 * Odhlašovací odkaz pro řádek se souhlasem nebo nárokem, jinak prázdný.
	 */
	public static function unsubscribe_url( int $id ): string {
		$since = self::since( $id );
		return null === $since ? '' : add_query_arg( 't', self::token( $id, $since ), home_url( '/odhlaseni/' ) );
	}

	public static function normalize( string $email ): string {
		return strtolower( trim( $email ) );
	}

	private static function token( int $id, string $since ): string {
		return $id . '.' . hash_hmac( 'sha256', 'unsubscribe|' . $id . '|' . $since, wp_salt( 'pneukarnik' ) );
	}

	private static function id_from_token( mixed $token ): ?int {
		if ( ! is_string( $token ) || ! preg_match( '/^([1-9][0-9]*)\.[0-9a-f]{64}$/', $token, $m ) ) {
			return null;
		}
		$since = self::since( (int) $m[1] );
		return null !== $since && hash_equals( self::token( (int) $m[1], $since ), $token ) ? (int) $m[1] : null;
	}

	/**
	 * Čas souhlasu, jinak nároku. Nový souhlas po odvolání ho změní, odvolání ne.
	 */
	private static function since( int $id ): ?string {
		global $wpdb;
		$since = $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(consented_at, claimed_at) FROM %i WHERE id = %d', Pneukarnik_DB::subscriptions_table(), $id ) );
		return null === $since ? null : (string) $since;
	}
}
