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
	 * @param array<string, list<array<string, string>>|null> $hours Neověřený vstup z administrace.
	 */
	public static function save( array $hours ): bool {
		$validated = [];
		foreach ( self::DAYS as $day ) {
			$day_hours = $hours[ $day ] ?? null;
			if ( $day_hours === null ) {
				$validated[ $day ] = null;
				continue;
			}
			$phases = [];
			foreach ( $day_hours as $phase ) {
				if ( ! self::is_valid_time( $phase['from'] ?? '' ) || ! self::is_valid_time( $phase['to'] ?? '' ) ) {
					return false;
				}
				$phases[] = [
					'from' => $phase['from'],
					'to'   => $phase['to'],
				];
			}
			$validated[ $day ] = $phases ?: null;
		}
		return update_option( self::OPTION_KEY, wp_json_encode( $validated ) );
	}

	public static function get_time_gap(): int {
		return (int) get_option( 'pneukarnik_time_gap', 0 );
	}

	public static function get_cancellation_days(): int {
		return (int) get_option( 'pneukarnik_cancellation_days', 1 );
	}

	private static function is_valid_time( string $time ): bool {
		return (bool) preg_match( '/^\d{2}:\d{2}$/', $time );
	}
}
