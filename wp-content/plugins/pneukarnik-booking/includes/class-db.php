<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Pneukarnik_DB {

	private const DB_VERSION_OPTION = 'pneukarnik_db_version';
	private const DB_VERSION        = '1.5';

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

		// 1.4 → 1.5: Rezervace má 1..n Služeb ve vlastní tabulce. Cena dřívějších Rezervací není známá.
		if ( $installed && version_compare( (string) $installed, '1.5', '<' ) ) {
			self::move_service_to_booking_services();
		}

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
		pneukarnik_ensure_capabilities();
	}

	private static function move_service_to_booking_services(): void {
		global $wpdb;
		$bookings = self::bookings_table();
		if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', $bookings, 'service_id' ) ) ) {
			return;
		}
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO %i (booking_id, position, service_id, service_name, duration, price, price_from)
				 SELECT b.id, 0, b.service_id, COALESCE(p.post_title, ''), TIME_TO_SEC(TIMEDIFF(b.time_end, b.time_start)) DIV 60, NULL, 0
				 FROM %i b LEFT JOIN %i p ON p.ID = b.service_id
				 WHERE NOT EXISTS (SELECT 1 FROM %i s WHERE s.booking_id = b.id)",
				self::booking_services_table(),
				$bookings,
				$wpdb->posts,
				self::booking_services_table()
			)
		);
		$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i DROP COLUMN service_id', $bookings ) );
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

		// Služby Rezervace v pořadí, jak je Zákazník vybral. Název, Délka a cena platí k okamžiku vytvoření.
		// price NULL = cena dle vozu (nebo neznámá u Rezervací z doby před 1.5).
		$booking_services = "CREATE TABLE {$wpdb->prefix}pneukarnik_booking_services (
			id           BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
			booking_id   BIGINT UNSIGNED  NOT NULL,
			position     TINYINT UNSIGNED NOT NULL,
			service_id   BIGINT UNSIGNED  NOT NULL,
			service_name VARCHAR(255)     NOT NULL,
			duration     SMALLINT UNSIGNED NOT NULL,
			price        INT UNSIGNED     DEFAULT NULL,
			price_from   TINYINT(1)       NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY uq_booking_position (booking_id, position),
			KEY idx_service (service_id)
		) ENGINE=InnoDB $charset_collate;";

		dbDelta( $bookings );
		dbDelta( $closed_dates );
		dbDelta( $booking_services );
	}

	// Vrátí plný název tabulky rezervací
	public static function bookings_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'pneukarnik_bookings';
	}

	public static function booking_services_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'pneukarnik_booking_services';
	}

	// Vrátí plný název tabulky uzavřených termínů
	public static function closed_dates_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'pneukarnik_closed_dates';
	}
}
