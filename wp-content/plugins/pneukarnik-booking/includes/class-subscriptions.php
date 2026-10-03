<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Souhlasy Zákazníků se zasíláním e‑mailů, jeden na e‑mail a účel:
 *   REMINDER  Připomínka přezutí, souhlas z rezervačního formuláře,
 *   LEGACY    „informace o slevách“ ze starého webu, jen s původním účelem (převod #21).
 *
 * Souhlas platí do odvolání, Rezervace ani jejich anonymizace ho nemění. Odhlašovací odkaz
 * /odhlaseni/?t={id}.{podpis} nese id souhlasu podepsané HMAC-SHA256 tajným klíčem webu spolu
 * s časem souhlasu, e‑mail v něm není. Po odvolání a novém souhlasu staré odkazy přestanou platit.
 * Starý odkaz /cancel-subscription?email=… odhlašuje jen LEGACY a zapamatuje si i e‑mail,
 * který zatím nezná, aby ho převod starých souhlasů (#21) znovu nepřihlásil: převod proto
 * existující záznam nikdy nepřepisuje.
 */
final class Pneukarnik_Subscriptions {

	public const REMINDER = 'reminder';
	public const LEGACY   = 'legacy';

	public const DONE          = 'unsubscribe.done';
	public const INVALID_TOKEN = 'unsubscribe.invalid_token';

	/**
	 * Zaznamená souhlas. Platný souhlas zůstane, jak byl (čas a zdroj prvního udělení),
	 * dřív odvolaný platí znovu od teď. Odeslané Připomínky se pamatují dál.
	 */
	public static function consent( string $email, string $purpose, string $source ): void {
		global $wpdb;
		$email = self::normalize( $email );
		if ( ! is_email( $email ) ) {
			return;
		}
		$wpdb->query(
			$wpdb->prepare(
				'INSERT INTO %i (email, purpose, source, consented_at) VALUES (%s, %s, %s, %s)
				 ON DUPLICATE KEY UPDATE
				   consented_at = IF(withdrawn_at IS NULL AND consented_at IS NOT NULL, consented_at, VALUES(consented_at)),
				   source = IF(withdrawn_at IS NULL, source, VALUES(source)),
				   withdrawn_at = NULL',
				Pneukarnik_DB::subscriptions_table(),
				$email,
				$purpose,
				$source,
				Pneukarnik_Clock::now()->format( 'Y-m-d H:i:s' )
			)
		);
	}

	public static function is_active( string $email, string $purpose ): bool {
		global $wpdb;
		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT 1 FROM %i WHERE email = %s AND purpose = %s AND withdrawn_at IS NULL',
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
				'UPDATE %i SET withdrawn_at = %s WHERE id = %d AND withdrawn_at IS NULL',
				Pneukarnik_DB::subscriptions_table(),
				Pneukarnik_Clock::now()->format( 'Y-m-d H:i:s' ),
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
					'INSERT INTO %i (email, purpose, source, withdrawn_at) VALUES (%s, %s, %s, %s)
					 ON DUPLICATE KEY UPDATE withdrawn_at = COALESCE(withdrawn_at, VALUES(withdrawn_at))',
					Pneukarnik_DB::subscriptions_table(),
					$email,
					self::LEGACY,
					'odhlaseni',
					Pneukarnik_Clock::now()->format( 'Y-m-d H:i:s' )
				)
			);
		}
		return self::DONE;
	}

	/**
	 * Smaže souhlasy e‑mailu (výmaz osobních údajů v nástrojích WordPressu).
	 */
	public static function erase( string $email ): int {
		global $wpdb;
		return (int) $wpdb->delete( Pneukarnik_DB::subscriptions_table(), [ 'email' => self::normalize( $email ) ] );
	}

	/**
	 * Odhlašovací odkaz pro platný souhlas, prázdný, když souhlas neexistuje.
	 */
	public static function unsubscribe_url( int $id ): string {
		$consented_at = self::consented_at( $id );
		return null === $consented_at ? '' : add_query_arg( 't', self::token( $id, $consented_at ), home_url( '/odhlaseni/' ) );
	}

	public static function normalize( string $email ): string {
		return strtolower( trim( $email ) );
	}

	private static function token( int $id, string $consented_at ): string {
		return $id . '.' . hash_hmac( 'sha256', 'unsubscribe|' . $id . '|' . $consented_at, wp_salt( 'pneukarnik' ) );
	}

	private static function id_from_token( mixed $token ): ?int {
		if ( ! is_string( $token ) || ! preg_match( '/^([1-9][0-9]*)\.[0-9a-f]{64}$/', $token, $m ) ) {
			return null;
		}
		$consented_at = self::consented_at( (int) $m[1] );
		return null !== $consented_at && hash_equals( self::token( (int) $m[1], $consented_at ), $token ) ? (int) $m[1] : null;
	}

	private static function consented_at( int $id ): ?string {
		global $wpdb;
		$consented_at = $wpdb->get_var( $wpdb->prepare( 'SELECT consented_at FROM %i WHERE id = %d', Pneukarnik_DB::subscriptions_table(), $id ) );
		return null === $consented_at ? null : (string) $consented_at;
	}
}
