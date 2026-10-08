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
 *   withdrawn_at  odmítnutí ve formuláři nebo odvolání (withdrawn_source: rezervace, nastaveni, jedno-kliknuti,
 *                 odkaz, stary-odkaz).
 *                 Má přednost, dokud ho nezmění nový výslovný souhlas nebo další online Rezervace
 *                 s nezaškrtnutým „Neposílat“ (ta založí nový nárok místo dřívějšího souhlasu).
 *   visited_at    Termín proběhlé Rezervace zapamatovaný při její anonymizaci, aby nárok přežil.
 * Rezervace zadaná Provozovatelem nárok nezakládá, za návštěvu se ale počítá.
 * Výmaz osobních údajů smaže všechny řádky e‑mailu.
 *
 * Stránka nastavení e‑mailů /odhlaseni/?k={id}.{podpis}: klíč nese id některého řádku e‑mailu
 * podepsané HMAC-SHA256 tajným klíčem webu spolu s e‑mailem, e‑mail v odkazu není. Platí, dokud
 * výmaz osobních údajů řádky e‑mailu nesmaže. Zvlášť jde vypnout a zapnout Připomínku přezutí
 * a Akce (i LEGACY), „Neposílat nic“ odvolá všechny druhy. Zapnutí je výslovný souhlas (nastaveni).
 *
 * „Ano, posílejte“ /odhlaseni/?s={id}.{podpis} z potvrzení Rezervace zadané Provozovatelem, když e‑mail
 * k Nabídkám a připomínkám ještě nemá žádný vztah: podpis id Rezervace spolu s jejím e‑mailem, platí do
 * anonymizace Rezervace. Otevření nic nezapíše, souhlas se všemi druhy (potvrzeni) zapíše až tlačítko na stránce.
 *
 * Odkazy odeslané dřív fungují dál se stejným účinkem: /odhlaseni/?t={id}.{podpis} z Připomínek
 * před stránkou nastavení odvolá Připomínku přezutí řádku id (podpis s časem souhlasu nebo nároku,
 * po odvolání a novém souhlasu nebo nároku přestane platit). Starý odkaz /cancel-subscription?email=…
 * odhlašuje jen LEGACY a zapamatuje si i e‑mail, který zatím nezná, aby ho převod starých souhlasů
 * (#21) znovu nepřihlásil: převod proto existující záznam nikdy nepřepisuje.
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
	 * Online Rezervace: zaškrtnuté „Neposílat“ odmítne všechny druhy, nezaškrtnuté založí nárok.
	 * Platný souhlas nebo nárok zůstane, jak byl, odmítnutý nebo odvolaný druh dostane nový nárok
	 * od teď (Zákazník mohl „Neposílat“ zaškrtnout a nechal ho prázdné).
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
					 ON DUPLICATE KEY UPDATE
					   consent_source = IF(withdrawn_at IS NULL, consent_source, NULL),
					   consented_at = IF(withdrawn_at IS NULL, consented_at, NULL),
					   claimed_at = IF(withdrawn_at IS NULL, COALESCE(claimed_at, VALUES(claimed_at)), VALUES(claimed_at)),
					   withdrawn_source = NULL,
					   withdrawn_at = NULL',
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
		self::withdraw( $email, self::KINDS, $source );
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
	 * Odkaz „Ano, posílejte“ do potvrzení Rezervace zadané Provozovatelem, prázdný pro webovou
	 * Rezervaci a pro e‑mail, který už souhlas, nárok nebo odmítnutí má (i starý souhlas).
	 *
	 * @param array<string,mixed> $booking Rezervace (Pneukarnik_Booking::get_by_id).
	 */
	public static function offer_url( array $booking ): string {
		$email = self::normalize( (string) $booking['customer_email'] );
		if ( Pneukarnik_Booking::SOURCE_WEB === $booking['source'] || ! is_email( $email ) || self::has_record( $email ) ) {
			return '';
		}
		return add_query_arg( 's', self::offer_token( (int) $booking['id'], $email ), home_url( '/odhlaseni/' ) );
	}

	/**
	 * E‑mail podle odkazu „Ano, posílejte“, null = neplatný odkaz. Nic nezapíše.
	 */
	public static function offer( mixed $token ): ?string {
		if ( ! is_string( $token ) || ! preg_match( '/^([1-9][0-9]*)\.[0-9a-f]{64}$/', $token, $m ) ) {
			return null;
		}
		global $wpdb;
		$email = $wpdb->get_var( $wpdb->prepare( 'SELECT customer_email FROM %i WHERE id = %d', Pneukarnik_DB::bookings_table(), (int) $m[1] ) );
		$email = self::normalize( (string) $email );
		return is_email( $email ) && hash_equals( self::offer_token( (int) $m[1], $email ), $token ) ? $email : null;
	}

	/**
	 * „Ano, posílejte“: výslovný souhlas se všemi druhy Nabídek a připomínek, platí hned.
	 *
	 * @return string|null Adresa stránky nastavení e‑mailu, null = neplatný odkaz (nic se nezapíše).
	 */
	public static function accept_offer( mixed $token ): ?string {
		$email = self::offer( $token );
		if ( null === $email ) {
			return null;
		}
		foreach ( self::KINDS as $purpose ) {
			self::consent( $email, $purpose, 'potvrzeni' );
		}
		global $wpdb;
		$id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT MIN(id) FROM %i WHERE email = %s', Pneukarnik_DB::subscriptions_table(), $email ) );
		return self::settings_url_for_key( self::key( $id, $email ) );
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
	 * Odhlášení odkazem z Připomínky odeslané před stránkou nastavení. Opakované odhlášení
	 * je taky v pořádku.
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
		self::withdraw( is_string( $email ) ? $email : '', [ self::LEGACY ], 'stary-odkaz' );
		return self::DONE;
	}

	/**
	 * Stránka nastavení e‑mailů podle klíče z odkazu: e‑mail, co mu smí chodit (u nároku bez ohledu
	 * na návštěvu, Akce i ze starého souhlasu) a adresa stránky. Null = neplatný klíč.
	 *
	 * @return array{email:string,reminder:bool,promotions:bool,url:string}|null
	 */
	public static function settings( mixed $key ): ?array {
		$email = self::email_from_key( $key );
		if ( null === $email ) {
			return null;
		}
		return [
			'email'      => $email,
			'reminder'   => self::is_active( $email, self::REMINDER ),
			'promotions' => self::is_active( $email, self::PROMOTIONS ) || self::is_active( $email, self::LEGACY ),
			'url'        => self::settings_url_for_key( (string) $key ),
		];
	}

	/**
	 * Uloží nastavení ze stránky: vypnutý druh odvolá (Akce i se starým souhlasem), zapnutý,
	 * který neplatí, dostane výslovný souhlas. Platný souhlas nebo nárok zůstane, jak byl.
	 * Žádost o hodnocení nemění.
	 *
	 * @return string DONE nebo INVALID_TOKEN
	 */
	public static function save_settings( mixed $key, bool $reminder, bool $promotions ): string {
		$settings = self::settings( $key );
		if ( null === $settings ) {
			return self::INVALID_TOKEN;
		}
		$email = $settings['email'];
		if ( ! $reminder ) {
			self::withdraw( $email, [ self::REMINDER ], 'nastaveni' );
		} elseif ( ! $settings['reminder'] ) {
			self::consent( $email, self::REMINDER, 'nastaveni' );
		}
		if ( ! $promotions ) {
			self::withdraw( $email, [ self::PROMOTIONS, self::LEGACY ], 'nastaveni' );
		} elseif ( ! $settings['promotions'] ) {
			self::consent( $email, self::PROMOTIONS, 'nastaveni' );
		}
		return self::DONE;
	}

	/**
	 * „Neposílat nic“ ze stránky nastavení nebo odhlášení jedním kliknutím z pošty (List-Unsubscribe):
	 * odvolá všechny druhy včetně Žádosti o hodnocení a starého souhlasu.
	 *
	 * @return string DONE nebo INVALID_TOKEN
	 */
	public static function withdraw_everything( mixed $key, string $source ): string {
		$email = self::email_from_key( $key );
		if ( null === $email ) {
			return self::INVALID_TOKEN;
		}
		self::withdraw( $email, [ ...self::KINDS, self::LEGACY ], $source );
		return self::DONE;
	}

	/**
	 * Klíč stránky nastavení pro e‑mail odkazu z Připomínky odeslané před stránkou nastavení,
	 * null = neplatný odkaz.
	 */
	public static function settings_key_by_token( mixed $token ): ?string {
		$id = self::id_from_token( $token );
		if ( null === $id ) {
			return null;
		}
		global $wpdb;
		$email = $wpdb->get_var( $wpdb->prepare( 'SELECT email FROM %i WHERE id = %d', Pneukarnik_DB::subscriptions_table(), $id ) );
		return null === $email ? null : self::key( $id, (string) $email );
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
	 * Odkaz na stránku nastavení e‑mailů do Nabídek a připomínek (i do hlavičky List-Unsubscribe),
	 * prázdný pro e‑mail bez záznamu.
	 */
	public static function settings_url( string $email ): string {
		global $wpdb;
		$email = self::normalize( $email );
		$id    = $wpdb->get_var( $wpdb->prepare( 'SELECT MIN(id) FROM %i WHERE email = %s', Pneukarnik_DB::subscriptions_table(), $email ) );
		return null === $id ? '' : self::settings_url_for_key( self::key( (int) $id, $email ) );
	}

	public static function normalize( string $email ): string {
		return strtolower( trim( $email ) );
	}

	/**
	 * Odvolá druhy e‑mailu, i ty, ke kterým zatím nemá záznam. Dřívější odvolání zůstane, jak bylo.
	 *
	 * @param list<string> $purposes
	 */
	private static function withdraw( string $email, array $purposes, string $source ): void {
		$email = self::normalize( $email );
		if ( ! is_email( $email ) ) {
			return;
		}
		global $wpdb;
		$now = Pneukarnik_Clock::now()->format( 'Y-m-d H:i:s' );
		foreach ( $purposes as $purpose ) {
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

	private static function has_record( string $email ): bool {
		global $wpdb;
		return null !== $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM %i WHERE email = %s LIMIT 1', Pneukarnik_DB::subscriptions_table(), $email ) );
	}

	private static function settings_url_for_key( string $key ): string {
		return add_query_arg( 'k', $key, home_url( '/odhlaseni/' ) );
	}

	private static function key( int $id, string $email ): string {
		return $id . '.' . hash_hmac( 'sha256', 'settings|' . $id . '|' . $email, wp_salt( 'pneukarnik' ) );
	}

	/**
	 * E‑mail podle klíče stránky nastavení, null = neplatný nebo pozměněný klíč.
	 */
	private static function email_from_key( mixed $key ): ?string {
		if ( ! is_string( $key ) || ! preg_match( '/^([1-9][0-9]*)\.[0-9a-f]{64}$/', $key, $m ) ) {
			return null;
		}
		global $wpdb;
		$email = $wpdb->get_var( $wpdb->prepare( 'SELECT email FROM %i WHERE id = %d', Pneukarnik_DB::subscriptions_table(), (int) $m[1] ) );
		return null !== $email && hash_equals( self::key( (int) $m[1], (string) $email ), $key ) ? (string) $email : null;
	}

	private static function offer_token( int $booking_id, string $email ): string {
		return $booking_id . '.' . hash_hmac( 'sha256', 'offer|' . $booking_id . '|' . $email, wp_salt( 'pneukarnik' ) );
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
