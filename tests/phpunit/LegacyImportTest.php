<?php
/**
 * Převod dat ze starého webu přes REST /admin/legacy-import nad fixturou staré tabulky
 * `reservations` a starého CPT `service`: mapování, idempotence, souhlasy a e‑maily.
 */

declare(strict_types=1);

class LegacyImportTest extends Pneukarnik_REST_Test_Case {

	private const NEXT_MONDAY = '2027-01-18';

	private int $old_tyres;

	public function set_up(): void {
		parent::set_up();
		Pneukarnik_Clock::freeze( '2027-01-15 10:00' );
		update_option( 'pneukarnik_cancellation_hours', 24 );
		$this->create_old_table();
		$this->old_tyres = $this->old_service(
			'Přezutí pneu',
			[
				'duration'      => '60',
				'price'         => '650',
				'display_price' => '1',
				'reservable'    => '1',
				'seasonal'      => '1',
				'mechanical'    => '0',
				'index'         => '-100',
			]
		);
		$this->capture_mails();
		$this->log_in_as( 'administrator' );
	}

	public function tear_down(): void {
		global $wpdb;
		// Dočasná tabulka by přežila rollback testu.
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', Pneukarnik_Legacy_Import::old_table() ) );
		parent::tear_down();
	}

	public function test_old_services_become_drafts_mapped_by_id(): void {
		$geometry = $this->old_service(
			'Geometrie',
			[
				'duration'      => '180',
				'price'         => '0',
				'display_price' => '1',
				'reservable'    => '0',
				'seasonal'      => '0',
				'mechanical'    => '1',
			]
		);

		$report = $this->import();

		$this->assertSame( [ 2, 0, 0 ], $this->counts( $report['services'] ) );
		$tyres = $this->service_for( $this->old_tyres );
		$this->assertSame(
			[ 'Přezutí pneu', 'draft', 'pneuservis', 60, 650, true, true, true, -100 ],
			[ $tyres->title, $tyres->status, $tyres->category, $tyres->duration, $tyres->price, $tyres->price_from, $tyres->bookable, $tyres->seasonal, get_post( $tyres->id )->menu_order ]
		);
		$geometry = $this->service_for( $geometry );
		$this->assertSame(
			[ 'autoservis', 180, null, false, false ],
			[ $geometry->category, $geometry->duration, $geometry->price, $geometry->bookable, $geometry->seasonal ],
			'nulovou cenu doplní Provozovatel'
		);
	}

	public function test_existing_service_with_the_same_slug_or_title_is_taken_over(): void {
		// Služby ze starého pokusu o nový web, který běžel ve stejné databázi.
		$attempt       = $this->attempt_service(
			'Přezutí pneumatik',
			get_post( $this->old_tyres )->post_name,
			[
				'_service_duration'       => '30',
				'_service_is_autoservice' => '0',
			]
		);
		$old_ozone     = $this->old_service(
			'Dezinfekce vozidla ozonem',
			[
				'duration'   => '60',
				'mechanical' => '1',
			]
		);
		$attempt_ozone = $this->attempt_service( 'Dezinfekce vozidla  ozonem', 'dezinfekce-ozonem', [ '_service_duration' => '60' ] );
		$this->old_reservation();

		$report = $this->import();

		$this->assertSame( [ 2, 0, 0 ], $this->counts( $report['services'] ) );
		$this->assertSame( [ 'Služba „Přezutí pneumatik“: převzata existující, Délka 30 min (na starém webu 60 min). Zkontrolujte ji.' ], $report['services']['problems'] );
		$tyres = $this->service_for( $this->old_tyres );
		$this->assertSame( $attempt, $tyres->id );
		$this->assertSame(
			[ 'publish', 'pneuservis', 30, true, -100 ],
			[ $tyres->status, $tyres->category, $tyres->duration, $tyres->price_from, get_post( $attempt )->menu_order ],
			'stav, Délka a cena zůstaly, chybějící pole doplněná'
		);
		$this->assertSame( $attempt_ozone, $this->service_for( $old_ozone )->id );
		$this->assertSame( 'autoservis', $this->service_for( $old_ozone )->category );
		$booking = $this->bookings()[0];
		$this->assertSame( [ $attempt ], array_column( $booking['services'], 'id' ) );
		$this->assertSame( [ '10:00', [ 60 ] ], [ $booking['time_end'], array_column( $booking['services'], 'duration' ) ], 'dílnu zabírá jako na starém webu' );
	}

