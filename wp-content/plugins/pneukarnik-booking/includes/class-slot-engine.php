<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Výpočet Termínů. Dílna má kapacitu 1.
 *
 * Termín pro úsek dané Délky se nabízí, když:
 * - leží na mřížce s krokem K od začátku bloku efektivní Pracovní doby dne,
 * - celý úsek [Termín, Termín + Délka) leží v jednom bloku,
 * - den není minulý ani za horizontem a dnes začíná až po „teď + předstih“,
 * - úsek nepřekrývá žádnou potvrzenou Rezervaci (jakékoli Služby).
 */
class Pneukarnik_Slot_Engine {

	/**
	 * Volné Termíny dne pro úsek dané Délky.
	 *
	 * @return list<array{time_start:string,time_end:string}>
	 */
	public static function free_termins( int $duration, string $date ): array {
		$offered = self::offered_intervals( $duration, $date );
		if ( ! $offered ) {
			return [];
		}
		$booked = self::confirmed_intervals( $date );
		$free   = [];
		foreach ( $offered as [ $start, $end ] ) {
			if ( ! self::overlaps_any( $start, $end, $booked ) ) {
				$free[] = [
					'time_start' => self::minutes_to_hhmm( $start ),
					'time_end'   => self::minutes_to_hhmm( $end ),
				];
			}
		}
		return $free;
	}

	/**
	 * Jestli je Termín v nabídce dne bez ohledu na obsazenost (mřížka, Pracovní doba, předstih, horizont).
	 */
	public static function is_offered( int $duration, string $date, string $time_start ): bool {
		$start = self::hhmm_to_minutes( $time_start );
		foreach ( self::offered_intervals( $duration, $date ) as [ $candidate ] ) {
			if ( $candidate === $start ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Jestli úsek dne překrývá potvrzenou Rezervaci (kromě $except_id, která se přesouvá).
	 */
	public static function overlaps_confirmed( string $date, string $time_start, string $time_end, int $except_id = 0 ): bool {
		return self::overlaps_any( self::hhmm_to_minutes( $time_start ), self::hhmm_to_minutes( $time_end ), self::confirmed_intervals( $date, $except_id ) );
	}

	/**
	 * Jestli celý úsek [začátek, konec) v minutách leží v jednom bloku efektivní Pracovní doby dne.
	 * Bez mřížky, předstihu a horizontu (zadání Provozovatelem).
	 */
	public static function within_working_hours( string $date, int $start, int $end ): bool {
		foreach ( self::resolve_effective_hours( $date ) ?? [] as $block ) {
			if ( $start >= self::hhmm_to_minutes( $block['from'] ) && $end <= self::hhmm_to_minutes( $block['to'] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Efektivní Pracovní doba dne: Výjimka nebo svátek (zavřeno / vlastní bloky), jinak Pracovní doba dne v týdnu.
	 *
	 * @return list<array{from:string,to:string}>|null null = zavřeno
	 */
	public static function resolve_effective_hours( string $date ): ?array {
		return Pneukarnik_Working_Hours::effective( $date )['hours'];
	}

	/**
	 * Dny měsíce, které mají pro úsek dané Délky alespoň jeden volný Termín.
	 *
	 * @param string $month YYYY-MM
	 * @return list<string> YYYY-MM-DD
	 */
	public static function days_with_free_termin( int $duration, string $month ): array {
		$days = [];
		$day  = Pneukarnik_Clock::at( $month . '-01' );
		$end  = $day->modify( 'first day of next month' );
		for ( ; $day < $end; $day = $day->modify( '+1 day' ) ) {
			$date = $day->format( 'Y-m-d' );
			if ( self::free_termins( $duration, $date ) ) {
				$days[] = $date;
			}
		}
		return $days;
	}

	/**
	 * Online nabídka: mřížka, předstih pro dnešek a horizont.
	 *
	 * @return list<array{0:int,1:int}> [začátek, konec] v minutách od půlnoci
	 */
	private static function offered_intervals( int $duration, string $date ): array {
		if ( $duration <= 0 ) {
			return [];
		}
		$today = Pneukarnik_Clock::today();
		$day   = Pneukarnik_Clock::at( $date );
		if ( $day < $today || $day > $today->modify( '+' . Pneukarnik_Working_Hours::get_horizon_days() . ' days' ) ) {
			return [];
		}

		$earliest = 0;
		if ( $day == $today ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- porovnání okamžiků DateTimeImmutable.
			$from_now = Pneukarnik_Clock::now()->modify( '+' . Pneukarnik_Working_Hours::get_lead_minutes() . ' minutes' );
			if ( $from_now->format( 'Y-m-d' ) !== $date ) {
				return [];
			}
			// Termín, který už v této minutě začal, se nenabízí (zaokrouhlení nahoru na minutu).
			$earliest = self::hhmm_to_minutes( $from_now->format( 'H:i' ) ) + ( '00' === $from_now->format( 's' ) ? 0 : 1 );
		}

		$step      = Pneukarnik_Working_Hours::get_grid_step();
		$intervals = [];
		foreach ( self::resolve_effective_hours( $date ) ?? [] as $block ) {
			$block_end = self::hhmm_to_minutes( $block['to'] );
			for ( $start = self::hhmm_to_minutes( $block['from'] ); $start + $duration <= $block_end; $start += $step ) {
				if ( $start >= $earliest ) {
					$intervals[] = [ $start, $start + $duration ];
				}
			}
		}
		return $intervals;
	}

	/**
	 * @return list<array{0:int,1:int}>
	 */
	private static function confirmed_intervals( string $date, int $except_id = 0 ): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT TIME_FORMAT(time_start, '%%H:%%i') AS s, TIME_FORMAT(time_end, '%%H:%%i') AS e FROM %i WHERE booking_date = %s AND status = 'CONFIRMED' AND id <> %d",
				Pneukarnik_DB::bookings_table(),
				$date,
				$except_id
			),
			ARRAY_A
		);
		return array_map(
			static fn( array $row ): array => [ self::hhmm_to_minutes( $row['s'] ), self::hhmm_to_minutes( $row['e'] ) ],
			$rows ?: []
		);
	}

	/**
	 * @param list<array{0:int,1:int}> $intervals
	 */
	private static function overlaps_any( int $start, int $end, array $intervals ): bool {
		foreach ( $intervals as [ $other_start, $other_end ] ) {
			if ( $start < $other_end && $other_start < $end ) {
				return true;
			}
		}
		return false;
	}

	public static function hhmm_to_minutes( string $hhmm ): int {
		[ $h, $m ] = array_map( 'intval', explode( ':', $hhmm ) + [ 0, 0 ] );
		return $h * 60 + $m;
	}

	public static function minutes_to_hhmm( int $minutes ): string {
		return sprintf( '%02d:%02d', intdiv( $minutes, 60 ), $minutes % 60 );
	}
}
