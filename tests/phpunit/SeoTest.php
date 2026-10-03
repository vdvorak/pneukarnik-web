<?php
/**
 * Údaje pro vyhledávače: title a description každé stránky (u Služeb a Průvodců z jejich polí,
 * pokud je Provozovatel nepřepíše), LocalBusiness z Nastavení, Service ze Služby a obsah sitemapy.
 */

declare(strict_types=1);

class SeoTest extends Pneukarnik_REST_Test_Case {

	private const COMPANY = 'Pneuservis a autoservis Jan Kárník';

	public function set_up(): void {
		parent::set_up();
		$this->set_permalink_structure( '/%postname%/' );
		// Adresy Průvodců vzniknou jen při registraci se zapnutými pěknými adresami, viz GuideTest.
		Pneukarnik_Guide_Type::register();
		flush_rewrite_rules( false );
		update_option( 'pneukarnik_phone', '+420 775 565 326' );
		update_option( 'pneukarnik_address', 'Dobšická 10, 669 02 Znojmo' );
	}

	public function test_service_title_and_description_come_from_its_fields(): void {
		$service = Pneukarnik_Service::find( $this->create_service( 60, title: 'Přezutí' ) );

		$this->assertNotNull( $service );
		$this->assertSame( 'Přezutí – ' . self::COMPANY, Pneukarnik_Seo::service_title( $service ) );
		$this->assertSame( 'Sezónní přezutí.', Pneukarnik_Seo::service_description( $service ) );
	}

	public function test_operator_overrides_service_title_and_description(): void {
		$id = $this->create_service( 60, title: 'Přezutí' );
		update_post_meta( $id, '_service_seo_title', 'Přezutí pneu Znojmo' );
		update_post_meta( $id, '_service_seo_description', 'Přezujeme vám kola do 30 minut.' );
		$service = Pneukarnik_Service::find( $id );

		$this->assertNotNull( $service );
		$this->assertSame( 'Přezutí pneu Znojmo', Pneukarnik_Seo::service_title( $service ) );
		$this->assertSame( 'Přezujeme vám kola do 30 minut.', Pneukarnik_Seo::service_description( $service ) );
	}

	public function test_guide_title_and_description_come_from_its_fields_unless_overridden(): void {
		$id    = $this->guide( 'Kdy přezout', 'Kdy je ten správný čas na přezutí.' );
		$guide = Pneukarnik_Guide::find( $id );

		$this->assertNotNull( $guide );
		$this->assertSame( 'Kdy přezout – ' . self::COMPANY, Pneukarnik_Seo::guide_title( $guide ) );
		$this->assertSame( 'Kdy je ten správný čas na přezutí.', Pneukarnik_Seo::guide_description( $guide ) );

		update_post_meta( $id, '_guide_seo_title', 'Kdy přezout na zimní pneumatiky' );
		update_post_meta( $id, '_guide_seo_description', 'Zimní pneumatiky od 7 °C.' );
		$guide = Pneukarnik_Guide::find( $id );

		$this->assertNotNull( $guide );
		$this->assertSame( 'Kdy přezout na zimní pneumatiky', Pneukarnik_Seo::guide_title( $guide ) );
		$this->assertSame( 'Zimní pneumatiky od 7 °C.', Pneukarnik_Seo::guide_description( $guide ) );
	}

	public function test_long_description_is_shortened_to_whole_words(): void {
		$perex = trim( str_repeat( 'Přezujeme, vyvážíme a uskladníme. ', 10 ) );
		$id    = $this->create_service( 60 );
		update_post_meta( $id, '_service_perex', $perex );

		$description = Pneukarnik_Seo::service_description( $this->service( $id ) );

		$this->assertLessThanOrEqual( 160, mb_strlen( $description ) );
		$this->assertStringEndsWith( '…', $description );
		$this->assertStringStartsWith( mb_substr( $description, 0, -1 ) . ' ', $perex ); // Celá slova.
	}