	public function test_future_and_last_year_bookings_are_converted(): void {
		$this->old_reservation(
			[
				'id'      => 'AbCdEfGhIjKlMnOpQrSt',
				'name'    => 'Novák &amp; syn',
				'spz'     => '1ab 2345',
				'email'   => 'Jan@Example.test',
				'phone'   => '603 123 456',
				'comment' => 'Volat &quot;předem&quot;',
				'date'    => self::NEXT_MONDAY,
				'time'    => '09:00:00',
				'created' => '2026-12-01 18:30:00',
			]
		);
		$this->old_reservation(
			[
				'date' => '2026-01-16',
				'time' => '08:00:00',
			]
		);

		$report = $this->import();

		$this->assertSame( [ 2, 0, 0 ], $this->counts( $report['bookings'] ) );
		$bookings = $this->bookings();
		$this->assertSame( [ '2026-01-16', self::NEXT_MONDAY ], array_column( $bookings, 'date' ) );
		$booking = $bookings[1];
		$this->assertSame(
			[ 'stary-web', 'CONFIRMED', '09:00', '10:00', 'Novák & syn', '1AB2345', 'jan@example.test', '603 123 456', 'Volat "předem"', '2026-12-01 18:30:00' ],
			[ $booking['source'], $booking['status'], $booking['time_start'], $booking['time_end'], $booking['name'], $booking['plate'], $booking['email'], $booking['phone'], $booking['note'], $booking['created_at'] ]
		);
		$this->assertSame( [ $this->service_for( $this->old_tyres )->id ], array_column( $booking['services'], 'id' ) );
		$this->assertSame( [ 'Přezutí pneu' ], array_column( $booking['services'], 'name' ) );
	}

	public function test_cancelled_too_old_and_unknown_service_are_not_converted(): void {
		$this->old_reservation( [ 'deleted' => 1 ] );
		$this->old_reservation(
			[
				'date' => '2026-01-15',
				'time' => '09:00:00',
			]
		);
		$this->old_reservation(
			[
				'name'      => 'Eva Malá',
				'serviceId' => 999,
				'time'      => '11:00:00',
			]
		);

		$report = $this->import();

		$this->assertSame( [ 0, 2, 1 ], $this->counts( $report['bookings'] ) );
		$this->assertSame( [ 'Rezervace 18.01.2027 v 11:00 (Eva Malá): neznámá Služba 999, nepřevedeno.' ], $report['bookings']['problems'] );
		$this->assertSame( [], $this->bookings() );
	}

	public function test_running_again_adds_only_new_records_and_keeps_changes(): void {
		$this->old_reservation( [ 'allow_newsletters' => 1 ] );
		$first   = $this->import();
		$booking = $this->bookings()[0];
		$this->assertSame( 200, $this->rest( 'PATCH', "/admin/bookings/{$booking['id']}", [ 'note' => 'Změna v novém systému' ] )->get_status() );
		$this->old_reservation( [ 'time' => '10:00:00' ] );

		$again = $this->import();

		$this->assertSame( [ 1, 0, 0 ], $this->counts( $first['bookings'] ) );
		$this->assertSame( [ 0, 1, 0 ], $this->counts( $again['services'] ) );
		$this->assertSame( [ 1, 1, 0 ], $this->counts( $again['bookings'] ) );
		$this->assertSame( [ 0, 1, 0 ], $this->counts( $again['consents'] ) );
		$this->assertSame( [ '09:00', '10:00' ], array_column( $this->bookings(), 'time_start' ) );
		$this->assertSame( 'Změna v novém systému', $this->bookings()[0]['note'] );
		$this->assertCount(
			1,
			get_posts(
				[
					'post_type'   => 'pneukarnik_service',
					'post_status' => 'any',
				]
			)
		);
	}

	public function test_overlapping_future_booking_is_converted_with_a_warning(): void {
		$this->old_reservation( [ 'time' => '09:00:00' ] );
		$this->old_reservation(
			[
				'name' => 'Eva Malá',
				'time' => '09:30:00',
			]
		);
		// Minulé překryvy (chyba starého webu) se převedou bez upozornění.
		foreach ( [ '09:00:00', '09:30:00' ] as $time ) {
			$this->old_reservation(
				[
					'date' => '2026-12-01',
					'time' => $time,
				]
			);
		}

		$report = $this->import();

		$this->assertSame( [ 4, 0, 0 ], $this->counts( $report['bookings'] ) );
		$this->assertSame( [ 'Rezervace 18.01.2027 v 09:30 (Eva Malá): převedeno, ale překrývá se s jinou Rezervací. Domluvte se se Zákazníkem.' ], $report['bookings']['problems'] );
	}

