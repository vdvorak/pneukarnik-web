<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Jediný zdroj „aktuálního času“ pluginu.
 * Vše se počítá v časové zóně Europe/Prague, nezávisle na nastavení WordPressu.
 * Testy nastavují „teď“ přes freeze(), v provozu se vždy bere systémový čas.
 */
final class Pneukarnik_Clock {

	public const TIMEZONE = 'Europe/Prague';

	private static ?\DateTimeImmutable $frozen = null;

	public static function timezone(): \DateTimeZone {
		return new \DateTimeZone( self::TIMEZONE );
	}

	public static function now(): \DateTimeImmutable {
		return ( self::$frozen ?? new \DateTimeImmutable( 'now' ) )->setTimezone( self::timezone() );
	}

	/** Dnešní půlnoc v Europe/Prague. */
	public static function today(): \DateTimeImmutable {
		return self::now()->setTime( 0, 0 );
	}

	/** Datum nebo datum a čas (např. „2027-03-01“, „2027-03-01 08:00:00“) jako místní čas v Europe/Prague. */
	public static function at( string $local ): \DateTimeImmutable {
		return new \DateTimeImmutable( $local, self::timezone() );
	}

	/** Pro testy: zastaví čas. Řetězec se čte jako místní čas v Europe/Prague. */
	/** Nejbližší celá hodina $hour místního času v budoucnu (dnes, nebo zítra), např. pro plánované úlohy. */
	public static function next_at( int $hour ): \DateTimeImmutable {
		$next = self::today()->setTime( $hour, 0 );
		return $next > self::now() ? $next : $next->modify( '+1 day' );
	}

	public static function freeze( \DateTimeImmutable|string $at ): void {
		self::$frozen = is_string( $at ) ? self::at( $at ) : $at;
	}

	public static function reset(): void {
		self::$frozen = null;
	}
}
