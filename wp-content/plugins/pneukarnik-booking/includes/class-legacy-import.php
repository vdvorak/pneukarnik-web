<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Převod dat ze starého webu, který běží ve stejné databázi (docs/puvodni-web.md):
 *
 *   Služby      ze starého CPT `service` jako koncepty Služeb. Provozovatel je doplní a zveřejní.
 *               Převedená Služba si pamatuje ID staré (SERVICE_META), podle něj se mapují Rezervace.
 *               Službu bez té vazby se stejným slugem (jinak názvem), např. ze starého pokusu o nový
 *               web ve stejné databázi, převod převezme a doplní jí jen chybějící Kategorii, pořadí
 *               a „cena od“. Její stav, Délku a cenu nechá. Převedené Rezervace ale zabírají dílnu
 *               s Délkou staré Služby, jak si je Zákazník objednal.
 *   Rezervace   ze staré tabulky `{prefix}reservations`: budoucí a minulé, od jejichž Termínu
 *               neuplynul rok (starší by anonymizace hned smazala). Zrušené (deleted) ne.
 *               Starý klíč pro zrušení neplatí, Rezervace dostane nový token pro Zrušení
 *               a na přání Provozovatele e‑mail s odkazem (jen budoucí).
 *   Souhlasy    „informace o slevách“ (allow_newsletters) jen s původním účelem
 *               (Pneukarnik_Subscriptions::LEGACY), tedy pro Akce, ne pro Připomínku přezutí.
 *
 * Převod jde spouštět opakovaně: co už převedené je (Služba podle ID, Rezervace podle hashe
 * starého klíče, souhlas podle e‑mailu), se přeskočí a nikdy nepřepíše.
 *
 * @phpstan-type Section array{imported:int,skipped:int,failed:int,problems:list<string>}
 * @phpstan-type BookingSection array{imported:int,skipped:int,failed:int,problems:list<string>,emailed:int}
 * @phpstan-type Report array{services:Section,bookings:BookingSection,consents:Section}
 */
final class Pneukarnik_Legacy_Import {

	public const NO_SOURCE = 'legacy_import.no_source';

	/** Meta převedené Služby s ID Služby starého webu. */
	public const SERVICE_META = '_service_legacy_id';

	/** Typ příspěvku Služeb starého webu (plugin Pods). */
	private const OLD_SERVICE_TYPE = 'service';

	/** Stará tabulka Rezervací (bez prefixu). */
	private const OLD_TABLE = 'reservations';

	public static function old_table(): string {
		global $wpdb;
		return $wpdb->prefix . self::OLD_TABLE;
	}

	/** Jestli je stará tabulka Rezervací v databázi. */
	public static function available(): bool {
		global $wpdb;
		$suppress = $wpdb->suppress_errors();
		$result   = $wpdb->query( $wpdb->prepare( 'SELECT 1 FROM %i LIMIT 1', self::old_table() ) );
		$wpdb->suppress_errors( $suppress );
		return false !== $result;
	}

	/**
	 * Převede Služby, Rezervace a souhlasy.
	 *
	 * @param bool $send_cancel_links Poslat nově převedeným budoucím Rezervacím e‑mail s odkazem na Zrušení.
	 * @return array{ok:true,report:Report}|array{ok:false,code:string,status:int}
	 */
	public static function run( bool $send_cancel_links ): array {
		if ( ! self::available() ) {
			return [
				'ok'     => false,
				'code'   => self::NO_SOURCE,
				'status' => 404,
			];
		}
		[ $services, $service_map, $durations ] = self::import_services();
		return [
			'ok'     => true,
			'report' => [
				'services' => $services,
				'bookings' => self::import_bookings( $service_map, $durations, $send_cancel_links ),
				'consents' => self::import_consents(),
			],
		];
	}

