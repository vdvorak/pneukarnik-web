<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Výjimky: den nebo rozsah dní, kdy je zavřeno, nebo platí jiná Pracovní doba (0–2 bloky
 * jako běžný den). Opakovaná Výjimka platí každý rok podle dne a měsíce, i přes přelom roku.
 *
 * Přednost pro jeden den: jednorázová Výjimka, pak opakovaná (mezi stejnými novější),
 * pak zapnutý státní svátek (Pneukarnik_Holidays).
 */
final class Pneukarnik_Day_Exceptions {

	/**
	 * Výjimka, která pro den platí, nebo null (den se řídí Pracovní dobou dne v týdnu).
	 *
	 * @return array{hours:list<array{from:string,to:string}>|null,note:string}|null hours null = zavřeno
	 */
	public static function for_date( string $date ): ?array {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE (yearly = 0 AND date_from <= %s AND date_to >= %s) OR yearly = 1 ORDER BY yearly ASC, id DESC',
				Pneukarnik_DB::day_exceptions_table(),
				$date,
				$date
			),
			ARRAY_A
		);
		foreach ( $rows ?: [] as $row ) {
			$exception = self::hydrate( $row );
			if ( ! $exception['yearly'] || self::repeats_on( $exception, $date ) ) {
				return [
					'hours' => $exception['hours'],
					'note'  => $exception['note'],
				];
			}
		}

		$holiday = Pneukarnik_Holidays::on( $date );
		return null === $holiday ? null : [
			'hours' => null,
			'note'  => $holiday['name'],
		];
	}

	/**
	 * Uloží Výjimku.
	 *
	 * @param mixed $hours null = zavřeno, jinak 1–2 bloky {from, to} jako Pracovní doba.
	 * @return string|null Kód chyby (invalid_date, invalid_range, invalid_hours), null = uloženo.
	 */
	public static function add( string $date_from, string $date_to, bool $yearly, mixed $hours, ?string $note ): ?string {
		if ( ! self::is_date( $date_from ) || ! self::is_date( $date_to ) ) {
			return 'invalid_date';
		}
		if ( $date_to < $date_from || ( $yearly && $date_to >= Pneukarnik_Clock::at( $date_from )->modify( '+1 year' )->format( 'Y-m-d' ) ) ) {
			return 'invalid_range';
		}
		if ( null !== $hours ) {
			$hours = Pneukarnik_Working_Hours::normalize_blocks( $hours );
			if ( ! $hours ) {
				return 'invalid_hours';
			}
		}

		global $wpdb;
		$inserted = $wpdb->insert(
			Pneukarnik_DB::day_exceptions_table(),
			[
				'date_from' => $date_from,
				'date_to'   => $date_to,
				'yearly'    => $yearly ? 1 : 0,
				'hours'     => null === $hours ? null : wp_json_encode( $hours ),
				'note'      => null !== $note && '' !== trim( $note ) ? trim( $note ) : null,
			]
		);
		return $inserted ? null : 'internal_error';
	}

	public static function delete( int $id ): bool {
		global $wpdb;
		return (bool) $wpdb->delete( Pneukarnik_DB::day_exceptions_table(), [ 'id' => $id ] );
	}

	/**
	 * Výjimky pro výpis v administraci: opakované a jednorázové, které neskončily před $since.
	 *
	 * @return list<array{id:int,date_from:string,date_to:string,yearly:bool,hours:list<array{from:string,to:string}>|null,note:string}>
	 */
	public static function listed( string $since ): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE yearly = 1 OR date_to >= %s ORDER BY yearly ASC, date_from ASC, id ASC',
				Pneukarnik_DB::day_exceptions_table(),
				$since
			),
			ARRAY_A
		);
		return array_map( [ self::class, 'hydrate' ], $rows ?: [] );
	}

	/**
	 * @param array{date_from:string,date_to:string} $exception
	 */
	private static function repeats_on( array $exception, string $date ): bool {
		$day  = substr( $date, 5 );
		$from = substr( $exception['date_from'], 5 );
		$to   = substr( $exception['date_to'], 5 );
		return $from <= $to ? $day >= $from && $day <= $to : $day >= $from || $day <= $to;
	}

	private static function is_date( string $value ): bool {
		return (bool) preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) && checkdate( (int) $m[2], (int) $m[3], (int) $m[1] );
	}

	/**
	 * @param array<string,mixed> $row
	 * @return array{id:int,date_from:string,date_to:string,yearly:bool,hours:list<array{from:string,to:string}>|null,note:string}
	 */
	private static function hydrate( array $row ): array {
		$hours = null === $row['hours'] ? null : json_decode( (string) $row['hours'], true );
		return [
			'id'        => (int) $row['id'],
			'date_from' => (string) $row['date_from'],
			'date_to'   => (string) $row['date_to'],
			'yearly'    => (bool) $row['yearly'],
			'hours'     => is_array( $hours ) ? $hours : null,
			'note'      => (string) ( $row['note'] ?? '' ),
		];
	}
}
