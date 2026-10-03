<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Osobní údaje v Rezervacích.
 *
 * Exportér a mazač pro nástroje WordPressu (Nástroje → Export / Smazání osobních údajů).
 *
 * Denní plánovaná úloha anonymizuje Rezervace 1 rok po Termínu (slib v Ochraně osobních
 * údajů): zmizí jméno, firma, telefon, e‑mail, SPZ, vůz, poznámka, leasingová společnost
 * i důvod Zrušení a přestanou platit odkazy na potvrzení, Zrušení a „Objednat znovu“.
 * Statistika zůstane: Služby, Termín, stav, zdroj, příznaky leasingu a uskladněných kol.
 */
class Pneukarnik_GDPR {

	/** Cron hook for automatic anonymisation. */
	public const CRON_HOOK = 'pneukarnik_gdpr_anonymise';

	/** E‑mail anonymizované Rezervace, podle něj ji pozná is_anonymised(). */
	private const ANONYMISED_EMAIL = 'anonymized@deleted.invalid';

	// ------------------------------------------------------------------
	// Bootstrap
	// ------------------------------------------------------------------

	public static function init(): void {
		add_filter( 'wp_privacy_personal_data_exporters', [ self::class, 'register_exporter' ] );
		add_filter( 'wp_privacy_personal_data_erasers', [ self::class, 'register_eraser' ] );
		add_action( self::CRON_HOOK, [ self::class, 'run_anonymise_old_bookings' ] );
		add_action( 'init', [ self::class, 'schedule' ] );
	}

	/**
	 * Denně ve 3:00, naplánuje se samo i u už aktivního pluginu.
	 */
	public static function schedule(): void {
		if ( wp_next_scheduled( self::CRON_HOOK ) ) {
			return;
		}
		wp_schedule_event( Pneukarnik_Clock::next_at( 3 )->getTimestamp(), 'daily', self::CRON_HOOK );
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

		// Souhlasy s e‑maily (Připomínka přezutí, starý odběr) se smažou celé.
		$subscriptions = 1 === $page ? Pneukarnik_Subscriptions::erase( $email_address ) : 0;

		// Anonymizované Rezervace už e‑mail nemají, další dávka proto začíná vždy od začátku.
		$per_page = 25;
		if ( self::ANONYMISED_EMAIL === strtolower( $email_address ) ) {
			return [
				'items_removed'  => $subscriptions,
				'items_retained' => 0,
				'messages'       => [],
				'done'           => true,
			];
		}
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT id FROM %i WHERE customer_email = %s ORDER BY id ASC LIMIT %d',
				$table,
				strtolower( $email_address ),
				$per_page
			)
		);

		return [
			'items_removed'  => $subscriptions + self::anonymise( $ids ),
			'items_retained' => 0,
			'messages'       => [],
			'done'           => count( $ids ) < $per_page,
		];
	}

	// ------------------------------------------------------------------
	// Automatic retention-based anonymisation (cron)
	// ------------------------------------------------------------------

	/**
	 * Jestli už má Rezervace osobní údaje nahrazené.
	 *
	 * @param array{customer_email:string} $booking
	 */
	public static function is_anonymised( array $booking ): bool {
		return self::ANONYMISED_EMAIL === $booking['customer_email'];
	}

	/**
	 * Anonymizuje Rezervace, od jejichž Termínu uplynul 1 rok. Opakované spuštění nic nemění.
	 */
	public static function run_anonymise_old_bookings(): void {
		global $wpdb;
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT id FROM %i
				 WHERE TIMESTAMP(booking_date, time_start) + INTERVAL 1 YEAR <= %s
				   AND customer_email <> %s
				 ORDER BY id ASC',
				Pneukarnik_DB::bookings_table(),
				Pneukarnik_Clock::now()->format( 'Y-m-d H:i:s' ),
				self::ANONYMISED_EMAIL
			)
		);
		self::anonymise( $ids );
	}

	/**
	 * Nahradí osobní údaje Rezervací a zneplatní jejich tokeny.
	 *
	 * @param array<int|string> $ids
	 * @return int Počet anonymizovaných Rezervací.
	 */
	private static function anonymise( array $ids ): int {
		global $wpdb;
		$count = 0;
		foreach ( $ids as $id ) {
			$updated = $wpdb->update(
				Pneukarnik_DB::bookings_table(),
				[
					'customer_name'      => __( '[anonymizováno]', 'pneukarnik-booking' ),
					'customer_company'   => null,
					'customer_plate'     => __( '[anonymizováno]', 'pneukarnik-booking' ),
					'customer_email'     => self::ANONYMISED_EMAIL,
					'customer_phone'     => '',
					'customer_note'      => null,
					'vehicle'            => null,
					'leasing_company'    => null,
					'cancel_reason'      => null,
					'cancel_token_hash'  => null,
					'confirm_token_hash' => null,
				],
				[ 'id' => (int) $id ]
			);
			if ( false !== $updated ) {
				++$count;
			}
		}
		return $count;
	}
}
