<?php
/**
 * Údaje pro Úvod a hlavičku: otevírací doba na 7 dní (stejná pravidla jako Termíny),
 * kontakty z Nastavení, Pohotovost jen po zapnutí, mapa a nejžádanější Služby.
 */

declare(strict_types=1);

class HomeInfoTest extends Pneukarnik_REST_Test_Case {

	private const MORNING   = [
		[
			'from' => '08:00',
			'to'   => '12:00',
		],
	];
	private const TWO_PARTS = [
		[
			'from' => '08:00',
			'to'   => '12:00',
		],
		[
			'from' => '13:00',
			'to'   => '17:00',
		],
	];

	public function test_upcoming_hours_start_today_and_follow_exceptions_and_holidays(): void {
		Pneukarnik_Clock::freeze( '2027-09-27 16:30' ); // Pondělí, 28. 9. je svátek.
		Pneukarnik_Working_Hours::save( array_fill_keys( [ 'mon', 'tue', 'wed', 'thu', 'fri' ], self::TWO_PARTS ) );
		$this->assertNull( Pneukarnik_Day_Exceptions::add( '2027-09-30', '2027-09-30', false, self::MORNING, 'Školení' ) );
		$this->assertNull( Pneukarnik_Day_Exceptions::add( '2027-10-01', '2027-10-01', false, null, 'Inventura' ) );

		$this->assertSame(
			[
				[ '2027-09-27', self::TWO_PARTS, '' ],
				[ '2027-09-28', null, 'Den české státnosti' ],
				[ '2027-09-29', self::TWO_PARTS, '' ],
				[ '2027-09-30', self::MORNING, 'Školení' ],
				[ '2027-10-01', null, 'Inventura' ],
				[ '2027-10-02', null, '' ],
				[ '2027-10-03', null, '' ],
			],
			array_map( static fn( array $day ): array => [ $day['date'], $day['hours'], $day['note'] ], Pneukarnik_Working_Hours::upcoming( 7 ) )
		);
	}

	public function test_upcoming_hours_use_the_same_hours_as_offered_termins(): void {
		Pneukarnik_Clock::freeze( '2027-09-29 07:00' );
		$this->set_working_hours_every_day( self::MORNING );
		$this->set_booking_rules( 60 );
		$this->assertNull( Pneukarnik_Day_Exceptions::add( '2027-09-30', '2027-09-30', false, [ [ 'from' => '10:00', 'to' => '12:00' ] ], null ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
		$service = $this->create_service( 60 );

		$tomorrow = Pneukarnik_Working_Hours::upcoming( 2 )[1];

		$this->assertSame( [ [ 'from' => '10:00', 'to' => '12:00' ] ], $tomorrow['hours'] ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
		$this->assertSame( [ '10:00', '11:00' ], $this->free_starts( $service, $tomorrow['date'] ) );
	}

	public function test_contact_comes_from_settings_with_default_company(): void {
		update_option( 'pneukarnik_phone', ' +420 775 565 326 ' );
		update_option( 'pneukarnik_email', 'servis@example.test' );
		update_option( 'pneukarnik_address', 'Dobšická 10, 669 02 Znojmo' );
		update_option( 'pneukarnik_ico', '12345678' );
		update_option( 'pneukarnik_dic', 'CZ12345678' );

		$this->assertSame( 'Pneuservis a autoservis Jan Kárník', Pneukarnik_Contact::company() );
		$this->assertSame( '+420 775 565 326', Pneukarnik_Contact::phone() );
		$this->assertSame( '+420 775 565 326', pneukarnik_phone() );
		$this->assertSame( 'servis@example.test', Pneukarnik_Contact::email() );
		$this->assertSame( 'Dobšická 10, 669 02 Znojmo', Pneukarnik_Contact::address() );
		$this->assertSame( '12345678', Pneukarnik_Contact::ico() );
		$this->assertSame( 'CZ12345678', Pneukarnik_Contact::dic() );

		update_option( 'pneukarnik_company', 'Kárník s.r.o.' );
		$this->assertSame( 'Kárník s.r.o.', Pneukarnik_Contact::company() );
	}

	/**
	 * @return array<string, array{string, string, bool}>
	 */
	public static function emergency_settings(): array {
		return [
			'vypnutá'         => [ '0', '+420 600 000 000', false ],
			'nikdy nastavená' => [ '', '+420 600 000 000', false ],
			'bez telefonu'    => [ '1', ' ', false ],
			'zapnutá'         => [ '1', '+420 600 000 000', true ],
		];
	}

	/**
	 * @dataProvider emergency_settings
	 */
	public function test_emergency_is_shown_only_when_enabled_with_phone( string $enabled, string $phone, bool $shown ): void {
		update_option( 'pneukarnik_emergency_enabled', $enabled );
		update_option( 'pneukarnik_emergency_phone', $phone );
		update_option( 'pneukarnik_emergency_text', 'Defekt na cestě nonstop' );

		$expected = [
			'phone' => '+420 600 000 000',
			'text'  => 'Defekt na cestě nonstop',
		];
		$this->assertSame( $shown ? $expected : null, Pneukarnik_Contact::emergency() );
	}

	public function test_map_falls_back_to_address_and_is_missing_without_it(): void {
		$this->assertSame( '', Pneukarnik_Contact::map_embed_url() );

		update_option( 'pneukarnik_address', 'Dobšická 10, Znojmo' );
		$this->assertSame( 'https://www.google.com/maps?output=embed&q=Dob%C5%A1ick%C3%A1%2010%2C%20Znojmo', Pneukarnik_Contact::map_embed_url() );

		update_option( 'pneukarnik_maps_embed_url', 'https://www.google.com/maps/embed?pb=abc' );
		$this->assertSame( 'https://www.google.com/maps/embed?pb=abc', Pneukarnik_Contact::map_embed_url() );
	}

	public function test_featured_services_are_published_ones_in_set_order(): void {
		$second = $this->create_service( 30, false, 'Geometrie' );
		$first  = $this->create_service( 60, false, 'Přezutí' );
		$plain  = $this->create_service( 30, false, 'Vyvážení' );
		wp_update_post(
			[
				'ID'         => $first,
				'menu_order' => 1,
			]
		);
		wp_update_post(
			[
				'ID'         => $second,
				'menu_order' => 2,
			]
		);
		foreach ( [ $first, $second ] as $id ) {
			update_post_meta( $id, '_service_featured', '1' );
		}
		$draft = $this->create_service( 30, false, 'Koncept' );
		update_post_meta( $draft, '_service_featured', '1' );
		wp_update_post(
			[
				'ID'          => $draft,
				'post_status' => 'draft',
			]
		);

		$this->assertSame( [ $first, $second ], array_map( static fn( Pneukarnik_Service $s ): int => $s->id, Pneukarnik_Service::featured() ) );
		$this->assertNotContains( $plain, array_map( static fn( Pneukarnik_Service $s ): int => $s->id, Pneukarnik_Service::featured() ) );
	}
}