	/**
	 * @return array{0:Section,1:array<int,int>,2:array<int,int>} Výsledek, mapa ID staré Služby => ID Služby
	 *         a Délka staré Služby (minuty) podle jejího ID.
	 */
	private static function import_services(): array {
		$section  = self::section();
		$map      = self::service_map();
		$unlinked = self::unlinked_services();

		$old_services = get_posts(
			[
				'post_type'   => self::OLD_SERVICE_TYPE,
				'post_status' => 'any',
				'numberposts' => -1,
				'orderby'     => 'ID',
				'order'       => 'ASC',
			]
		);
		$durations    = [];
		foreach ( $old_services as $old ) {
			$durations[ $old->ID ] = max( 0, (int) get_post_meta( $old->ID, 'duration', true ) );
			if ( isset( $map[ $old->ID ] ) ) {
				++$section['skipped'];
				continue;
			}
			$meta     = static fn( string $key ): string => trim( (string) get_post_meta( $old->ID, $key, true ) );
			$price    = (int) $meta( 'price' );
			$category = '1' === $meta( 'mechanical' ) ? Pneukarnik_Service::AUTOSERVIS : Pneukarnik_Service::PNEUSERVIS;
			$duration = max( 0, (int) $meta( 'duration' ) );
			// Starý web ukazoval cenu jako „Od … Kč“, nulovou nebo skrytou doplní Provozovatel.
			$shown = '1' === $meta( 'display_price' ) && $price > 0;

			$existing = self::take_matching( $unlinked, $old );
			if ( null !== $existing ) {
				$map[ $old->ID ] = $existing->ID;
				self::link( $existing, $old->ID, $category, (int) $meta( 'index' ), $shown );
				++$section['imported'];
				$current = (int) get_post_meta( $existing->ID, '_service_duration', true );
				if ( $current !== $duration ) {
					/* translators: 1: název Služby, 2: Délka v minutách, 3: Délka na starém webu */
					$section['problems'][] = sprintf( __( 'Služba „%1$s“: převzata existující, Délka %2$d min (na starém webu %3$d min). Zkontrolujte ji.', 'pneukarnik-booking' ), $existing->post_title, $current, $duration );
				}
				continue;
			}

			$id = wp_insert_post(
				[
					'post_type'   => Pneukarnik_Service::POST_TYPE,
					'post_status' => 'draft',
					'post_title'  => $old->post_title,
					'post_name'   => $old->post_name,
					'menu_order'  => (int) $meta( 'index' ),
					'meta_input'  => [
						'_service_category'    => $category,
						'_service_duration'    => $duration,
						'_service_price'       => $shown ? (string) $price : '',
						'_service_price_from'  => $shown ? '1' : '',
						'_service_bookable'    => '1' === $meta( 'reservable' ) ? '1' : '',
						'_service_is_seasonal' => '1' === $meta( 'seasonal' ) ? '1' : '',
						self::SERVICE_META     => $old->ID,
					],
				],
				true
			);
			if ( is_wp_error( $id ) ) {
				++$section['failed'];
				/* translators: 1: název staré Služby, 2: chyba */
				$section['problems'][] = sprintf( __( 'Služba „%1$s“: %2$s', 'pneukarnik-booking' ), $old->post_title, $id->get_error_message() );
				continue;
			}
			$map[ $old->ID ] = $id;
			++$section['imported'];
		}
		return [ $section, $map, $durations ];
	}

	/**
	 * Služby bez vazby na starý web, např. ze starého pokusu o nový web, který běžel ve stejné databázi.
	 *
	 * @return list<WP_Post>
	 */
	private static function unlinked_services(): array {
		return array_values(
			array_filter(
				get_posts(
					[
						'post_type'   => Pneukarnik_Service::POST_TYPE,
						'post_status' => 'any',
						'numberposts' => -1,
						'orderby'     => 'ID',
						'order'       => 'ASC',
					]
				),
				static fn( WP_Post $post ): bool => ! metadata_exists( 'post', $post->ID, self::SERVICE_META )
			)
		);
	}

	/**
	 * Vyjme ze seznamu Službu se stejným slugem jako stará Služba, jinak se stejným názvem.
	 *
	 * @param list<WP_Post> $unlinked
	 */
	private static function take_matching( array &$unlinked, WP_Post $old ): ?WP_Post {
		$title = static fn( string $t ): string => mb_strtolower( (string) preg_replace( '/\s+/u', ' ', trim( $t ) ) );
		foreach ( [
			static fn( WP_Post $post ): bool => $post->post_name === $old->post_name,
			static fn( WP_Post $post ): bool => $title( $post->post_title ) === $title( $old->post_title ),
		] as $matches ) {
			foreach ( $unlinked as $i => $post ) {
				if ( $matches( $post ) ) {
					array_splice( $unlinked, $i, 1 );
					return $post;
				}
			}
		}
		return null;
	}

