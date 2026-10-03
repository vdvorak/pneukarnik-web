<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Státní svátky ČR. Platí jako opakované celodenní Výjimky „zavřeno“, Provozovatel může
 * každý svátek jednotlivě vypnout. Výjimka zadaná Provozovatelem má před svátkem přednost.
 */
final class Pneukarnik_Holidays {

	private const DISABLED_OPTION = 'pneukarnik_disabled_holidays';

	/** Pevné svátky: klíč => měsíc-den. */
	private const FIXED = [
		'new_year'         => '01-01',
		'labour_day'       => '05-01',
		'victory_day'      => '05-08',
		'cyril_methodius'  => '07-05',
		'jan_hus'          => '07-06',
		'statehood_day'    => '09-28',
		'independence_day' => '10-28',
		'freedom_day'      => '11-17',
		'christmas_eve'    => '12-24',
		'christmas_day'    => '12-25',
		'st_stephen'       => '12-26',
	];

	/**
	 * Všechny svátky v pořadí roku.
	 *
	 * @return array<string, string> klíč => název
	 */
	public static function names(): array {
		return [
			'new_year'         => __( 'Nový rok, Den obnovy samostatného českého státu', 'pneukarnik-booking' ),
			'good_friday'      => __( 'Velký pátek', 'pneukarnik-booking' ),
			'easter_monday'    => __( 'Velikonoční pondělí', 'pneukarnik-booking' ),
			'labour_day'       => __( 'Svátek práce', 'pneukarnik-booking' ),
			'victory_day'      => __( 'Den vítězství', 'pneukarnik-booking' ),
			'cyril_methodius'  => __( 'Den slovanských věrozvěstů Cyrila a Metoděje', 'pneukarnik-booking' ),
			'jan_hus'          => __( 'Den upálení mistra Jana Husa', 'pneukarnik-booking' ),
			'statehood_day'    => __( 'Den české státnosti', 'pneukarnik-booking' ),
			'independence_day' => __( 'Den vzniku samostatného československého státu', 'pneukarnik-booking' ),
			'freedom_day'      => __( 'Den boje za svobodu a demokracii', 'pneukarnik-booking' ),
			'christmas_eve'    => __( 'Štědrý den', 'pneukarnik-booking' ),
			'christmas_day'    => __( '1. svátek vánoční', 'pneukarnik-booking' ),
			'st_stephen'       => __( '2. svátek vánoční', 'pneukarnik-booking' ),
		];
	}

	/**
	 * Data svátků v roce, včetně vypnutých.
	 *
	 * @return array<string, string> klíč => YYYY-MM-DD, v pořadí roku
	 */
	public static function dates_in_year( int $year ): array {
		$easter = self::easter_sunday( $year );
		$dates  = [
			'good_friday'   => $easter->modify( '-2 days' )->format( 'Y-m-d' ),
			'easter_monday' => $easter->modify( '+1 day' )->format( 'Y-m-d' ),
		];
		foreach ( self::FIXED as $key => $month_day ) {
			$dates[ $key ] = $year . '-' . $month_day;
		}
		asort( $dates );
		return $dates;
	}

	/**
	 * Zapnutý svátek v daný den, nebo null.
	 *
	 * @return array{key:string,name:string}|null
	 */
	public static function on( string $date ): ?array {
		$key = array_search( $date, self::dates_in_year( (int) substr( $date, 0, 4 ) ), true );
		if ( false === $key || in_array( $key, self::disabled(), true ) ) {
			return null;
		}
		return [
			'key'  => $key,
			'name' => self::names()[ $key ],
		];
	}

	/**
	 * @return list<string> Klíče svátků, které Provozovatel vypnul.
	 */
	public static function disabled(): array {
		$value = get_option( self::DISABLED_OPTION, [] );
		return is_array( $value ) ? array_values( array_filter( $value, 'is_string' ) ) : [];
	}

	/**
	 * @param list<string> $keys Klíče svátků, které neplatí. Neznámé klíče se zahodí.
	 */
	public static function save_disabled( array $keys ): void {
		update_option( self::DISABLED_OPTION, array_values( array_intersect( array_keys( self::names() ), $keys ) ) );
	}

	/**
	 * Velikonoční neděle podle gregoriánského kalendáře (anonymní algoritmus, Meeus/Jones/Butcher).
	 */
	private static function easter_sunday( int $year ): DateTimeImmutable {
		$a     = $year % 19;
		$b     = intdiv( $year, 100 );
		$c     = $year % 100;
		$d     = intdiv( $b, 4 );
		$e     = $b % 4;
		$f     = intdiv( $b + 8, 25 );
		$g     = intdiv( $b - $f + 1, 3 );
		$h     = ( 19 * $a + $b - $d - $g + 15 ) % 30;
		$i     = intdiv( $c, 4 );
		$k     = $c % 4;
		$l     = ( 32 + 2 * $e + 2 * $i - $h - $k ) % 7;
		$m     = intdiv( $a + 11 * $h + 22 * $l, 451 );
		$month = intdiv( $h + $l - 7 * $m + 114, 31 );
		$day   = ( ( $h + $l - 7 * $m + 114 ) % 31 ) + 1;
		return Pneukarnik_Clock::at( sprintf( '%04d-%02d-%02d', $year, $month, $day ) );
	}
}
