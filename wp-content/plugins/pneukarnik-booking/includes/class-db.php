<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Pneukarnik_DB {

	private const DB_VERSION_OPTION = 'pneukarnik_db_version';
	private const DB_VERSION        = '1.4';

	/** Testy běží uvnitř transakce WP test suite, transakce pluginu pak používají savepoint. */
	private static bool $savepoints = false;

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

		// 1.3 → 1.4: dílna má kapacitu 1, unikátní klíč po Službách už neplatí (dbDelta indexy nemaže).
		if ( $installed && version_compare( (string) $installed, '1.4', '<' ) ) {
			global $wpdb;
			$table = self::bookings_table();
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW INDEX FROM %i WHERE Key_name = %s', $table, 'uq_slot' ) ) ) {
				$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i DROP INDEX uq_slot', $table ) );
			}
		}

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
		pneukarnik_ensure_capabilities();
	}

	public static function use_savepoints( bool $enabled ): void {
		self::$savepoints = $enabled;
	}

	public static function begin(): void {
		global $wpdb;
		if ( self::$savepoints ) {
			$wpdb->query( 'SAVEPOINT pneukarnik' );
		} else {
			$wpdb->query( 'START TRANSACTION' );
		}
	}

	public static function commit(): void {
		global $wpdb;
		if ( self::$savepoints ) {
			$wpdb->query( 'RELEASE SAVEPOINT pneukarnik' );
		} else {
			$wpdb->query( 'COMMIT' );
		}
	}

	public static function rollback(): void {
		global $wpdb;
		if ( self::$savepoints ) {
			$wpdb->query( 'ROLLBACK TO SAVEPOINT pneukarnik' );
		} else {
			$wpdb->query( 'ROLLBACK' );
		}
	}

	/**
	 * Provede $callback se zámkem dílny na daný den (MySQL GET_LOCK), aby se dvě Rezervace
	 * téhož dne nevyhodnocovaly souběžně.
	 *
	 * @template T
	 * @param callable(): T $callback
	 * @return T|null null, když se zámek nepodařilo získat do 10 s.
	 */
	public static function with_day_lock( string $date, callable $callback ): mixed {
		global $wpdb;
		$name = $wpdb->prefix . 'pneukarnik_day_' . $date;
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 10)', $name ) ) ) {
			return null;
		}
		try {
			return $callback();
		} finally {
			$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) );
		}
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
			vehicle                 VARCHAR(100) DEFAULT NULL,
			booking_date            DATE         NOT NULL,
			time_start              TIME         NOT NULL,
			time_end                TIME         NOT NULL,
			status                  ENUM('CONFIRMED','CANCELLED') NOT NULL DEFAULT 'CONFIRMED',
			cancel_token_hash       VARCHAR(64)  DEFAULT NULL,
			cancel_token_expires_at DATETIME     DEFAULT NULL,
			cancelled_at            DATETIME     DEFAULT NULL,
			cancel_reason           VARCHAR(255) DEFAULT NULL,
			confirm_token_hash      CHAR(64)     DEFAULT NULL,
			consent_gdpr_at         DATETIME     DEFAULT NULL,
			source                  VARCHAR(20)  NOT NULL DEFAULT 'web',
			reminder_sent           TINYINT(1)   NOT NULL DEFAULT 0,
			created_at              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_email (customer_email),
			KEY idx_date (booking_date),
			KEY idx_confirm_token (confirm_token_hash),
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
