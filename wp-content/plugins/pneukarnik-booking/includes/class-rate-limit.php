<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ochrana veřejné rezervace: kolikrát za hodinu smí jedna IP vytvořit Rezervaci a zkusit Zrušení.
 * Přihlášený Provozovatel limit nemá. Okno je pevná hodina od prvního započteného pokusu,
 * počítá se podle Pneukarnik_Clock. IPv6 se počítá po /64, kterou má jedna přípojka celou.
 * Za proxy vrátí skutečnou IP filtr `pneukarnik_client_ip` (výchozí REMOTE_ADDR).
 */
final class Pneukarnik_Rate_Limit {

	/** Vytvořené Rezervace (nepovedené pokusy se nepočítají, obsazenost prozradí i /slots). */
	public const CREATE = 'create';
	/** Každý pokus o Zrušení odkazem, i s neplatným tokenem. */
	public const CANCEL = 'cancel';

	public const MIN = 1;
	public const MAX = 100;

	private const OPTIONS = [
		self::CREATE => 'pneukarnik_rate_limit',
		self::CANCEL => 'pneukarnik_cancel_rate_limit',
	];

	private const DEFAULTS = [
		self::CREATE => 10,
		self::CANCEL => 10,
	];

	public static function limit( string $action ): int {
		return self::clamp( (int) get_option( self::OPTIONS[ $action ], self::DEFAULTS[ $action ] ) );
	}

	public static function save_limit( string $action, int $limit ): void {
		update_option( self::OPTIONS[ $action ], self::clamp( $limit ) );
	}

	/**
	 * Vyčerpala IP limit? Pokus se nezapočítá, to udělá hit().
	 */
	public static function exceeded( string $action ): bool {
		return ! is_user_logged_in() && self::count( $action ) >= self::limit( $action );
	}

	/**
	 * Započte jeden pokus IP.
	 */
	public static function hit( string $action ): void {
		if ( is_user_logged_in() ) {
			return;
		}
		$now    = Pneukarnik_Clock::now()->getTimestamp();
		$window = self::window( $action );
		if ( null === $window ) {
			$window = [
				'count' => 0,
				'until' => $now + HOUR_IN_SECONDS,
			];
		}
		++$window['count'];
		set_transient( self::key( $action ), $window, max( 1, $window['until'] - $now ) );
	}

	/**
	 * Započte pokus a řekne, jestli se ještě vešel do limitu.
	 */
	public static function attempt( string $action ): bool {
		if ( self::exceeded( $action ) ) {
			return false;
		}
		self::hit( $action );
		return true;
	}

	private static function count( string $action ): int {
		return self::window( $action )['count'] ?? 0;
	}

	/**
	 * @return array{count:int,until:int}|null Běžící okno, null když žádné není nebo už skončilo.
	 */
	private static function window( string $action ): ?array {
		$window = get_transient( self::key( $action ) );
		if ( ! is_array( $window ) || ! isset( $window['count'], $window['until'] ) || $window['until'] <= Pneukarnik_Clock::now()->getTimestamp() ) {
			return null;
		}
		return [
			'count' => (int) $window['count'],
			'until' => (int) $window['until'],
		];
	}

	private static function key( string $action ): string {
		return 'pnk_rl_' . $action . '_' . md5( self::client() );
	}

	/**
	 * Klient, kterého limit počítá: IPv4 celá, IPv6 jako /64.
	 */
	private static function client(): string {
		$ip     = (string) apply_filters( 'pneukarnik_client_ip', sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) ) );
		$packed = filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ? inet_pton( $ip ) : false;
		return false === $packed ? $ip : bin2hex( substr( $packed, 0, 8 ) ) . '::/64';
	}

	private static function clamp( int $limit ): int {
		return max( self::MIN, min( self::MAX, $limit ) );
	}
}
