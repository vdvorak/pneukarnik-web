<?php
/**
 * Údaje pro Úvod a hlavičku: otevírací doba na 7 dní (stejná pravidla jako Termíny),
 * kontakty a sociální sítě z Nastavení, Pohotovost jen po zapnutí, mapa a Služby na Úvodu (Akce napřed).
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

	public function test_hero_shows_today_and_tomorrow_with_exceptions_and_holidays(): void {
		require_once dirname( __DIR__, 2 ) . '/wp-content/themes/pneukarnik/inc/template-tags.php';
		Pneukarnik_Clock::freeze( '2027-09-27 16:30' ); // Pondělí, 28. 9. je svátek.
		Pneukarnik_Working_Hours::save( array_fill_keys( [ 'mon', 'tue', 'wed', 'thu', 'fri' ], self::TWO_PARTS ) );
		$this->assertNull( Pneukarnik_Day_Exceptions::add( '2027-09-27', '2027-09-27', false, self::MORNING, 'Školení' ) );

		ob_start();
		pneukarnik_today_tomorrow_hours();
		$html = wp_strip_all_tags( (string) ob_get_clean() );

		$this->assertSame( 'Dnes: 8:00–12:00 (Školení) Zítra: Zavřeno (Den české státnosti)', trim( (string) preg_replace( '/\s+/u', ' ', $html ) ) );
	}

	/**
	 * @return iterable<string, array{0:array<string,mixed>,1:string}>
	 */
	public static function weekly_hours(): iterable {
		$workdays  = [ 'mon', 'tue', 'wed', 'thu', 'fri' ];
		$afternoon = [ [ 'from' => '13:00', 'to' => '17:00' ] ]; // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
		yield 'všední dny stejně' => [ array_fill_keys( $workdays, self::TWO_PARTS ), 'Po–Pá 8:00–12:00, 13:00–17:00' ];
		yield 'sobota jinak' => [ array_merge( array_fill_keys( $workdays, self::TWO_PARTS ), [ 'sat' => self::MORNING ] ), 'Po–Pá 8:00–12:00, 13:00–17:00; So 8:00–12:00' ];
		yield 'zavřená středa rozdělí týden' => [ array_fill_keys( [ 'mon', 'tue', 'thu', 'fri' ], self::MORNING ), 'Po–Út 8:00–12:00; Čt–Pá 8:00–12:00' ];
		yield 'jiné bloky se nesloučí' => [ array_merge( [ 'mon' => self::MORNING ], array_fill_keys( [ 'tue', 'wed' ], $afternoon ) ), 'Po 8:00–12:00; Út–St 13:00–17:00' ];
		yield 'vše zavřené' => [ [], '' ];
	}

	/**
	 * Karta „Raději zavoláte?“ u rezervace ukazuje Pracovní dobu z Nastavení zkráceně.
	 *
	 * @dataProvider weekly_hours
	 * @param array<string,mixed> $hours
	 */
	public function test_weekly_hours_merge_same_consecutive_days_and_skip_closed( array $hours, string $expected ): void {
		require_once dirname( __DIR__, 2 ) . '/wp-content/themes/pneukarnik/inc/template-tags.php';
		$this->assertTrue( Pneukarnik_Working_Hours::save( $hours ) );

		$this->assertSame( $expected, pneukarnik_weekly_hours() );
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

	public function test_social_links_are_only_filled_web_addresses_in_fixed_order(): void {
		update_option( 'pneukarnik_social_instagram', 'https://instagram.com/pneukarnik' );
		update_option( 'pneukarnik_social_facebook', ' https://facebook.com/pneukarnik ' );
		update_option( 'pneukarnik_social_google', 'javascript:alert(1)' );

		$this->assertSame(
			[
				'facebook'  => [
					'label' => 'Facebook',
					'url'   => 'https://facebook.com/pneukarnik',
				],
				'instagram' => [
					'label' => 'Instagram',
					'url'   => 'https://instagram.com/pneukarnik',
				],
			],
			Pneukarnik_Contact::social()
		);
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

	public function test_home_shows_promoted_then_featured_services_at_most_six(): void {
		Pneukarnik_Clock::freeze( '2027-03-15 10:00' );
		$ids = [];
		foreach ( range( 1, 8 ) as $order ) {
			$ids[ $order ] = $this->create_service( 30, false, "Služba {$order}" );
			wp_update_post(
				[
					'ID'         => $ids[ $order ],
					'menu_order' => $order,
				]
			);
		}
		foreach ( [ 1, 2, 3, 4, 5, 6 ] as $order ) {
			update_post_meta( $ids[ $order ], '_service_featured', '1' );
		}
		$this->promotion_for( $ids[8] );
		$this->promotion_for( $ids[4] );

		$this->assertSame(
			[ $ids[4], $ids[8], $ids[1], $ids[2], $ids[3], $ids[5] ],
			array_map( static fn( Pneukarnik_Service $s ): int => $s->id, Pneukarnik_Service::for_home( Pneukarnik_Promotion::current() ) )
		);
	}

	public function test_home_fills_free_places_with_other_published_services_in_set_order(): void {
		Pneukarnik_Clock::freeze( '2027-03-15 10:00' );
		$ids = [];
		foreach ( range( 1, 4 ) as $order ) {
			$ids[ $order ] = $this->create_service( 30, false, "Služba {$order}" );
			wp_update_post(
				[
					'ID'         => $ids[ $order ],
					'menu_order' => $order,
				]
			);
		}
		update_post_meta( $ids[3], '_service_featured', '1' );
		$this->promotion_for( $ids[4] );

		$this->assertSame(
			[ $ids[4], $ids[3], $ids[1], $ids[2] ],
			array_map( static fn( Pneukarnik_Service $s ): int => $s->id, Pneukarnik_Service::for_home( Pneukarnik_Promotion::current() ) )
		);
	}

	public function test_why_us_lines_are_title_with_optional_text(): void {
		update_option( 'pneukarnik_why_us', "Partner sítě BestDrive | Věrnostní karta platí i u nás.\r\n\n  Ve Znojmě od roku 1991  \n| Termín online, bez registrace\nVybavení dílny |  \nCena | od 600 Kč | s DPH\n  |  " );

		$this->assertSame(
			[
				[
					'title' => 'Partner sítě BestDrive',
					'text'  => 'Věrnostní karta platí i u nás.',
				],
				[
					'title' => 'Ve Znojmě od roku 1991',
					'text'  => '',
				],
				[
					'title' => 'Termín online, bez registrace',
					'text'  => '',
				],
				[
					'title' => 'Vybavení dílny',
					'text'  => '',
				],
				[
					'title' => 'Cena',
					'text'  => 'od 600 Kč | s DPH',
				],
			],
			pneukarnik_why_us()
		);
	}

	public function test_why_us_without_titles_is_empty(): void {
		update_option( 'pneukarnik_why_us', " \n | \n" );

		$this->assertSame( [], pneukarnik_why_us() );
	}

	/**
	 * @return array<string, array{string, int|null}>
	 */
	public static function founded_years(): array {
		return [
			'nezadaný'        => [ '', null ],
			'rok'             => [ ' 1991 ', 1991 ],
			'letošní'         => [ '2027', 2027 ],
			'v budoucnu'      => [ '2028', null ],
			'příliš starý'    => [ '1899', null ],
			'není rok'        => [ 'od 1991', null ],
			'dvouciferný'     => [ '91', null ],
			'desetinné číslo' => [ '1991.5', null ],
		];
	}

	/**
	 * @dataProvider founded_years
	 */
	public function test_founded_year_is_given_out_only_when_it_makes_sense( string $saved, ?int $expected ): void {
		Pneukarnik_Clock::freeze( '2027-03-01 08:00' );
		update_option( 'pneukarnik_founded_year', $saved );

		$this->assertSame( $expected, pneukarnik_founded_year() );
	}

	/**
	 * Akce u Služby platná v březnu 2027.
	 */
	private function promotion_for( int $service ): void {
		self::factory()->post->create(
			[
				'post_type'   => 'pneukarnik_promotion',
				'post_status' => 'publish',
				'post_title'  => 'Zaváděcí cena',
				'meta_input'  => [
					'_promotion_service_id' => (string) $service,
					'_promotion_price'      => '990',
					'_promotion_valid_from' => '2027-03-01',
					'_promotion_valid_to'   => '2027-03-31',
				],
			]
		);
	}
}