	public function test_discount_consents_keep_only_their_original_purpose(): void {
		$this->old_reservation(
			[
				'email'             => 'Jan@Example.test',
				'allow_newsletters' => 1,
				'deleted'           => 1,
				'date'              => '2022-05-02',
				'created'           => '2022-04-20 10:00:00',
			]
		);
		$this->old_reservation(
			[
				'email'             => 'jan@example.test',
				'allow_newsletters' => 1,
				'created'           => '2027-01-02 10:00:00',
			]
		);
		$this->old_reservation(
			[
				'email' => 'bez@example.test',
				'time'  => '10:00:00',
			]
		);
		$this->old_reservation(
			[
				'email'             => 'odhlasen@example.test',
				'allow_newsletters' => 1,
				'time'              => '11:00:00',
			]
		);
		$this->old_reservation(
			[
				'email'             => 'neni-email',
				'allow_newsletters' => 1,
				'time'              => '12:00:00',
			]
		);
		$this->rest( 'POST', '/unsubscribe', [ 'email' => 'odhlasen@example.test' ] ); // Starý odkaz po přepnutí webu.

		$report = $this->import();

		$this->assertSame( [ 1, 1, 1 ], $this->counts( $report['consents'] ) );
		$this->assertTrue( Pneukarnik_Subscriptions::is_active( 'jan@example.test', Pneukarnik_Subscriptions::LEGACY ) );
		$this->assertFalse( Pneukarnik_Subscriptions::is_active( 'jan@example.test', Pneukarnik_Subscriptions::REMINDER ) );
		$this->assertFalse( Pneukarnik_Subscriptions::is_active( 'bez@example.test', Pneukarnik_Subscriptions::LEGACY ) );
		$this->assertFalse( Pneukarnik_Subscriptions::is_active( 'odhlasen@example.test', Pneukarnik_Subscriptions::LEGACY ) );
		global $wpdb;
		$this->assertSame(
			[ '2022-04-20 10:00:00', 'stary-web' ],
			array_values( (array) $wpdb->get_row( $wpdb->prepare( 'SELECT consented_at, consent_source FROM %i WHERE email = %s', Pneukarnik_DB::subscriptions_table(), 'jan@example.test' ), ARRAY_A ) ),
			'souhlas platí od první Rezervace se souhlasem'
		);
	}

	public function test_cancel_links_are_emailed_to_future_bookings_only_when_asked(): void {
		$this->old_reservation( [ 'email' => 'jan@example.test' ] );
		$this->old_reservation(
			[
				'email' => 'minula@example.test',
				'date'  => '2026-12-01',
			]
		);
		$report = $this->import();
		$this->assertSame( 0, $report['bookings']['emailed'] );
		$this->assertSame( [], $this->mails );

		$this->old_reservation(
			[
				'email' => 'eva@example.test',
				'time'  => '11:00:00',
			]
		);
		$this->old_reservation(
			[
				'email' => 'minula2@example.test',
				'date'  => '2026-12-02',
			]
		);
		$report = $this->import( true );

		$this->assertSame( 1, $report['bookings']['emailed'] );
		$mail = $this->mail_to( 'eva@example.test' );
		$this->assertCount( 1, $this->mails, 'jen nově převedené budoucí Rezervace' );
		$this->assertSame( 'Nový odkaz ke zrušení rezervace na pondělí 18. 1. 2027 v 11:00', $mail['subject'] );
		$this->assertStringContainsString( 'starý klíč pro zrušení už neplatí', $mail['text'] );
		$cancelled = $this->cancel( $this->cancel_token_from( $mail ) );
		$this->assertSame( 'cancellation.cancelled', $cancelled->get_data()['code'] );
	}