	/**
	 * Naváže existující Službu na starou a doplní jen to, co v novém modelu chybí.
	 * Délku, cenu a ostatní pole nechá, jak jsou.
	 */
	private static function link( WP_Post $service, int $old_id, string $category, int $index, bool $price_from ): void {
		update_post_meta( $service->ID, self::SERVICE_META, $old_id );
		if ( ! isset( Pneukarnik_Service::categories()[ (string) get_post_meta( $service->ID, '_service_category', true ) ] ) ) {
			update_post_meta( $service->ID, '_service_category', $category );
		}
		if ( ! metadata_exists( 'post', $service->ID, '_service_price_from' ) ) {
			update_post_meta( $service->ID, '_service_price_from', $price_from ? '1' : '' );
		}
		if ( 0 === $service->menu_order && 0 !== $index ) {
			// Mimo wp_update_post: hlídání zveřejnění by neúplnou zveřejněnou Službu vrátilo do konceptu.
			global $wpdb;
			$wpdb->update( $wpdb->posts, [ 'menu_order' => $index ], [ 'ID' => $service->ID ] );
			clean_post_cache( $service->ID );
		}
	}

	/**
	 * Už převedené Služby (i v koši, aby se smazaná Služba nevrátila).
	 *
	 * @return array<int,int> ID staré Služby => ID Služby.
	 */
	private static function service_map(): array {
		$map = [];
		foreach ( get_posts(
			[
				'post_type'   => Pneukarnik_Service::POST_TYPE,
				'post_status' => [ 'any', 'trash' ],
				'numberposts' => -1,
				'meta_key'    => self::SERVICE_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'fields'      => 'ids',
			]
		) as $id ) {
			$map[ (int) get_post_meta( (int) $id, self::SERVICE_META, true ) ] = (int) $id;
		}
		return $map;
	}

