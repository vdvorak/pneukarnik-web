<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Multi-phase working hours per day.
 * Stored as WP option pneukarnik_working_hours (JSON).
 * Format: {"mon": [{"from":"08:00","to":"12:00"},{"from":"13:00","to":"17:00"}], ...}
 * null = closed that day.
 */
class Pneukarnik_Working_Hours {

	private const OPTION_KEY = 'pneukarnik_working_hours';
	private const DAYS       = [ 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' ];

	/**
	 * @return array<string, list<array{from:string,to:string}>|null>
	 */
	public static function get_all(): array {
		$raw = get_option( self::OPTION_KEY, null );
		if ( ! $raw ) {
			return array_fill_keys( self::DAYS, null );
		}
		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			return array_fill_keys( self::DAYS, null );
		}
		return $decoded;
	}

	/**
	 * @return list<array{from:string,to:string}>|null  null = closed
	 */
	public static function get_for_date( \DateTimeImmutable $date ): ?array {
		$day_key = strtolower( $date->format( 'D' ) );
		$all     = self::get_all();
		return $all[ $day_key ] ?? null;
	}

	/**
	 * Efektivní Pracovní doba dne: Výjimka nebo svátek (zavřeno / vlastní bloky) s poznámkou,
	 * jinak Pracovní doba dne v týdnu. Podle ní se počítají Termíny i zobrazuje otevírací doba.
	 *
	 * @param string $date YYYY-MM-DD
	 * @return array{hours:list<array{from:string,to:string}>|null,note:string} hours null = zavřeno
	 */
	public static function effective( string $date ): array {
		$exception = Pneukarnik_Day_Exceptions::for_date( $date );
		return $exception ?? [
			'hours' => self::get_for_date( Pneukarnik_Clock::at( $date ) ),
			'note'  => '',
		];
	}

	/**
	 * Efektivní Pracovní doba na $days dní od dneška (dnešek první).
	 *
	 * @return list<array{date:string,hours:list<array{from:string,to:string}>|null,note:string}>
	 */
	public static function upcoming( int $days ): array {
		$upcoming = [];
		$day      = Pneukarnik_Clock::today();
		for ( $i = 0; $i < $days; $i++ ) {
			$date       = $day->modify( "+{$i} days" )->format( 'Y-m-d' );
			$upcoming[] = [ 'date' => $date ] + self::effective( $date );
		}
		return $upcoming;
	}

	/**
	 * Uloží Pracovní dobu: pro každý den 0–2 bloky „od–do“, které se nepřekrývají.
	 *
	 * @param array<string, list<array<string, string>>|null> $hours Neověřený vstup z administrace.
	 * @return bool false, když je některý blok neplatný (nic se neuloží).
	 */
	public static function save( array $hours ): bool {
		$validated = [];
		foreach ( self::DAYS as $day ) {
			$blocks = self::normalize_blocks( $hours[ $day ] ?? [] );
			if ( null === $blocks ) {
				return false;
			}
			$validated[ $day ] = $blocks ?: null;
		}
		update_option( self::OPTION_KEY, wp_json_encode( $validated ) );
		return true;
	}

	/**
	 * Bloky Pracovní doby jednoho dne seřazené podle začátku: 0–2 bloky „od–do“ (HH:MM),
	 * které se nepřekrývají. Stejná pravidla platí pro Výjimku s jinou Pracovní dobou.
	 *
	 * @return list<array{from:string,to:string}>|null null, když je vstup neplatný.
	 */
	public static function normalize_blocks( mixed $blocks ): ?array {
		if ( ! is_array( $blocks ) ) {
			return null;
		}
		$normalized = [];
		foreach ( $blocks as $block ) {
			$from = is_array( $block ) ? (string) ( $block['from'] ?? '' ) : '';
			$to   = is_array( $block ) ? (string) ( $block['to'] ?? '' ) : '';
			if ( ! self::is_valid_time( $from ) || ! self::is_valid_time( $to ) || $from >= $to ) {
				return null;
			}
			$normalized[] = [
				'from' => $from,
				'to'   => $to,
			];
		}
		usort( $normalized, static fn( array $a, array $b ): int => strcmp( $a['from'], $b['from'] ) );
		if ( count( $normalized ) > 2 || ( 2 === count( $normalized ) && $normalized[1]['from'] < $normalized[0]['to'] ) ) {
			return null;
		}
		return $normalized;
	}

	/** Krok mřížky Termínů v minutách, mřížka začíná na začátku každého bloku Pracovní doby. */
	public static function get_grid_step(): int {
		return max( 5, (int) get_option( 'pneukarnik_grid_step', 30 ) );
	}

	/** Minimální předstih pro dnešní Termíny v minutách. */
	public static function get_lead_minutes(): int {
		return max( 0, (int) get_option( 'pneukarnik_lead_minutes', 60 ) );
	}

	/** Kolik dní dopředu lze rezervovat (dnešek + horizont včetně). */
	public static function get_horizon_days(): int {
		return max( 0, (int) get_option( 'pneukarnik_horizon_days', 60 ) );
	}

	/** Lhůta zrušení: kolik hodin před Termínem nejpozději může Zákazník Rezervaci zrušit. */
	public static function get_cancellation_hours(): int {
		return max( 0, (int) get_option( 'pneukarnik_cancellation_hours', 24 ) );
	}

	private static function is_valid_time( string $time ): bool {
		return (bool) preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $time );
	}
}