	public function test_every_page_type_has_its_own_title_and_description(): void {
		$service = $this->create_service( 60, title: 'Přezutí' );
		update_post_meta( $service, '_service_featured', '1' );
		$guide   = $this->guide( 'Kdy přezout', 'Kdy je ten správný čas na přezutí.', $service );
		$kontakt = $this->page( 'kontakt', 'Kontakt', '<p>Odbočte u čerpací stanice.</p>' );
		$o_nas   = $this->page( 'o-nas', 'O nás', '<!-- wp:heading --><h2>Historie</h2><!-- /wp:heading --><!-- wp:paragraph --><p>Servis ve Znojmě od roku 1991.</p><!-- /wp:paragraph -->' );

		$pages        = [
			'Úvod'       => [ home_url( '/' ), self::COMPANY . ' – Znojmo' ],
			'Pneuservis' => [ home_url( '/pneuservis/' ), 'Pneuservis – ' . self::COMPANY ],
			'Autoservis' => [ home_url( '/autoservis/' ), 'Autoservis – ' . self::COMPANY ],
			'Služba'     => [ get_permalink( $service ), 'Přezutí – ' . self::COMPANY ],
			'Průvodce'   => [ get_permalink( $guide ), 'Kdy přezout – ' . self::COMPANY ],
			'Kontakt'    => [ get_permalink( $kontakt ), 'Kontakt – ' . self::COMPANY ],
			'O nás'      => [ get_permalink( $o_nas ), 'O nás – ' . self::COMPANY ],
			'Rezervace'  => [ home_url( '/rezervace/' ), 'Rezervace termínu – ' . self::COMPANY ],
		];
		$descriptions = [];
		foreach ( $pages as $name => [ $url, $title ] ) {
			$this->go_to( (string) $url );
			$seo = Pneukarnik_Seo::current();

			$this->assertNotNull( $seo, $name );
			$this->assertSame( $title, $seo['title'], $name );
			$this->assertSame( $title, wp_get_document_title(), $name );
			$this->assertSame( $url, $seo['url'], $name );
			$this->assertNotSame( '', $seo['description'], $name );
			$descriptions[ $name ] = $seo['description'];
		}

		$this->assertSame( array_unique( $descriptions ), $descriptions );
		$this->assertSame( 'Přezutí. Objednejte se online na volný termín, nebo zavolejte +420 775 565 326. Dobšická 10, 669 02 Znojmo', $descriptions['Úvod'] );
		$this->assertSame( 'Pneuservis: Přezutí. Ceny, co která služba zahrnuje, a online rezervace termínu.', $descriptions['Pneuservis'] );
		$this->assertStringContainsString( 'Dobšická 10, 669 02 Znojmo', $descriptions['Kontakt'] );
		$this->assertSame( 'Servis ve Znojmě od roku 1991.', $descriptions['O nás'] );
	}

	public function test_pages_for_one_booking_and_missing_pages_have_no_description(): void {
		foreach ( [ '/rezervace/zruseni/?r=neplatny', '/neexistuje/' ] as $path ) {
			$this->go_to( home_url( $path ) );
			$seo = Pneukarnik_Seo::current();

			$this->assertNotNull( $seo, $path );
			$this->assertSame( '', $seo['description'], $path );
			$this->assertSame( '', $seo['url'], $path );
		}
	}