	/**
	 * @param array<int,int> $service_map ID staré Služby => ID Služby.
	 * @param array<int,int> $durations   Délka staré Služby podle jejího ID.
	 * @return BookingSection
	 */
	private static function import_bookings( array $service_map, array $durations, bool $send_cancel_links ): array {
		global $wpdb;
		$section = self::section() + [ 'emailed' => 0 ];
		$now     = Pneukarnik_Clock::now();

		$imported = array_flip(
			$wpdb->get_col( $wpdb->prepare( 'SELECT legacy_key_hash FROM %i WHERE legacy_key_hash IS NOT NULL', Pneukarnik_DB::bookings_table() ) )
		);
		$rows     = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, name, spz, email, phone, serviceId, comment, date, time, created, deleted,
				        TIMESTAMP(date, time) + INTERVAL 1 YEAR <= %s AS expired
				 FROM %i ORDER BY date, time, created',
				$now->format( 'Y-m-d H:i:s' ),
				self::old_table()
			),
			ARRAY_A
		);

		foreach ( $rows ?: [] as $row ) {
			if ( '1' === (string) $row['deleted'] || '1' === (string) $row['expired'] || isset( $imported[ hash( 'sha256', (string) $row['id'] ) ] ) ) {
				++$section['skipped'];
				continue;
			}

			$name  = self::text( $row['name'] );
			$date  = (string) $row['date'];
			$time  = substr( (string) $row['time'], 0, 5 );
			$label = self::booking_label( $date, $time, $name );

			if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m ) || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
				++$section['failed'];
				/* translators: %s: Rezervace (Termín a jméno) */
				$section['problems'][] = sprintf( __( '%s: neplatné datum, nepřevedeno.', 'pneukarnik-booking' ), $label );
				continue;
			}

			$service_id = $service_map[ (int) $row['serviceId'] ] ?? 0;
			$service    = Pneukarnik_Service::find( $service_id );
			if ( null === $service || $service->duration <= 0 ) {
				++$section['failed'];
				/* translators: 1: Rezervace (Termín a jméno), 2: ID Služby starého webu */
				$section['problems'][] = sprintf( __( '%1$s: neznámá Služba %2$d, nepřevedeno.', 'pneukarnik-booking' ), $label, (int) $row['serviceId'] );
				continue;
			}

			$email  = strtolower( self::text( $row['email'] ) );
			$fields = [
				'legacy_key' => (string) $row['id'],
				'name'       => $name,
				'phone'      => self::text( $row['phone'] ),
				'email'      => is_email( $email ) ? $email : '',
				'plate'      => substr( strtoupper( (string) preg_replace( '/[\s\-]/', '', self::text( $row['spz'] ) ) ), 0, 20 ),
				'note'       => sanitize_textarea_field( html_entity_decode( (string) $row['comment'], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ),
				'date'       => $date,
				'time'       => $time,
				'created_at' => (string) $row['created'],
			];
			// Dílnu zabírá tak dlouho jako na starém webu, i když má převzatá Služba jinou Délku.
			$duration = ( $durations[ (int) $row['serviceId'] ] ?? 0 ) ?: $service->duration;
			$end      = Pneukarnik_Slot_Engine::minutes_to_hhmm( Pneukarnik_Slot_Engine::hhmm_to_minutes( $time ) + $duration );
			$future   = Pneukarnik_Clock::at( "{$date} {$time}" ) > $now;
			// U minulých Rezervací už překryv (chyba starého webu) nic neznamená.
			$overlaps = $future && Pneukarnik_Slot_Engine::overlaps_confirmed( $date, $time, $end );

			$result = Pneukarnik_Booking::insert_imported( $fields, $service, $duration );
			if ( null === $result ) {
				++$section['failed'];
				/* translators: %s: Rezervace (Termín a jméno) */
				$section['problems'][] = sprintf( __( '%s: zápis selhal, nepřevedeno.', 'pneukarnik-booking' ), $label );
				continue;
			}
			++$section['imported'];
			if ( $overlaps ) {
				/* translators: %s: Rezervace (Termín a jméno) */
				$section['problems'][] = sprintf( __( '%s: převedeno, ale překrývá se s jinou Rezervací. Domluvte se se Zákazníkem.', 'pneukarnik-booking' ), $label );
			}

			$booking = Pneukarnik_Booking::get_by_id( $result['id'] );
			if ( $send_cancel_links && $future && null !== $booking && Pneukarnik_Notifications::on_booking_imported( $booking, $result['cancel_token'] ) ) {
				++$section['emailed'];
			}
		}
		return $section;
	}

	/**
	 * Souhlasy „informace o slevách“. Odhlášení starým odkazem nastavilo allow_newsletters = 0
	 * všem Rezervacím e‑mailu, souhlas tedy platí od první Rezervace s 1 po posledním odhlášení.
	 *
	 * @return Section
	 */
	private static function import_consents(): array {
		global $wpdb;
		$section = self::section();
		$rows    = $wpdb->get_results(
			$wpdb->prepare( 'SELECT email, created FROM %i WHERE allow_newsletters = 1 ORDER BY created', self::old_table() ),
			ARRAY_A
		);

		$consents = [];
		foreach ( $rows ?: [] as $row ) {
			$email                = Pneukarnik_Subscriptions::normalize( self::text( $row['email'] ) );
			$consents[ $email ] ??= (string) $row['created'];
		}
		foreach ( $consents as $email => $consented_at ) {
			if ( ! is_email( $email ) ) {
				++$section['failed'];
				/* translators: %s: e‑mail */
				$section['problems'][] = sprintf( __( 'Souhlas pro „%s“: neplatný e‑mail, nepřevedeno.', 'pneukarnik-booking' ), $email );
				continue;
			}
			if ( Pneukarnik_Subscriptions::import_legacy( $email, $consented_at ) ) {
				++$section['imported'];
			} else {
				++$section['skipped'];
			}
		}
		return $section;
	}

	/**
	 * Text ze staré tabulky. Starý web ukládal vstup přes htmlspecialchars.
	 */
	private static function text( mixed $value ): string {
		return sanitize_text_field( html_entity_decode( (string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	}

	private static function booking_label( string $date, string $time, string $name ): string {
		$valid = (bool) preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m ) && checkdate( (int) $m[2], (int) $m[3], (int) $m[1] );
		/* translators: 1: datum, 2: čas, 3: jméno Zákazníka */
		return sprintf( __( 'Rezervace %1$s v %2$s (%3$s)', 'pneukarnik-booking' ), $valid ? pneukarnik_format_date( $date ) : $date, $time, $name );
	}

	/**
	 * @return Section
	 */
	private static function section(): array {
		return [
			'imported' => 0,
			'skipped'  => 0,
			'failed'   => 0,
			'problems' => [],
		];
	}
}
