<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Pneukarnik_DB {

	private const DB_VERSION_OPTION = 'pneukarnik_db_version';
	private const DB_VERSION        = '1.3';

	public static function activate(): void {
		self::create_tables();
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	public static function deactivate(): void {
		// Tabulky se nesmazávají — data zákazníků musí zůstat
	}

	public static function maybe_upgrade(): void {
		$installed = get_option( self::DB_VERSION_OPTION );

		if ( $installed === self::DB_VERSION ) {
			return;
		}

		// migrate 1.2 → 1.3: status UPPERCASE
		// dbDelta změní DDL ENUM definici; data je nutno přepsat explicitně.
		// Na čerstvé instalaci (bez aktivačního hooku, např. mu-plugin v testech) tabulka ještě není.
		if ( $installed && version_compare( (string) $installed, '1.3', '<' ) ) {
			global $wpdb;
			$wpdb->query(
				"UPDATE {$wpdb->prefix}pneukarnik_bookings SET status = UPPER(status)"
			);
		}

		self::create_tables();
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
		pneukarnik_ensure_capabilities();
	}

	private static function create_tables(): void {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$bookings = "CREATE TABLE {$wpdb->prefix}pneukarnik_bookings (
			id                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			service_id              BIGINT UNSIGNED NOT NULL,
			customer_name           VARCHAR(255) NOT NULL,
			customer_company        VARCHAR(255) DEFAULT NULL,
			customer_plate          VARCHAR(20)  NOT NULL,
			customer_email          VARCHAR(255) NOT NULL,
			customer_phone          VARCHAR(50)  NOT NULL,
			customer_note           TEXT         DEFAULT NULL,
			booking_date            DATE         NOT NULL,
			time_start              TIME         NOT NULL,
			time_end                TIME         NOT NULL,
			status                  ENUM('CONFIRMED','CANCELLED') NOT NULL DEFAULT 'CONFIRMED',
			cancel_token_hash       VARCHAR(64)  DEFAULT NULL,
			cancel_token_expires_at DATETIME     DEFAULT NULL,
			cancelled_at            DATETIME     DEFAULT NULL,
			cancel_reason           VARCHAR(255) DEFAULT NULL,
			reminder_sent           TINYINT(1)   NOT NULL DEFAULT 0,
			created_at              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY uq_slot (service_id, booking_date, time_start),
			KEY idx_email (customer_email),
			KEY idx_date (booking_date),
			KEY idx_status (status)
		) ENGINE=InnoDB $charset_collate;";

		$closed_dates = "CREATE TABLE {$wpdb->prefix}pneukarnik_closed_dates (
			id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			date            DATE            NOT NULL,
			is_fully_closed TINYINT(1)      NOT NULL DEFAULT 1,
			custom_hours    JSON            DEFAULT NULL,
			note            VARCHAR(255)    DEFAULT NULL,
			created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY uq_date (date)
		) ENGINE=InnoDB $charset_collate;";

		dbDelta( $bookings );
		dbDelta( $closed_dates );
	}

	// Vrátí plný název tabulky rezervací
	public static function bookings_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'pneukarnik_bookings';
	}

	// Vrátí plný název tabulky uzavřených termínů
	public static function closed_dates_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'pneukarnik_closed_dates';
	}
}