	public function test_without_the_old_table_there_is_nothing_to_convert(): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'DROP TABLE %i', Pneukarnik_Legacy_Import::old_table() ) );

		$response = $this->rest( 'POST', '/admin/legacy-import' );

		$this->assertSame( 404, $response->get_status() );
		$this->assertSame( 'legacy_import.no_source', $response->get_data()['code'] );
	}

	public function test_only_site_administrator_can_convert(): void {
		$this->log_in_as( 'pneukarnik_manager' );

		$this->assertSame( 403, $this->rest( 'POST', '/admin/legacy-import' )->get_status() );
	}

	/**
	 * @return array<string, mixed> Report převodu.
	 */
	private function import( bool $send_cancel_links = false ): array {
		$response = $this->rest( 'POST', '/admin/legacy-import', [ 'send_cancel_links' => $send_cancel_links ] );
		$this->assertSame( 200, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );
		return $response->get_data();
	}

	/**
	 * @param array{imported:int,skipped:int,failed:int} $section
	 * @return array{0:int,1:int,2:int} Převedeno, přeskočeno, chybné.
	 */
	private function counts( array $section ): array {
		return [ $section['imported'], $section['skipped'], $section['failed'] ];
	}

	/**
	 * Všechny Rezervace, jak je vidí Provozovatel v seznamu.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function bookings(): array {
		$response = $this->rest( 'GET', '/admin/bookings' );
		$this->assertSame( 200, $response->get_status() );
		return $response->get_data()['bookings'];
	}

	private function service_for( int $old_id ): Pneukarnik_Service {
		$ids = get_posts(
			[
				'post_type'   => 'pneukarnik_service',
				'post_status' => 'any',
				'meta_key'    => '_service_legacy_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'  => (string) $old_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'fields'      => 'ids',
			]
		);
		$this->assertCount( 1, $ids );
		$service = Pneukarnik_Service::find( (int) $ids[0] );
		$this->assertNotNull( $service );
		return $service;
	}

	/**
	 * Zveřejněná Služba ze starého pokusu o nový web: bez perexu a Kategorie, zveřejněná ještě
	 * před hlídáním povinných částí (to by ji při uložení vrátilo do konceptu).
	 *
	 * @param array<string, string> $meta
	 */
	private function attempt_service( string $title, string $slug, array $meta ): int {
		global $wpdb;
		$id = self::factory()->post->create(
			[
				'post_type'  => 'pneukarnik_service',
				'post_title' => $title,
				'post_name'  => $slug,
				'meta_input' => $meta,
			]
		);
		$wpdb->update(
			$wpdb->posts,
			[
				'post_status' => 'publish',
				'post_name'   => $slug,
			],
			[ 'ID' => $id ]
		);
		clean_post_cache( $id );
		return $id;
	}

	/**
	 * Služba starého webu (CPT `service` z pluginu Pods).
	 *
	 * @param array<string, string> $meta
	 */
	private function old_service( string $title, array $meta ): int {
		return self::factory()->post->create(
			[
				'post_type'   => 'service',
				'post_status' => 'publish',
				'post_title'  => $title,
				'meta_input'  => $meta,
			]
		);
	}

	/**
	 * Stará tabulka Rezervací ve tvaru ze živého webu (v testech dočasná tabulka).
	 */
	private function create_old_table(): void {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				'CREATE TABLE %i (
					id char(20) NOT NULL,
					name varchar(50) NOT NULL,
					spz varchar(20) DEFAULT NULL,
					email varchar(100) NOT NULL,
					phone varchar(15) NOT NULL,
					serviceId int(11) NOT NULL,
					time time NOT NULL,
					date date NOT NULL,
					created datetime NOT NULL,
					allow_newsletters tinyint(1) NOT NULL,
					deleted tinyint(1) NOT NULL DEFAULT 0,
					comment varchar(255) DEFAULT NULL,
					PRIMARY KEY (id)
				)',
				Pneukarnik_Legacy_Import::old_table()
			)
		);
	}

	/**
	 * Řádek staré tabulky, jednotlivá pole jde přepsat.
	 *
	 * @param array<string, mixed> $overrides
	 */
	private function old_reservation( array $overrides = [] ): void {
		global $wpdb;
		$this->assertSame(
			1,
			$wpdb->insert(
				Pneukarnik_Legacy_Import::old_table(),
				$overrides + [
					'id'                => wp_generate_password( 20, false ),
					'name'              => 'Jan Novák',
					'spz'               => '1AB2345',
					'email'             => 'jan@example.test',
					'phone'             => '603123456',
					'serviceId'         => $this->old_tyres,
					'time'              => '09:00:00',
					'date'              => self::NEXT_MONDAY,
					'created'           => '2027-01-02 10:00:00',
					'allow_newsletters' => 0,
					'deleted'           => 0,
					'comment'           => '',
				]
			)
		);
	}
}
