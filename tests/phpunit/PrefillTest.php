<?php
/**
 * „Objednat znovu“: odkaz v e‑mailu nese podepsaný token, REST podle něj vydá kontaktní údaje,
 * Služby a uskladněná kola dané Rezervace, nikdy poznámku. Zfalšovaný nebo upravený token je odmítnut.
 */

declare(strict_types=1);

class PrefillTest extends Pneukarnik_REST_Test_Case {

	private const MONDAY   = '2027-03-01';
	private const CUSTOMER = 'jan@example.test';

	private int $tyres;
	private int $alignment;

	public function set_up(): void {
		parent::set_up();
		Pneukarnik_Clock::freeze( '2027-02-20 12:00' );
		$this->set_working_hours_every_day(
			[
				[
					'from' => '08:00',
					'to'   => '12:00',
				],
			]
		);
		$this->set_booking_rules( 60 );
		$this->tyres     = $this->create_service( 60 );
		$this->alignment = $this->create_service( 30, false, 'Geometrie' );
		update_post_meta( $this->tyres, '_service_ask_stored_wheels', '1' );
		$this->capture_mails();
	}

	public function test_link_in_confirmation_gives_contact_details_services_and_stored_wheels(): void {
		$token = $this->booked_prefill_token( services: [ $this->alignment, $this->tyres ] );

		$response = $this->prefill( $token );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			[
				'name'          => 'Jan Novák',
				'phone'         => '+420 603 123 456',
				'email'         => self::CUSTOMER,
				'plate'         => '1AB2345',
				'vehicle'       => 'Škoda Octavia',
				'service_ids'   => [ $this->alignment, $this->tyres ],
				'stored_wheels' => true,
			],
			$response->get_data()
		);
		$this->assertSame( 'no-store', $response->get_headers()['Cache-Control'] );
	}

	public function test_service_no_longer_bookable_online_is_left_out(): void {
		$phone_only = $this->create_service( 30, false, 'Klimatizace' );
		$token      = $this->booked_prefill_token( services: [ $this->tyres, $phone_only, $this->alignment ] );

		update_post_meta( $phone_only, '_service_bookable', '' );
		wp_update_post(
			[
				'ID'          => $this->alignment,
				'post_status' => 'draft',
			]
		);

		$data = $this->prefill( $token )->get_data();
		$this->assertSame( [ $this->tyres ], $data['service_ids'] );
		$this->assertSame( self::CUSTOMER, $data['email'] );
	}

	public function test_link_after_cancellation_leads_to_a_prefilled_new_booking(): void {
		$this->booked_prefill_token();
		$cancel_token = $this->cancel_token_from( $this->mail_to( self::CUSTOMER ) );
		$this->mails  = [];

		$this->cancel( $cancel_token );
		$token = $this->prefill_token_from( $this->mail_to( self::CUSTOMER ) );

		$this->assertSame( self::CUSTOMER, $this->prefill( $token )->get_data()['email'] );
		$this->assertArrayNotHasKey( 'prefill_url', $this->cancellation( $cancel_token )->get_data() );
	}

	public function test_each_link_gives_its_own_booking(): void {
		$first       = $this->booked_prefill_token();
		$this->mails = [];
		$second      = $this->booked_prefill_token( 'eva@example.test', '10:00' );

		$this->assertSame( self::CUSTOMER, $this->prefill( $first )->get_data()['email'] );
		$this->assertSame( 'eva@example.test', $this->prefill( $second )->get_data()['email'] );
	}

	/**
	 * Zfalšované tokeny: hodnota, nebo funkce z tokenů dvou Rezervací $tokens = ['own' => …, 'other' => …].
	 *
	 * @return array<string, array{mixed}>
	 */
	public static function forged_tokens(): array {
		$id        = static fn( string $token ): string => strstr( $token, '.', true );
		$signature = static fn( string $token ): string => strstr( $token, '.' );
		return [
			'jiné id, stejný podpis'     => [ static fn( array $tokens ): string => ( (int) $tokens['own'] + 1 ) . $signature( $tokens['own'] ) ],
			'id jiné Rezervace'          => [ static fn( array $tokens ): string => $id( $tokens['other'] ) . $signature( $tokens['own'] ) ],
			'upravený podpis'            => [ static fn( array $tokens ): string => substr( $tokens['own'], 0, -1 ) . ( str_ends_with( $tokens['own'], '0' ) ? '1' : '0' ) ],
			'zkrácený podpis'            => [ static fn( array $tokens ): string => substr( $tokens['own'], 0, -2 ) ],
			'bez podpisu'                => [ static fn( array $tokens ): string => $id( $tokens['own'] ) ],
			'podpis velkými písmeny'     => [ static fn( array $tokens ): string => strtoupper( $tokens['own'] ) ],
			'id s nulou navíc'           => [ static fn( array $tokens ): string => '0' . $tokens['own'] ],
			'podpis spočítaný bez klíče' => [ static fn( array $tokens ): string => $id( $tokens['own'] ) . '.' . hash( 'sha256', 'prefill|' . $id( $tokens['own'] ) ) ],
			'prázdný'                    => [ '' ],
			'číslo'                      => [ 1 ],
		];
	}

	/**
	 * @dataProvider forged_tokens
	 */
	public function test_forged_or_modified_link_is_refused( mixed $forge ): void {
		$own         = $this->booked_prefill_token();
		$this->mails = [];
		$other       = $this->booked_prefill_token( 'eva@example.test', '10:00' );
		$token       = is_callable( $forge )
			? $forge(
				[
					'own'   => $own,
					'other' => $other,
				]
			)
			: $forge;

		$response = $this->prefill( $token );

		$this->assertSame( 404, $response->get_status() );
		$this->assertSame( 'prefill.invalid_token', $response->get_data()['code'] );
	}

	public function test_link_stops_working_once_the_booking_is_anonymised(): void {
		$token = $this->booked_prefill_token();

		Pneukarnik_Clock::freeze( '2030-01-01 12:00' );
		do_action( Pneukarnik_GDPR::CRON_HOOK );

		$this->assertSame( 404, $this->prefill( $token )->get_status() );
	}

	/**
	 * Vytvoří Rezervaci (s uskladněnými koly) a vrátí token z odkazu „Objednat znovu“ v potvrzovacím e‑mailu.
	 *
	 * @param list<int>|null $services Služby Rezervace, null = jen přezutí.
	 */
	private function booked_prefill_token( string $email = self::CUSTOMER, string $time = '08:00', ?array $services = null ): string {
		$this->created_booking(
			$this->book(
				$services ?? $this->tyres,
				self::MONDAY,
				$time,
				[
					'email'         => $email,
					'company'       => 'Novák s.r.o.',
					'vehicle'       => 'Škoda Octavia',
					'note'          => 'Tajná poznámka',
					'stored_wheels' => true,
				]
			)
		);
		return $this->prefill_token_from( $this->mail_to( $email ) );
	}
}