	public function test_local_business_has_contact_and_working_hours_from_settings(): void {
		update_option( 'pneukarnik_email', 'servis@example.test' );
		update_option( 'pneukarnik_ico', '12345678' );
		update_option( 'pneukarnik_dic', 'CZ12345678' );
		update_option( 'pneukarnik_social_facebook', 'https://www.facebook.com/pneukarnik' );
		$this->save_hours(
			[
				'mon' => [ self::block( '08:00', '12:00' ), self::block( '13:00', '17:00' ) ],
				'tue' => [ self::block( '08:00', '12:00' ), self::block( '13:00', '17:00' ) ],
				'sat' => [ self::block( '08:00', '12:00' ) ],
			]
		);

		$business = Pneukarnik_Seo::local_business();

		$this->assertSame( 'https://schema.org', $business['@context'] );
		$this->assertSame( 'AutoRepair', $business['@type'] ); // Podtyp LocalBusiness.
		$this->assertSame( self::COMPANY, $business['name'] );
		$this->assertSame( home_url( '/' ), $business['url'] );
		$this->assertSame( '+420 775 565 326', $business['telephone'] );
		$this->assertSame( 'servis@example.test', $business['email'] );
		$this->assertSame(
			[
				'@type'           => 'PostalAddress',
				'streetAddress'   => 'Dobšická 10',
				'postalCode'      => '669 02',
				'addressLocality' => 'Znojmo',
				'addressCountry'  => 'CZ',
			],
			$business['address']
		);
		$this->assertSame( '12345678', $business['taxID'] );
		$this->assertSame( 'CZ12345678', $business['vatID'] );
		$this->assertSame( [ 'https://www.facebook.com/pneukarnik' ], $business['sameAs'] );
		$this->assertSame(
			[
				[ [ 'Monday', 'Tuesday', 'Saturday' ], '08:00', '12:00' ],
				[ [ 'Monday', 'Tuesday' ], '13:00', '17:00' ],
			],
			self::hours( $business['openingHoursSpecification'] )
		);
	}

	public function test_change_in_settings_shows_in_local_business_right_away(): void {
		$this->save_hours( [ 'mon' => [ self::block( '08:00', '12:00' ) ] ] );
		$this->assertSame( '+420 775 565 326', Pneukarnik_Seo::local_business()['telephone'] );

		update_option( 'pneukarnik_phone', '+420 603 000 111' );
		update_option( 'pneukarnik_address', 'Pražská 5, 669 02 Znojmo' );
		$this->save_hours( [ 'fri' => [ self::block( '07:00', '15:00' ) ] ] );

		$business = Pneukarnik_Seo::local_business();
		$this->assertSame( '+420 603 000 111', $business['telephone'] );
		$this->assertSame( 'Pražská 5', $business['address']['streetAddress'] );
		$this->assertSame( [ [ [ 'Friday' ], '07:00', '15:00' ] ], self::hours( $business['openingHoursSpecification'] ) );
	}

	public function test_local_business_has_exceptions_and_holidays_of_the_coming_days(): void {
		Pneukarnik_Clock::freeze( '2027-09-27 08:00' ); // Pondělí, 28. 9. je svátek.
		$this->save_hours( array_fill_keys( [ 'mon', 'tue', 'wed', 'thu', 'fri' ], [ self::block( '08:00', '17:00' ) ] ) );
		$this->assertNull( Pneukarnik_Day_Exceptions::add( '2027-09-30', '2027-09-30', false, [ self::block( '08:00', '12:00' ) ], 'Školení' ) );
		$this->assertNull( Pneukarnik_Day_Exceptions::add( '2027-10-26', '2027-10-27', false, null, 'Dovolená' ) ); // 27. 10. je až za 30 dní.

		$special = Pneukarnik_Seo::local_business()['specialOpeningHoursSpecification'];

		$this->assertSame(
			[
				[ '2027-09-28', '00:00', '00:00' ],
				[ '2027-09-30', '08:00', '12:00' ],
				[ '2027-10-26', '00:00', '00:00' ],
			],
			array_map( static fn( array $day ): array => [ $day['validFrom'], $day['opens'], $day['closes'] ], $special )
		);
	}

	public function test_local_business_leaves_out_what_the_operator_has_not_set(): void {
		delete_option( 'pneukarnik_phone' );
		delete_option( 'pneukarnik_address' );

		$business = Pneukarnik_Seo::local_business();

		foreach ( [ 'telephone', 'email', 'address', 'hasMap', 'taxID', 'vatID', 'sameAs', 'openingHoursSpecification' ] as $key ) {
			$this->assertArrayNotHasKey( $key, $business );
		}
	}

