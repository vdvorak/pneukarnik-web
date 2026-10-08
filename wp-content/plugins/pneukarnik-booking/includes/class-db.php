<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Pneukarnik_DB {

	private const DB_VERSION_OPTION = 'pneukarnik_db_version';
	private const DB_VERSION        = '1.17';

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

		// 1.12 → 1.13: Nabídky a připomínky (ADR 0003), source je zdroj souhlasu a vedle něj přibude zdroj
		// odmítnutí. Přejmenovat před dbDelta, ta by jinak přidala nový sloupec vedle starého.
		if ( $installed && version_compare( (string) $installed, '1.13', '<' ) ) {
			self::rename_subscription_source();
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

		// 1.5 → 1.6: Výjimky s rozsahem a opakováním místo jednotlivých uzavřených dnů.
		if ( $installed && version_compare( (string) $installed, '1.6', '<' ) ) {
			self::move_closed_dates_to_day_exceptions();
		}

		// 1.6 → 1.7: jedna Sezóna podle dnešního data nahrazena jarní a podzimní podle data Termínu.
		// Starý rozsah znamenal něco jiného, Provozovatel Sezóny nastaví znovu.
		if ( $installed && version_compare( (string) $installed, '1.7', '<' ) ) {
			delete_option( 'pneukarnik_season_from' );
			delete_option( 'pneukarnik_season_to' );
			delete_option( 'pneukarnik_season_forced' );
		}

		// 1.7 → 1.8: odkaz pro Zrušení platí do Termínu (počítá se z něj), Lhůta zrušení je v hodinách.
		if ( $installed && version_compare( (string) $installed, '1.8', '<' ) ) {
			global $wpdb;
			$table = self::bookings_table();
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', $table, 'cancel_token_expires_at' ) ) ) {
				$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i DROP COLUMN cancel_token_expires_at', $table ) );
			}
			$days = get_option( 'pneukarnik_cancellation_days' );
			if ( false !== $days ) {
				add_option( 'pneukarnik_cancellation_hours', 24 * max( 0, (int) $days ) );
				delete_option( 'pneukarnik_cancellation_days' );
			}
		}

		// 1.8 → 1.9: zdroj Rezervace zadané v administraci se jmenuje podle slovníku „provozovatel“.
		if ( $installed && version_compare( (string) $installed, '1.9', '<' ) ) {
			global $wpdb;
			$wpdb->query( $wpdb->prepare( 'UPDATE %i SET source = %s WHERE source = %s', self::bookings_table(), Pneukarnik_Booking::SOURCE_PROVOZOVATEL, 'admin' ) );
		}

		// 1.9 → 1.10: sociální sítě místo ručního JSONu v polích Kontaktu, jedno na síť.
		if ( $installed && version_compare( (string) $installed, '1.10', '<' ) ) {
			self::move_social_links_to_contact();
		}

		// 1.12 → 1.13: odvolání dřívějších souhlasů s Připomínkou přezutí bylo odkazem, starého odběru
		// starým odkazem. Souhlasy s Připomínkou zůstanou výslovnými souhlasy (jen vývojová data).
		if ( $installed && version_compare( (string) $installed, '1.13', '<' ) ) {
			global $wpdb;
			$wpdb->query(
				$wpdb->prepare(
					'UPDATE %i SET
					   consent_source = IF(consented_at IS NULL, NULL, consent_source),
					   withdrawn_source = IF(withdrawn_at IS NULL, NULL, IF(purpose = %s, %s, %s))',
					self::subscriptions_table(),
					Pneukarnik_Subscriptions::LEGACY,
					'stary-odkaz',
					'odkaz'
				)
			);
		}

		// 1.16 → 1.17: Nabídky a připomínky jen s výslovným souhlasem, bez Žádosti o hodnocení (ADR 0004).
		if ( $installed && version_compare( (string) $installed, '1.17', '<' ) ) {
			self::drop_soft_opt_in();
		}

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
		pneukarnik_ensure_capabilities();
	}

	/**
	 * Nároky z online Rezervace souhlasem nejsou a smažou se, s nimi i odmítnutí ve formuláři bez
	 * dřívějšího souhlasu. Odvolání odkazem zůstanou (starý odkaz brání převodu starých souhlasů).
	 * Žádost o hodnocení zmizí i s plánovanou úlohou a nastavením.
	 */
	private static function drop_soft_opt_in(): void {
		global $wpdb;
		$table = self::subscriptions_table();
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE purpose = %s OR (consented_at IS NULL AND (withdrawn_at IS NULL OR withdrawn_source = %s))',
				$table,
				'review',
				'rezervace'
			)
		);
		foreach ( [ 'claimed_at', 'visited_at', 'sent_at' ] as $column ) {
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', $table, $column ) ) ) {
				$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i DROP COLUMN %i', $table, $column ) );
			}
		}
		wp_clear_scheduled_hook( 'pneukarnik_review_request_send' );
		delete_option( 'pneukarnik_review_request_enabled' );
		delete_option( 'pneukarnik_review_request_intro' );
	}

	private static function rename_subscription_source(): void {
		global $wpdb;
		$table = self::subscriptions_table();
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', $table, 'source' ) ) ) {
			$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i CHANGE COLUMN source consent_source VARCHAR(20) DEFAULT NULL', $table ) );
		}
	}

	private static function move_social_links_to_contact(): void {
		$links = json_decode( (string) get_option( 'pneukarnik_social_links', '[]' ), true );
		foreach ( is_array( $links ) ? $links : [] as $link ) {
			$network = is_array( $link ) ? strtolower( (string) ( $link['platform'] ?? '' ) ) : '';
			if ( isset( Pneukarnik_Contact::social_networks()[ $network ] ) ) {
				add_option( Pneukarnik_Contact::OPTION_SOCIAL_PREFIX . $network, esc_url_raw( (string) ( $link['url'] ?? '' ), [ 'http', 'https' ] ) );
			}
		}
		delete_option( 'pneukarnik_social_links' );
	}

	private static function move_closed_dates_to_day_exceptions(): void {
		global $wpdb;
		$closed_dates = $wpdb->prefix . 'pneukarnik_closed_dates';
		if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $closed_dates ) ) ) {
			return;
		}
		$wpdb->query(
			$wpdb->prepare(
				'INSERT INTO %i (date_from, date_to, yearly, hours, note, created_at)
				 SELECT date, date, 0, IF(is_fully_closed = 1, NULL, custom_hours), note, created_at FROM %i ORDER BY id',
				self::day_exceptions_table(),
				$closed_dates
			)
		);
		$wpdb->query( $wpdb->prepare( 'DROP TABLE %i', $closed_dates ) );
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

		// legacy_key_hash = SHA-256 klíče Rezervace ze starého webu (Pneukarnik_Legacy_Import), jen u převedených.
		$bookings = "CREATE TABLE {$wpdb->prefix}pneukarnik_bookings (
			id                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			customer_name           VARCHAR(255) NOT NULL,
			customer_company        VARCHAR(255) DEFAULT NULL,
			customer_plate          VARCHAR(20)  NOT NULL,
			customer_email          VARCHAR(255) NOT NULL,
			customer_phone          VARCHAR(50)  NOT NULL,
			customer_note           TEXT         DEFAULT NULL,
			vehicle                 VARCHAR(100) DEFAULT NULL,
			leasing                 TINYINT(1)   NOT NULL DEFAULT 0,
			leasing_company         VARCHAR(255) DEFAULT NULL,
			stored_wheels           TINYINT(1)   NOT NULL DEFAULT 0,
			booking_date            DATE         NOT NULL,
			time_start              TIME         NOT NULL,
			time_end                TIME         NOT NULL,
			status                  ENUM('CONFIRMED','CANCELLED') NOT NULL DEFAULT 'CONFIRMED',
			cancel_token_hash       VARCHAR(64)  DEFAULT NULL,
			cancelled_at            DATETIME     DEFAULT NULL,
			cancel_reason           VARCHAR(255) DEFAULT NULL,
			confirm_token_hash      CHAR(64)     DEFAULT NULL,
			consent_gdpr_at         DATETIME     DEFAULT NULL,
			source                  VARCHAR(20)  NOT NULL DEFAULT 'web',
			reminder_sent           TINYINT(1)   NOT NULL DEFAULT 0,
			legacy_key_hash         CHAR(64)     DEFAULT NULL,
			created_at              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY uq_legacy_key (legacy_key_hash),
			KEY idx_email (customer_email),
			KEY idx_date (booking_date),
			KEY idx_confirm_token (confirm_token_hash),
			KEY idx_cancel_token (cancel_token_hash),
			KEY idx_status (status)
		) ENGINE=InnoDB $charset_collate;";

		// Výjimky. hours NULL = zavřeno, jinak JSON se 1–2 bloky {from, to}.
		// yearly = opakovat každý rok podle dne a měsíce date_from–date_to.
		$day_exceptions = "CREATE TABLE {$wpdb->prefix}pneukarnik_day_exceptions (
			id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			date_from  DATE            NOT NULL,
			date_to    DATE            NOT NULL,
			yearly     TINYINT(1)      NOT NULL DEFAULT 0,
			hours      JSON            DEFAULT NULL,
			note       VARCHAR(255)    DEFAULT NULL,
			created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_range (yearly, date_from, date_to)
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

		// Souhlas e‑mailu s Nabídkami a připomínkami, jeden řádek na e‑mail a druh (Pneukarnik_Subscriptions):
		// souhlas a odvolání s časem a zdrojem. last_season = Sezóna poslední odeslané Připomínky (např. 2027-spring).
		$subscriptions = "CREATE TABLE {$wpdb->prefix}pneukarnik_subscriptions (
			id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			email            VARCHAR(255)    NOT NULL,
			purpose          VARCHAR(20)     NOT NULL,
			consent_source   VARCHAR(20)     DEFAULT NULL,
			consented_at     DATETIME        DEFAULT NULL,
			withdrawn_at     DATETIME        DEFAULT NULL,
			withdrawn_source VARCHAR(20)     DEFAULT NULL,
			last_season      VARCHAR(20)     DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uq_email_purpose (email, purpose),
			KEY idx_purpose (purpose, withdrawn_at)
		) ENGINE=InnoDB $charset_collate;";

		// Rozesílky (Pneukarnik_Mailing): úvodní věta, vybrané Akce (ID oddělená čárkou), Kategorie příjemců
		// (prázdná = všichni), plánovaný čas odeslání (místní) a stav odesílání.
		$mailings = "CREATE TABLE {$wpdb->prefix}pneukarnik_mailings (
			id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			intro         TEXT            NOT NULL,
			promotion_ids VARCHAR(255)    NOT NULL DEFAULT '',
			category      VARCHAR(20)     NOT NULL DEFAULT '',
			status        VARCHAR(20)     NOT NULL DEFAULT 'draft',
			test_sent_at  DATETIME        DEFAULT NULL,
			scheduled_at  DATETIME        DEFAULT NULL,
			created_at    DATETIME        NOT NULL,
			started_at    DATETIME        DEFAULT NULL,
			finished_at   DATETIME        DEFAULT NULL,
			sent_count    INT UNSIGNED    NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY idx_status (status)
		) ENGINE=InnoDB $charset_collate;";

		// Komu Rozesílka odešla, jeden řádek na e‑mail. Zapíše se před odesláním, aby nic neodešlo dvakrát.
		$mailing_recipients = "CREATE TABLE {$wpdb->prefix}pneukarnik_mailing_recipients (
			id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			mailing_id BIGINT UNSIGNED NOT NULL,
			email      VARCHAR(255)    NOT NULL,
			sent_at    DATETIME        NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uq_mailing_email (mailing_id, email),
			KEY idx_email (email)
		) ENGINE=InnoDB $charset_collate;";

		dbDelta( $bookings );
		dbDelta( $day_exceptions );
		dbDelta( $booking_services );
		dbDelta( $subscriptions );
		dbDelta( $mailings );
		dbDelta( $mailing_recipients );
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

	public static function subscriptions_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'pneukarnik_subscriptions';
	}

	public static function mailings_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'pneukarnik_mailings';
	}

	public static function mailing_recipients_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'pneukarnik_mailing_recipients';
	}

	public static function day_exceptions_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'pneukarnik_day_exceptions';
	}
}
