<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Slot engine — computes available time slots for a given service and date.
 *
 * Algorithm (7 steps from spec):
 * 1. Resolve effective hours (working_hours → override by closed_dates custom_hours → fully_closed = empty)
 * 2. Check if service is available on this date (seasonal filter)
 * 3. Generate candidate slots (duration + time_gap cadence within each phase)
 * 4. Filter out already-booked slots
 * 5. Filter out past slots (today only)
 * 6. Return list of {time_start, time_end, available}
 */
class Pneukarnik_Slot_Engine {

	/**
	 * @return list<array{time_start:string,time_end:string,available:bool}>
	 */
	public static function get_slots( int $service_id, string $date ): array {
		$service = self::get_service( $service_id );
		if ( ! $service ) {
			return [];
		}

		// Step 1: Effective hours for this date
		$effective_hours = self::resolve_effective_hours( $date );
		if ( $effective_hours === null ) {
			return []; // Fully closed
		}

		// Step 2: Seasonal filter — if season active and service is not seasonal, no slots
		if ( Pneukarnik_Season::is_active() && ! $service['is_seasonal'] ) {
			return [];
		}

		// Step 3: Generate candidates
		$duration = (int) $service['duration'];
		$gap      = Pneukarnik_Working_Hours::get_time_gap();
		$step     = $duration + $gap;

		$candidates = [];
		foreach ( $effective_hours as $phase ) {
			$phase_slots = self::generate_phase_slots( $phase['from'], $phase['to'], $duration, $step );
			$candidates  = array_merge( $candidates, $phase_slots );
		}

		if ( empty( $candidates ) ) {
			return [];
		}

		// Step 4: Booked slots for this service/date
		$booked = self::get_booked_starts( $service_id, $date );

		// Step 5: Past filter (only for today)
		$now      = Pneukarnik_Clock::now();
		$is_today = $date === $now->format( 'Y-m-d' );
		$now_hhmm = $now->format( 'H:i' );

		$slots = [];
		foreach ( $candidates as [ $start, $end ] ) {
			if ( $is_today && $start <= $now_hhmm ) {
				continue; // Past slot
			}
			$slots[] = [
				'time_start' => $start,
				'time_end'   => $end,
				'available'  => ! in_array( $start, $booked, true ),
			];
		}

		return $slots;
	}

	/**
	 * Effective hours = working_hours unless closed_dates overrides.
	 * @return list<array{from:string,to:string}>|null  null = fully closed
	 */
	public static function resolve_effective_hours( string $date ): ?array {
		$closed = Pneukarnik_Closed_Dates::get_for_date( $date );
		if ( $closed !== null ) {
			if ( $closed['is_fully_closed'] ) {
				return null;
			}
			return $closed['custom_hours'] ?? null;
		}

		$dt      = Pneukarnik_Clock::at( $date );
		$day_key = strtolower( $dt->format( 'D' ) );
		$all     = Pneukarnik_Working_Hours::get_all();
		return $all[ $day_key ] ?? null;
	}

	/**
	 * @return list<array{0:string,1:string}>  [time_start, time_end] pairs in HH:MM
	 */
	private static function generate_phase_slots( string $phase_from, string $phase_to, int $duration, int $step ): array {
		$start_min = self::hhmm_to_minutes( $phase_from );
		$end_min   = self::hhmm_to_minutes( $phase_to );
		$slots     = [];

		$cursor = $start_min;
		while ( $cursor + $duration <= $end_min ) {
			$slots[] = [
				self::minutes_to_hhmm( $cursor ),
				self::minutes_to_hhmm( $cursor + $duration ),
			];
			$cursor += $step;
		}

		return $slots;
	}

	/**
	 * @return list<string>  HH:MM starts of booked (confirmed) slots
	 */
	private static function get_booked_starts( int $service_id, string $date ): array {
		global $wpdb;
		$table = Pneukarnik_DB::bookings_table();
		$rows  = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT TIME_FORMAT(time_start,'%%H:%%i') FROM %i
				WHERE service_id = %d AND booking_date = %s AND status = 'CONFIRMED'",
				$table,
				$service_id,
				$date
			)
		);
		return $rows ?: [];
	}

	private static function get_service( int $service_id ): ?array {
		$post = get_post( $service_id );
		if ( ! $post || $post->post_type !== 'pneukarnik_service' || $post->post_status !== 'publish' ) {
			return null;
		}
		return [
			'duration'    => (int) get_post_meta( $service_id, '_service_duration', true ),
			'bookable'    => (bool) get_post_meta( $service_id, '_service_bookable', true ),
			'is_seasonal' => (bool) get_post_meta( $service_id, '_service_is_seasonal', true ),
		];
	}

	public static function hhmm_to_minutes( string $hhmm ): int {
		[ $h, $m ] = explode( ':', $hhmm );
		return (int) $h * 60 + (int) $m;
	}

	public static function minutes_to_hhmm( int $minutes ): string {
		return sprintf( '%02d:%02d', intdiv( $minutes, 60 ), $minutes % 60 );
	}
}
