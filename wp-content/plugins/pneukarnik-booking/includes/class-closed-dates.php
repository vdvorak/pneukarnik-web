<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Closed dates management.
 * A date can be fully closed (no slots) or have custom_hours that override working_hours.
 */
class Pneukarnik_Closed_Dates {

	/**
	 * Get closed date entry for a specific date.
	 * Returns null if date is not in the closed_dates table (= normal working day).
	 *
	 * @return array{id:int,date:string,is_fully_closed:bool,custom_hours:list<array{from:string,to:string}>|null,note:string|null}|null
	 */
	public static function get_for_date( string $date ): ?array {
		global $wpdb;
		$table = Pneukarnik_DB::closed_dates_table();
		$row   = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE date = %s', $table, $date ),
			ARRAY_A
		);
		if ( ! $row ) {
			return null;
		}
		return self::hydrate( $row );
	}

	/**
	 * @return list<array{id:int,date:string,is_fully_closed:bool,custom_hours:list<array{from:string,to:string}>|null,note:string|null}>
	 */
	public static function get_upcoming( int $days = 365 ): array {
		global $wpdb;
		$table = Pneukarnik_DB::closed_dates_table();
		$from  = Pneukarnik_Clock::today()->format( 'Y-m-d' );
		$to    = Pneukarnik_Clock::today()->modify( "+{$days} days" )->format( 'Y-m-d' );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE date BETWEEN %s AND %s ORDER BY date ASC',
				$table,
				$from,
				$to
			),
			ARRAY_A
		);
		return array_map( [ self::class, 'hydrate' ], $rows ?: [] );
	}

	/**
	 * Get all entries from a given relative date (e.g. '-30 days') onwards.
	 *
	 * @return list<array{id:int,date:string,is_fully_closed:bool,custom_hours:list<array{from:string,to:string}>|null,note:string|null}>
	 */
	public static function get_all_from( string $relative_from = 'today' ): array {
		global $wpdb;
		$table = Pneukarnik_DB::closed_dates_table();
		$from  = Pneukarnik_Clock::today()->modify( $relative_from )->format( 'Y-m-d' );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE date >= %s ORDER BY date ASC',
				$table,
				$from
			),
			ARRAY_A
		);
		return array_map( [ self::class, 'hydrate' ], $rows ?: [] );
	}

	public static function upsert( string $date, bool $is_fully_closed, ?array $custom_hours, ?string $note ): bool {
		global $wpdb;
		$table = Pneukarnik_DB::closed_dates_table();

		$custom_hours_json = null;
		if ( ! $is_fully_closed && $custom_hours ) {
			$custom_hours_json = wp_json_encode( $custom_hours );
		}

		$existing = self::get_for_date( $date );
		if ( $existing ) {
			return (bool) $wpdb->update(
				$table,
				[
					'is_fully_closed' => $is_fully_closed ? 1 : 0,
					'custom_hours'    => $custom_hours_json,
					'note'            => $note,
				],
				[ 'date' => $date ]
			);
		}

		return (bool) $wpdb->insert(
			$table,
			[
				'date'            => $date,
				'is_fully_closed' => $is_fully_closed ? 1 : 0,
				'custom_hours'    => $custom_hours_json,
				'note'            => $note,
			]
		);
	}

	public static function delete( string $date ): bool {
		global $wpdb;
		$table = Pneukarnik_DB::closed_dates_table();
		return (bool) $wpdb->delete( $table, [ 'date' => $date ] );
	}

	private static function hydrate( array $row ): array {
		$custom_hours = null;
		if ( $row['custom_hours'] ) {
			$decoded      = json_decode( $row['custom_hours'], true );
			$custom_hours = is_array( $decoded ) ? $decoded : null;
		}
		return [
			'id'              => (int) $row['id'],
			'date'            => $row['date'],
			'is_fully_closed' => (bool) $row['is_fully_closed'],
			'custom_hours'    => $custom_hours,
			'note'            => $row['note'] ?: null,
		];
	}
}