	public function test_address_in_another_format_stays_whole(): void {
		update_option( 'pneukarnik_address', 'Dobšická 10 Znojmo' );

		$this->assertSame(
			[
				'@type'          => 'PostalAddress',
				'streetAddress'  => 'Dobšická 10 Znojmo',
				'addressCountry' => 'CZ',
			],
			Pneukarnik_Seo::local_business()['address']
		);
	}

	public function test_service_schema_has_price_and_provider(): void {
		$id = $this->create_service( 60, title: 'Přezutí' );

		$schema = Pneukarnik_Seo::service_schema( $this->service( $id ) );

		$this->assertSame( 'Service', $schema['@type'] );
		$this->assertSame( 'Přezutí', $schema['name'] );
		$this->assertSame( 'Sezónní přezutí.', $schema['description'] );
		$this->assertSame( get_permalink( $id ), $schema['url'] );
		$this->assertSame( 'Pneuservis', $schema['serviceType'] );
		$this->assertSame(
			[
				'@type'         => 'Offer',
				'priceCurrency' => 'CZK',
				'price'         => 600,
			],
			$schema['offers']
		);
		$this->assertSame( 'AutoRepair', $schema['provider']['@type'] );
		$this->assertSame( home_url( '/#provozovatel' ), $schema['provider']['@id'] );
		$this->assertSame( '+420 775 565 326', $schema['provider']['telephone'] );

		update_post_meta( $id, '_service_price_from', '1' );
		$this->assertSame( 600, Pneukarnik_Seo::service_schema( $this->service( $id ) )['offers']['priceSpecification']['minPrice'] );

		update_post_meta( $id, '_service_price_by_vehicle', '1' );
		$this->assertArrayNotHasKey( 'offers', Pneukarnik_Seo::service_schema( $this->service( $id ) ) );
	}

	public function test_local_business_is_on_home_and_kontakt_and_service_on_its_detail(): void {
		$service = $this->create_service( 60 );
		$kontakt = $this->page( 'kontakt', 'Kontakt', '' );
		$o_nas   = $this->page( 'o-nas', 'O nás', '<p>Historie.</p>' );

		$types = [];
		foreach ( [ home_url( '/' ), get_permalink( $kontakt ), get_permalink( $service ), get_permalink( $o_nas ), home_url( '/pneuservis/' ), home_url( '/rezervace/' ) ] as $url ) {
			$this->go_to( (string) $url );
			$types[] = array_column( Pneukarnik_Seo::schema(), '@type' );
		}

		$this->assertSame( [ [ 'AutoRepair' ], [ 'AutoRepair' ], [ 'Service' ], [], [], [] ], $types );
	}

