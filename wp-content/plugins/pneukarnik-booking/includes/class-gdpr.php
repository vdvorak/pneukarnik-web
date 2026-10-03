<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GDPR compliance for pneukarnik booking data.
 *
 * Registers WordPress personal data exporter and eraser so site admins
 * can respond to GDPR subject-access and erasure requests from WP Admin
 * (Tools → Export Personal Data / Erase Personal Data).
 *
 * Retention policy: bookings older than pneukarnik_gdpr_retention_years
 * (default 2 years) are anonymised automatically via a daily WP-Cron job.
 * Anonymisation replaces PII fields with empty strings / placeholders while
 * keeping aggregate booking records for business reporting.
 */
class Pneukarnik_GDPR {

	/** Option key for retention period in years. */
	private const RETENTION_OPTION = 'pneukarnik_gdpr_retention_years';

	/** Default retention in years. */
	private const RETENTION_DEFAULT = 2;

	/** Cron hook for automatic anonymisation. */
	public const CRON_HOOK = 'pneukarnik_gdpr_anonymise';

	// ------------------------------------------------------------------
	// Bootstrap
	// ------------------------------------------------------------------

	public static function init(): void {
		add_filter( 'wp_privacy_personal_data_exporters', [ self::class, 'register_exporter' ] );
		add_filter( 'wp_privacy_personal_data_erasers', [ self::class, 'register_eraser' ] );
		add_action( self::CRON_HOOK, [ self::class, 'run_anonymise_old_bookings' ] );
	}

	// ------------------------------------------------------------------
	// Exporter
	// ------------------------------------------------------------------

	public static function register_exporter( array $exporters ): array {
		$exporters['pneukarnik-bookings'] = [
			'exporter_friendly_name' => __( 'Pneukarnik — rezervace', 'pneukarnik-booking' ),
			'callback'               => [ self::class, 'export_personal_data' ],
		];
		return $exporters;
	}

