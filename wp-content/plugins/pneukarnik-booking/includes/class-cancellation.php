<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Booking cancellation.
 * Two paths:
 *  - Admin: no token required, no cancellation_days limit.
 *  - Customer: requires valid plain token (compared against SHA-256 hash in DB) + N-day check.
 */
class Pneukarnik_Cancellation {

	/**
	 * Cancel by admin (no token, no day limit).
	 * @return array{ok:true,booking:array}|array{ok:false,code:string,status:int}|WP_Error
	 */
	public static function cancel_by_admin( int $booking_id, ?string $reason ): array|WP_Error {
		$booking = Pneukarnik_Booking::get_by_id( $booking_id );
		if ( ! $booking ) {
			return [
				'ok'     => false,
				'code'   => 'cancellation.not_found',
				'status' => 404,
			];
		}
		if ( $booking['status'] === 'CANCELLED' ) {
			return [
				'ok'     => false,
				'code'   => 'cancellation.already_cancelled',
				'status' => 409,
			];
		}

		return self::execute_cancel( $booking_id, $reason );
	}

	/**
	 * Cancel by customer token.
	 * @return array{ok:true,booking:array}|array{ok:false,code:string,status:int}|WP_Error
	 */
	public static function cancel_by_token( int $booking_id, string $email, string $plain_token ): array|WP_Error {
		global $wpdb;
		$table = Pneukarnik_DB::bookings_table();

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE id = %d',
				$table,
				$booking_id
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return [
				'ok'     => false,
				'code'   => 'cancellation.not_found',
				'status' => 404,
			];
		}

		if ( $row['status'] === 'CANCELLED' ) {
			return [
				'ok'     => false,
				'code'   => 'cancellation.already_cancelled',
				'status' => 409,
			];
		}

		// Validate email
		if ( strtolower( $email ) !== strtolower( $row['customer_email'] ) ) {
			return [
				'ok'     => false,
				'code'   => 'cancellation.invalid_token',
				'status' => 403,
			];
		}

		// Validate token hash
		$token_hash = hash( 'sha256', $plain_token );
		if ( ! hash_equals( $row['cancel_token_hash'] ?? '', $token_hash ) ) {
			return [
				'ok'     => false,
				'code'   => 'cancellation.invalid_token',
				'status' => 403,
			];
		}

		// Token expiry
		if ( $row['cancel_token_expires_at'] && Pneukarnik_Clock::at( $row['cancel_token_expires_at'] ) < Pneukarnik_Clock::now() ) {
			return [
				'ok'     => false,
				'code'   => 'cancellation.invalid_token',
				'status' => 403,
			];
		}

		// N-day check
		$cancellation_days = Pneukarnik_Working_Hours::get_cancellation_days();
		$booking_date      = Pneukarnik_Clock::at( $row['booking_date'] );
		$today             = Pneukarnik_Clock::today();
		$diff              = (int) $today->diff( $booking_date )->days;

		if ( $diff < $cancellation_days ) {
			return [
				'ok'     => false,
				'code'   => 'cancellation.too_late',
				'status' => 422,
			];
		}

		return self::execute_cancel( $booking_id, null );
	}

	private static function execute_cancel( int $booking_id, ?string $reason ): array|WP_Error {
		global $wpdb;
		$table = Pneukarnik_DB::bookings_table();

		$updated = $wpdb->update(
			$table,
			[
				'status'            => 'CANCELLED',
				'cancelled_at'      => Pneukarnik_Clock::now()->format( 'Y-m-d H:i:s' ),
				'cancel_reason'     => $reason,
				'cancel_token_hash' => null, // Invalidate token after use
			],
			[ 'id' => $booking_id ]
		);

		if ( false === $updated ) {
			return new WP_Error( 'cancellation.db_error', 'Chyba při aktualizaci rezervace.', [ 'status' => 500 ] );
		}

		$booking = Pneukarnik_Booking::get_by_id( $booking_id );

		// Send cancellation confirmation email
		Pneukarnik_Notifications::on_booking_cancelled( $booking );

		return [
			'ok'      => true,
			'booking' => $booking,
		];
	}
}