	public function test_sitemap_has_published_pages_services_and_guides_and_nothing_else(): void {
		$page          = $this->page( 'kontakt', 'Kontakt', '<p>Příjezd.</p>' );
		$draft_page    = self::factory()->post->create(
			[
				'post_type'   => 'page',
				'post_status' => 'draft',
			]
		);
		$service       = $this->create_service( 60 );
		$draft_service = self::factory()->post->create(
			[
				'post_type'   => 'pneukarnik_service',
				'post_status' => 'draft',
				'post_title'  => 'Koncept Služby',
			]
		);
		$guide         = $this->guide( 'Kdy přezout', 'Perex.' );
		$post          = self::factory()->post->create( [ 'post_title' => 'Stará novinka' ] );
		$notice        = self::factory()->post->create(
			[
				'post_type'  => 'pneukarnik_notice',
				'post_title' => 'Dovolená',
			]
		);
		self::factory()->user->create( [ 'role' => 'author' ] );

		$sitemaps = wp_sitemaps_get_server();
		$urls     = [];
		foreach ( $sitemaps->registry->get_providers() as $provider ) {
			foreach ( array_keys( $provider->get_object_subtypes() ) ?: [ '' ] as $subtype ) {
				$urls = [ ...$urls, ...array_column( $provider->get_url_list( 1, (string) $subtype ), 'loc' ) ];
			}
		}

		$this->assertSame( [ 'posts', 'pneukarnik' ], array_keys( $sitemaps->registry->get_providers() ) );
		$this->assertSame( [ 'page', 'pneukarnik_service', 'pneukarnik_guide' ], array_keys( $sitemaps->registry->get_provider( 'posts' )->get_object_subtypes() ) );
		foreach ( [ home_url( '/' ), get_permalink( $page ), get_permalink( $service ), get_permalink( $guide ), home_url( '/pneuservis/' ), home_url( '/autoservis/' ), home_url( '/rezervace/' ) ] as $expected ) {
			$this->assertContains( $expected, $urls );
		}
		foreach ( [ get_permalink( $draft_page ), get_permalink( $draft_service ), get_permalink( $post ), get_permalink( $notice ) ] as $unexpected ) {
			$this->assertNotContains( $unexpected, $urls );
		}
		$this->assertSame( array_unique( $urls ), $urls );
	}

	public function test_matomo_is_measured_only_with_address_and_site_id(): void {
		$this->assertNull( Pneukarnik_Seo::matomo() );

		Pneukarnik_Seo::save_settings( 'https://matomo.example.test/stats', '3', '' );

		$this->assertSame(
			[
				'url'     => 'https://matomo.example.test/stats/',
				'site_id' => 3,
			],
			Pneukarnik_Seo::matomo()
		);

		Pneukarnik_Seo::save_settings( 'https://matomo.example.test/', '', '' );
		$this->assertNull( Pneukarnik_Seo::matomo() );
	}

	public function test_google_verification_keeps_only_the_code_from_a_pasted_tag(): void {
		Pneukarnik_Seo::save_settings( '', '', '<meta name="google-site-verification" content="AbC_12-xyz" />' );
		$this->assertSame( 'AbC_12-xyz', Pneukarnik_Seo::google_verification() );

		Pneukarnik_Seo::save_settings( '', '', ' AbC_12-xyz"><script> ' );
		$this->assertSame( 'AbC_12-xyzscript', Pneukarnik_Seo::google_verification() );
	}

	private function service( int $id ): Pneukarnik_Service {
		$service = Pneukarnik_Service::find( $id );
		$this->assertNotNull( $service );
		return $service;
	}

	private function guide( string $title, string $perex, ?int $service = null ): int {
		return self::factory()->post->create(
			[
				'post_type'    => 'pneukarnik_guide',
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_content' => '<p>Text Průvodce.</p>',
				'meta_input'   => [
					'_guide_perex'      => $perex,
					'_guide_service_id' => $service ?? $this->create_service( 60 ),
				],
			]
		);
	}

	private function page( string $slug, string $title, string $content ): int {
		return self::factory()->post->create(
			[
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_name'    => $slug,
				'post_title'   => $title,
				'post_content' => $content,
				'post_excerpt' => '',
			]
		);
	}

	/**
	 * @param array<string, list<array{from:string,to:string}>> $hours Dny bez bloků jsou zavřené.
	 */
	private function save_hours( array $hours ): void {
		$this->assertTrue( Pneukarnik_Working_Hours::save( $hours ) );
	}

	/**
	 * @return array{from:string,to:string}
	 */
	private static function block( string $from, string $to ): array {
		return [
			'from' => $from,
			'to'   => $to,
		];
	}

	/**
	 * @param list<array{dayOfWeek:list<string>,opens:string,closes:string}> $specification
	 * @return list<array{0:list<string>,1:string,2:string}>
	 */
	private static function hours( array $specification ): array {
		return array_map( static fn( array $block ): array => [ $block['dayOfWeek'], $block['opens'], $block['closes'] ], $specification );
	}
}