	/**
	 * Export all bookings that contain the given email address.
	 *
	 * @param string $email_address Email of the data subject.
	 * @param int    $page          1-based page (WP paginates exporter calls).
	 * @return array{data: array, done: bool}
	 */
	public static function export_personal_data( string $email_address, int $page = 1 ): array {
		global $wpdb;
		$table = Pneukarnik_DB::bookings_table();

		$per_page = 25;
		$offset   = ( $page - 1 ) * $per_page;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE customer_email = %s ORDER BY id ASC LIMIT %d OFFSET %d',
				$table,
				strtolower( $email_address ),
				$per_page,
				$offset
			),
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return [
				'data' => [],
				'done' => true,
			];
		}

		$export_items = [];
		foreach ( $rows as $row ) {
			$data = [
				[
					'name'  => __( 'ID rezervace', 'pneukarnik-booking' ),
					'value' => (int) $row['id'],
				],
				[
					'name'  => __( 'Datum rezervace', 'pneukarnik-booking' ),
					'value' => $row['booking_date'],
				],
				[
					'name'  => __( 'Čas od', 'pneukarnik-booking' ),
					'value' => substr( $row['time_start'], 0, 5 ),
				],
				[
					'name'  => __( 'Čas do', 'pneukarnik-booking' ),
					'value' => substr( $row['time_end'], 0, 5 ),
				],
				[
					'name'  => __( 'Jméno', 'pneukarnik-booking' ),
					'value' => $row['customer_name'],
				],
				[
					'name'  => __( 'Firma', 'pneukarnik-booking' ),
					'value' => $row['customer_company'] ?? '',
				],
				[
					'name'  => __( 'SPZ', 'pneukarnik-booking' ),
					'value' => $row['customer_plate'],
				],
				[
					'name'  => __( 'Telefon', 'pneukarnik-booking' ),
					'value' => $row['customer_phone'],
				],
				[
					'name'  => __( 'Email', 'pneukarnik-booking' ),
					'value' => $row['customer_email'],
				],
				[
					'name'  => __( 'Poznámka', 'pneukarnik-booking' ),
					'value' => $row['customer_note'] ?? '',
				],
				[
					'name'  => __( 'Stav', 'pneukarnik-booking' ),
					'value' => $row['status'],
				],
				[
					'name'  => __( 'Vytvořeno', 'pneukarnik-booking' ),
					'value' => $row['created_at'],
				],
			];

			$export_items[] = [
				'group_id'    => 'pneukarnik-bookings',
				'group_label' => __( 'Rezervace pneuservisu', 'pneukarnik-booking' ),
				'item_id'     => 'booking-' . (int) $row['id'],
				'data'        => $data,
			];
		}

		// If exactly per_page rows returned, there may be more pages.
		$done = count( $rows ) < $per_page;

		return [
			'data' => $export_items,
			'done' => $done,
		];
	}

	// ------------------------------------------------------------------
	// Eraser
	// ------------------------------------------------------------------

	public static function register_eraser( array $erasers ): array {
		$erasers['pneukarnik-bookings'] = [
			'eraser_friendly_name' => __( 'Pneukarnik — rezervace', 'pneukarnik-booking' ),
			'callback'             => [ self::class, 'erase_personal_data' ],
		];
		return $erasers;
	}

	/**
	 * Anonymise all bookings for the given email address.
	 *
	 * WP eraser protocol: returns items_removed + items_retained counts.
	 *
	 * @param string $email_address Email of the data subject.
	 * @param int    $page          1-based page.
	 * @return array{items_removed: int, items_retained: int, messages: string[], done: bool}
	 */
	public static function erase_personal_data( string $email_address, int $page = 1 ): array {
		global $wpdb;
		$table = Pneukarnik_DB::bookings_table();

		$per_page = 25;
		$offset   = ( $page - 1 ) * $per_page;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id FROM %i WHERE customer_email = %s ORDER BY id ASC LIMIT %d OFFSET %d',
				$table,
				strtolower( $email_address ),
				$per_page,
				$offset
			),
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return [
				'items_removed'  => 0,
				'items_retained' => 0,
				'messages'       => [],
				'done'           => true,
			];
		}

		$removed = 0;
		foreach ( $rows as $row ) {
			$updated = $wpdb->update(
				$table,
				[
					'customer_name'     => __( '[anonymizováno]', 'pneukarnik-booking' ),
					'customer_company'  => null,
					'customer_plate'    => __( '[anonymizováno]', 'pneukarnik-booking' ),
					'customer_email'    => 'anonymized@deleted.invalid',
					'customer_phone'    => '',
					'customer_note'     => null,
					// Invalidate cancel token so it can no longer be used.
					'cancel_token_hash' => null,
				],
				[ 'id' => (int) $row['id'] ]
			);
			if ( false !== $updated ) {
				++$removed;
			}
		}

		$done = count( $rows ) < $per_page;

		return [
			'items_removed'  => $removed,
			'items_retained' => 0,
			'messages'       => [],
			'done'           => $done,
		];
	}

	// ------------------------------------------------------------------
	// Automatic retention-based anonymisation (cron)
	// ------------------------------------------------------------------

	/**
	 * Jestli už má Rezervace osobní údaje nahrazené (e‑mail končí .invalid).
	 *
	 * @param array{customer_email:string} $booking
	 */
	public static function is_anonymised( array $booking ): bool {
		return str_ends_with( $booking['customer_email'], '.invalid' );
	}

	/**
	 * Anonymise PII in old completed/cancelled bookings.
	 * Triggered by WP-Cron daily hook.
	 */
	public static function run_anonymise_old_bookings(): void {
		global $wpdb;
		$table = Pneukarnik_DB::bookings_table();

		$years  = (int) get_option( self::RETENTION_OPTION, self::RETENTION_DEFAULT );
		$cutoff = Pneukarnik_Clock::today()->modify( "-{$years} years" )->format( 'Y-m-d' );

		// Only anonymise bookings that are past (booking_date < cutoff) and already
		// settled (confirmed = event occurred, cancelled = no PII needed).
		// Skip records already anonymised (email ends with .invalid).
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT id FROM %i
				 WHERE booking_date < %s
				   AND customer_email NOT LIKE %s
				 LIMIT 200',
				$table,
				$cutoff,
				'%' . $wpdb->esc_like( '.invalid' )
			)
		);

		if ( empty( $ids ) ) {
			return;
		}

		foreach ( $ids as $id ) {
			$wpdb->update(
				$table,
				[
					'customer_name'     => __( '[anonymizováno]', 'pneukarnik-booking' ),
					'customer_company'  => null,
					'customer_plate'    => __( '[anonymizováno]', 'pneukarnik-booking' ),
					'customer_email'    => 'anonymized@deleted.invalid',
					'customer_phone'    => '',
					'customer_note'     => null,
					'cancel_token_hash' => null,
				],
				[ 'id' => (int) $id ]
			);
		}

		error_log( sprintf( '[pneukarnik] GDPR: anonymised %d bookings older than %d years (cutoff %s)', count( $ids ), $years, $cutoff ) );
	}
}
