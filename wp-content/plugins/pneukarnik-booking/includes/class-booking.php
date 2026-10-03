<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Booking creation with race condition protection.
 * Uses MySQL transaction + SELECT FOR UPDATE.
 * UNIQUE KEY uq_slot provides hard guard if two transactions race.
 */
class Pneukarnik_Booking {

	/**
	 * Create a booking. Transactional, race-safe.
	 *
	 * @param array{
	 *   service_id: int,
	 *   booking_date: string,
	 *   time_start: string,
	 *   customer_name: string,
	 *   customer_company: string|null,
	 *   customer_plate: string,
	 *   customer_email: string,
	 *   customer_phone: string,
	 *   customer_note: string|null,
	 * } $data
	 * @return array{ok:true,booking:array}|array{ok:false,code:string,status:int,field?:string}
	 */
	public static function create( array $data ): array {
		global $wpdb;

		$validation = self::validate_create( $data );
		if ( $validation !== null ) {
			return $validation;
		}

		$service_id = (int) $data['service_id'];
		$date       = $data['booking_date'];
		$time_start = $data['time_start'];

		// Compute time_end from service duration
		$duration  = (int) get_post_meta( $service_id, '_service_duration', true );
		$start_min = Pneukarnik_Slot_Engine::hhmm_to_minutes( $time_start );
		$time_end  = Pneukarnik_Slot_Engine::minutes_to_hhmm( $start_min + $duration );

		// Cancel token — plain token in email only, hash in DB
		$plain_token = bin2hex( random_bytes( 32 ) );
		$token_hash  = hash( 'sha256', $plain_token );

		$cancel_days   = Pneukarnik_Working_Hours::get_cancellation_days();
		$booking_dt    = Pneukarnik_Clock::at( $date );
		$in_30_days    = Pneukarnik_Clock::now()->modify( '+30 days' );
		$token_expires = $booking_dt < $in_30_days
			? $booking_dt->format( 'Y-m-d 23:59:59' )
			: $in_30_days->format( 'Y-m-d H:i:s' );

		$table = Pneukarnik_DB::bookings_table();

		$wpdb->query( 'START TRANSACTION' );

		// Lock slot row — prevents race condition
		$locked = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM %i
				WHERE service_id = %d AND booking_date = %s AND time_start = %s
				FOR UPDATE',
				$table,
				$service_id,
				$date,
				$time_start
			)
		);

		if ( $locked !== null ) {
			$wpdb->query( 'ROLLBACK' );
			return [
				'ok'     => false,
				'code'   => 'booking.slot_taken',
				'status' => 409,
			];
		}

		$inserted = $wpdb->insert(
			$table,
			[
				'service_id'              => $service_id,
				'customer_name'           => $data['customer_name'],
				'customer_company'        => $data['customer_company'] ?? null,
				'customer_plate'          => strtoupper( $data['customer_plate'] ),
				'customer_email'          => strtolower( $data['customer_email'] ),
				'customer_phone'          => $data['customer_phone'],
				'customer_note'           => isset( $data['customer_note'] ) ? sanitize_text_field( $data['customer_note'] ) : null,
				'booking_date'            => $date,
				'time_start'              => $time_start,
				'time_end'                => $time_end,
				'status'                  => 'CONFIRMED',
				'cancel_token_hash'       => $token_hash,
				'cancel_token_expires_at' => $token_expires,
				'created_at'              => Pneukarnik_Clock::now()->format( 'Y-m-d H:i:s' ),
			]
		);

		if ( ! $inserted ) {
			$wpdb->query( 'ROLLBACK' );
			// Duplicate key = slot taken by concurrent request (UNIQUE KEY fallback)
			if ( $wpdb->last_error && str_contains( $wpdb->last_error, 'Duplicate entry' ) ) {
				return [
					'ok'     => false,
					'code'   => 'booking.slot_taken',
					'status' => 409,
				];
			}
			return [
				'ok'     => false,
				'code'   => 'booking.internal_error',
				'status' => 500,
			];
		}

		$booking_id = (int) $wpdb->insert_id;
		$wpdb->query( 'COMMIT' );

		$booking = self::get_by_id( $booking_id );

		// Invalidate slot cache for this service+date
		Pneukarnik_Rest_Slots::invalidate_for_service_date( $service_id, $date );

		// Send notifications async (after commit)
		Pneukarnik_Notifications::on_booking_created( $booking, $plain_token );

		return [
			'ok'      => true,
			'booking' => $booking,
		];
	}

	public static function get_by_id( int $id ): ?array {
		global $wpdb;
		$table = Pneukarnik_DB::bookings_table();
		$row   = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $table, $id ),
			ARRAY_A
		);
		return $row ? self::hydrate( $row ) : null;
	}

	/**
	 * @return array{ok:false,code:string,status:int,field?:string}|null  null = data jsou v pořádku
	 */
	private static function validate_create( array $data ): ?array {
		$required = [ 'service_id', 'booking_date', 'time_start', 'customer_name', 'customer_plate', 'customer_email', 'customer_phone' ];
		foreach ( $required as $field ) {
			if ( empty( $data[ $field ] ) ) {
				return [
					'ok'     => false,
					'code'   => 'validation.required',
					'status' => 422,
					'field'  => $field,
				];
			}
		}

		$service_id = (int) $data['service_id'];
		$post       = get_post( $service_id );
		if ( ! $post || $post->post_type !== 'pneukarnik_service' || $post->post_status !== 'publish' ) {
			return [
				'ok'     => false,
				'code'   => 'booking.service_not_found',
				'status' => 404,
			];
		}

		if ( ! get_post_meta( $service_id, '_service_bookable', true ) ) {
			return [
				'ok'     => false,
				'code'   => 'booking.service_not_bookable',
				'status' => 422,
			];
		}

		// Seasonal check
		if ( Pneukarnik_Season::is_active() && ! get_post_meta( $service_id, '_service_is_seasonal', true ) ) {
			return [
				'ok'     => false,
				'code'   => 'booking.seasonal_only',
				'status' => 422,
			];
		}

		// Date format
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $data['booking_date'] ) ) {
			return [
				'ok'     => false,
				'code'   => 'booking.invalid_date',
				'status' => 422,
			];
		}

		// Past date
		$date_dt = Pneukarnik_Clock::at( $data['booking_date'] );
		$today   = Pneukarnik_Clock::today();
		if ( $date_dt < $today ) {
			return [
				'ok'     => false,
				'code'   => 'booking.invalid_date',
				'status' => 422,
			];
		}

		// Closed date
		$closed = Pneukarnik_Closed_Dates::get_for_date( $data['booking_date'] );
		if ( $closed && $closed['is_fully_closed'] ) {
			return [
				'ok'     => false,
				'code'   => 'booking.closed_date',
				'status' => 422,
			];
		}

		// Time start format
		if ( ! preg_match( '/^\d{2}:\d{2}$/', $data['time_start'] ) ) {
			return [
				'ok'     => false,
				'code'   => 'validation.required',
				'status' => 422,
				'field'  => 'time_start',
			];
		}

		// Email
		if ( ! is_email( $data['customer_email'] ) ) {
			return [
				'ok'     => false,
				'code'   => 'validation.invalid_email',
				'status' => 422,
			];
		}

		// Max length checks
		if ( mb_strlen( (string) $data['customer_name'] ) > 120 ) {
			return [
				'ok'     => false,
				'code'   => 'validation.max_length',
				'status' => 422,
				'field'  => 'customer_name',
			];
		}
		if ( isset( $data['customer_company'] ) && mb_strlen( (string) $data['customer_company'] ) > 120 ) {
			return [
				'ok'     => false,
				'code'   => 'validation.max_length',
				'status' => 422,
				'field'  => 'customer_company',
			];
		}
		if ( mb_strlen( trim( (string) $data['customer_phone'] ) ) > 30 ) {
			return [
				'ok'     => false,
				'code'   => 'validation.max_length',
				'status' => 422,
				'field'  => 'customer_phone',
			];
		}

		// Phone — basic, non-empty
		if ( strlen( trim( $data['customer_phone'] ) ) < 9 ) {
			return [
				'ok'     => false,
				'code'   => 'validation.invalid_phone',
				'status' => 422,
			];
		}

		// SPZ — basic Czech plate
		if ( ! preg_match( '/^[A-Z0-9][A-Z0-9 ]{1,18}[A-Z0-9]$/i', $data['customer_plate'] ) ) {
			return [
				'ok'     => false,
				'code'   => 'validation.invalid_plate',
				'status' => 422,
			];
		}

		// Note — optional, max 500 chars
		if ( isset( $data['customer_note'] ) && mb_strlen( (string) $data['customer_note'] ) > 500 ) {
			return [
				'ok'     => false,
				'code'   => 'validation.max_length',
				'status' => 422,
				'field'  => 'customer_note',
			];
		}

		// Verify slot is actually available (avoids needless transaction race)
		$slots        = Pneukarnik_Slot_Engine::get_slots( $service_id, $data['booking_date'] );
		$slot_matches = array_values( array_filter( $slots, static fn( $s ) => $s['time_start'] === $data['time_start'] ) );
		if ( empty( $slot_matches ) ) {
			return [
				'ok'     => false,
				'code'   => 'booking.slot_unavailable',
				'status' => 422,
			];
		}
		if ( ! $slot_matches[0]['available'] ) {
			return [
				'ok'     => false,
				'code'   => 'booking.slot_taken',
				'status' => 409,
			];
		}

		return null;
	}

	private static function hydrate( array $row ): array {
		$service_name = get_the_title( (int) $row['service_id'] );
		return [
			'id'               => (int) $row['id'],
			'service_id'       => (int) $row['service_id'],
			'service_name'     => $service_name ?: '',
			'customer_name'    => $row['customer_name'],
			'customer_company' => $row['customer_company'],
			'customer_plate'   => $row['customer_plate'],
			'customer_email'   => $row['customer_email'],
			'customer_phone'   => $row['customer_phone'],
			'customer_note'    => $row['customer_note'],
			'booking_date'     => $row['booking_date'],
			'time_start'       => substr( $row['time_start'], 0, 5 ),
			'time_end'         => substr( $row['time_end'], 0, 5 ),
			'status'           => $row['status'],
			'created_at'       => $row['created_at'],
			'cancelled_at'     => $row['cancelled_at'] ?? null,
			'cancel_reason'    => $row['cancel_reason'] ?? null,
		];
	}
}
