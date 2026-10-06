<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Zápis kalendáře iCalendar (RFC 5545) pro feed Provozovatele i soubor .ics k Rezervaci
 * pro Zákazníka. Hodnoty typu TEXT escapuje volající přes text(), řádky zalomí calendar().
 */
final class Pneukarnik_Ical {

	/**
	 * Celý kalendář: vlastnosti VCALENDAR a události VEVENT v zadaném pořadí, konce řádků CRLF.
	 *
	 * @param array<string,string>       $properties Název => hodnota, už escapovaná.
	 * @param list<array<string,string>> $events     Vlastnosti každé události, už escapované.
	 */
	public static function calendar( array $properties, array $events ): string {
		$lines = [ 'BEGIN:VCALENDAR', ...self::lines( $properties ) ];
		foreach ( $events as $event ) {
			$lines = [ ...$lines, 'BEGIN:VEVENT', ...self::lines( $event ), 'END:VEVENT' ];
		}
		$lines[] = 'END:VCALENDAR';

		return implode( "\r\n", $lines ) . "\r\n";
	}

	/**
	 * Escapování hodnoty TEXT podle RFC 5545: zpětné lomítko, středník, čárka a konce řádků.
	 * Text od Zákazníka nebo Provozovatele tak nemůže přidat vlastní řádky ani události.
	 */
	public static function text( string $text ): string {
		$text = str_replace( [ '\\', ';', ',' ], [ '\\\\', '\\;', '\\,' ], $text );
		return str_replace( [ "\r\n", "\r", "\n" ], '\\n', $text );
	}

	/**
	 * Místní datum a čas v Europe/Prague (z DB) jako čas v UTC, např. 20270301T080000Z.
	 */
	public static function utc( string $date, string $time ): string {
		return Pneukarnik_Clock::at( $date . ' ' . $time )->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Ymd\THis\Z' );
	}

	/** Aktuální čas v UTC pro DTSTAMP. */
	public static function now(): string {
		return Pneukarnik_Clock::now()->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Ymd\THis\Z' );
	}

	/**
	 * @param array<string,string> $properties
	 * @return list<string>
	 */
	private static function lines( array $properties ): array {
		$lines = [];
		foreach ( $properties as $name => $value ) {
			$lines[] = self::fold( $name . ':' . $value );
		}
		return $lines;
	}

	/**
	 * Zalomení řádku podle RFC 5545: nejvýš 75 oktetů, pokračování CRLF + mezera.
	 * mb_strcut nerozdělí vícebajtový znak UTF-8 (čeština) mezi dva řádky.
	 */
	private static function fold( string $line ): string {
		$output = '';
		$length = strlen( $line );
		while ( $length > 75 ) {
			$chunk   = mb_strcut( $line, 0, 75, 'UTF-8' );
			$output .= $chunk . "\r\n ";
			$line    = substr( $line, strlen( $chunk ) );
			$length  = strlen( $line );
		}
		return $output . $line;
	}
}
