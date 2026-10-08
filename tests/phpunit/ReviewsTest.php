<?php
/**
 * Google recenze: denní stažení z Places API do cache (plánovaná úloha spouštěná přímo),
 * chyba API nesmaže poslední data, při vypnutí se nic nestahuje ani nezobrazuje.
 * Odpověď Googlu podvrhuje háček pre_http_request.
 */

declare(strict_types=1);

class ReviewsTest extends Pneukarnik_REST_Test_Case {

	private const KEY   = 'AIza-tajny-klic-123';
	private const PLACE = 'ChIJ-pneukarnik';

	/** @var list<array{url:string,args:array<string,mixed>}> */
	private array $requests = [];

	/** @var array<string,mixed>|WP_Error */
	private array|WP_Error $response;

	/** Původní cíl error_log: chyby API se v testech záměrně vyvolávají, do výstupu nepatří. */
	private string|false $error_log = false;

	public function set_up(): void {
		parent::set_up();
		Pneukarnik_Clock::freeze( '2027-05-10 03:00' );
		$this->error_log = ini_set( 'error_log', '/dev/null' ); // phpcs:ignore WordPress.PHP.IniSet.Risky
		add_filter( 'pre_http_request', [ $this, 'fake_google' ], 10, 3 );
		$this->response = $this->api_response( 200, $this->place() );
	}

	public function tear_down(): void {
		remove_filter( 'pre_http_request', [ $this, 'fake_google' ] );
		if ( false !== $this->error_log ) {
			ini_set( 'error_log', $this->error_log ); // phpcs:ignore WordPress.PHP.IniSet.Risky
		}
		parent::tear_down();
	}

	public function test_refresh_stores_rating_link_and_three_best_newest_reviews(): void {
		$this->enable();

		do_action( Pneukarnik_Reviews::CRON_HOOK );

		$this->assertSame(
			[
				'rating'  => 4.8,
				'count'   => 123,
				'url'     => 'https://www.google.com/maps/place//data=reviews',
				'cid'     => '1',
				'reviews' => [
					[
						'author'     => 'Eva Nováková',
						'author_url' => 'https://www.google.com/maps/contrib/2',
						'rating'     => 5,
						'text'       => 'Rychlé přezutí, milý personál.',
						'date'       => '2027-05-01',
					],
					[
						'author'     => 'Petr Dvořák',
						'author_url' => 'https://www.google.com/maps/contrib/1',
						'rating'     => 5,
						'text'       => 'Vše v pořádku.',
						'date'       => '2027-04-01',
					],
					[
						'author'     => 'Jana Malá',
						'author_url' => 'https://www.google.com/maps/contrib/4',
						'rating'     => 4,
						'text'       => 'Good price.', // Vlastní slova Zákazníka, ne strojový překlad.
						'date'       => '2027-05-08',
					],
				],
			],
			Pneukarnik_Reviews::summary()
		);
	}

	public function test_reviews_below_four_stars_are_never_shown(): void {
		$place            = $this->place();
		$place['reviews'] = array_values( array_filter( $place['reviews'], static fn( array $review ): bool => 'Petr Dvořák' !== $review['authorAttribution']['displayName'] ) );
		$this->response   = $this->api_response( 200, $place );
		$this->enable();

		do_action( Pneukarnik_Reviews::CRON_HOOK );

		$this->assertSame( [ 'Eva Nováková', 'Jana Malá' ], array_column( Pneukarnik_Reviews::summary()['reviews'] ?? [], 'author' ) );
	}

	public function test_api_key_goes_in_a_header_and_never_into_the_data_for_the_web(): void {
		$this->enable();

		do_action( Pneukarnik_Reviews::CRON_HOOK );

		$this->assertCount( 1, $this->requests );
		$request = $this->requests[0];
		$this->assertStringStartsWith( 'https://places.googleapis.com/v1/places/' . self::PLACE, $request['url'] );
		$this->assertStringNotContainsString( self::KEY, $request['url'] );
		$this->assertSame( self::KEY, $request['args']['headers']['X-Goog-Api-Key'] );
		$this->assertStringNotContainsString( self::KEY, (string) wp_json_encode( Pneukarnik_Reviews::summary() ) );
	}

	/**
	 * @return array<string, array{0: callable(self): (array<string,mixed>|WP_Error)}>
	 */
	public static function failures(): array {
		return [
			'nedostupné API'        => [ static fn() => new WP_Error( 'http_request_failed', 'cURL error 28' ) ],
			'chyba 403'             => [ static fn( self $t ) => $t->api_response( 403, [ 'error' => [ 'message' => 'API key not valid' ] ] ) ],
			'neplatný JSON'         => [ static fn( self $t ) => $t->api_response( 200, '<html>' ) ],
			'odpověď bez hodnocení' => [ static fn( self $t ) => $t->api_response( 200, [ 'reviews' => [] ] ) ],
		];
	}

	/**
	 * @dataProvider failures
	 * @param callable(self): (array<string,mixed>|WP_Error) $failure
	 */
	public function test_api_failure_keeps_the_last_successful_data( callable $failure ): void {
		$this->enable();
		do_action( Pneukarnik_Reviews::CRON_HOOK );
		$before = Pneukarnik_Reviews::summary();

		Pneukarnik_Clock::freeze( '2027-05-11 03:00' );
		$this->response = $failure( $this );
		do_action( Pneukarnik_Reviews::CRON_HOOK );

		$this->assertNotNull( $before );
		$this->assertSame( $before, Pneukarnik_Reviews::summary() );
		$status = Pneukarnik_Reviews::status();
		$this->assertSame( '2027-05-10 03:00', $status['updated_at'] );
		$this->assertNotSame( '', $status['error'] );
	}

	public function test_without_any_successful_download_there_is_nothing_to_show(): void {
		$this->enable();
		$this->response = $this->api_response( 500, [] );

		do_action( Pneukarnik_Reviews::CRON_HOOK );

		$this->assertNull( Pneukarnik_Reviews::summary() );
	}

	public function test_switched_off_downloads_nothing_and_shows_nothing(): void {
		$this->enable();
		do_action( Pneukarnik_Reviews::CRON_HOOK );
		$this->requests = [];

		update_option( 'pneukarnik_reviews_enabled', '0' );
		do_action( Pneukarnik_Reviews::CRON_HOOK );

		$this->assertSame( [], $this->requests );
		$this->assertNull( Pneukarnik_Reviews::summary() );
	}

	public function test_without_key_or_place_nothing_is_downloaded(): void {
		$this->enable();
		update_option( 'pneukarnik_reviews_api_key', '' );

		do_action( Pneukarnik_Reviews::CRON_HOOK );

		$this->assertSame( [], $this->requests );
		$this->assertNull( Pneukarnik_Reviews::summary() );
	}

	public function test_saving_settings_keeps_the_key_when_left_empty_and_downloads_right_away(): void {
		Pneukarnik_Reviews::save_settings( false, self::KEY, self::PLACE );
		$this->assertSame( [], $this->requests );

		Pneukarnik_Reviews::save_settings( true, '', self::PLACE );

		$this->assertSame( self::KEY, get_option( 'pneukarnik_reviews_api_key' ) );
		$this->assertCount( 1, $this->requests );
		$this->assertSame( 123, Pneukarnik_Reviews::summary()['count'] ?? null );
	}

	public function test_saving_unchanged_settings_downloads_nothing(): void {
		Pneukarnik_Reviews::save_settings( true, self::KEY, self::PLACE );
		$this->requests = [];

		Pneukarnik_Reviews::save_settings( true, '', self::PLACE );

		$this->assertSame( [], $this->requests );
	}

	public function test_another_place_drops_the_reviews_of_the_previous_one(): void {
		Pneukarnik_Reviews::save_settings( true, self::KEY, self::PLACE );
		$this->response = new WP_Error( 'http_request_failed', 'cURL error 28' );

		Pneukarnik_Reviews::save_settings( true, '', 'ChIJ-jine-misto' );

		$this->assertNull( Pneukarnik_Reviews::summary() );
	}

	public function test_download_is_scheduled_daily_at_four_in_the_morning(): void {
		wp_clear_scheduled_hook( Pneukarnik_Reviews::CRON_HOOK );

		Pneukarnik_Reviews::schedule(); // Totéž dělá plugin při každém init, i když už je aktivní.

		$this->assertSame( 'daily', wp_get_schedule( Pneukarnik_Reviews::CRON_HOOK ) );
		$this->assertSame( Pneukarnik_Clock::at( '2027-05-10 04:00' )->getTimestamp(), wp_next_scheduled( Pneukarnik_Reviews::CRON_HOOK ) );

		wp_clear_scheduled_hook( Pneukarnik_Reviews::CRON_HOOK );
		Pneukarnik_Clock::freeze( '2027-05-10 04:00' );
		Pneukarnik_Reviews::schedule();

		$this->assertSame( Pneukarnik_Clock::at( '2027-05-11 04:00' )->getTimestamp(), wp_next_scheduled( Pneukarnik_Reviews::CRON_HOOK ) );
	}

	/**
	 * @param array<string,mixed> $args
	 * @return array<string,mixed>|WP_Error|false
	 */
	public function fake_google( mixed $pre, array $args, string $url ): mixed {
		if ( ! str_starts_with( $url, 'https://places.googleapis.com/' ) ) {
			return $pre;
		}
		$this->requests[] = [
			'url'  => $url,
			'args' => $args,
		];
		return $this->response;
	}

	/**
	 * @param array<string,mixed>|string $body
	 * @return array<string,mixed>
	 */
	public function api_response( int $code, array|string $body ): array {
		return [
			'headers'  => [],
			'body'     => is_string( $body ) ? $body : (string) wp_json_encode( $body ),
			'response' => [
				'code'    => $code,
				'message' => '',
			],
			'cookies'  => [],
			'filename' => null,
		];
	}

	private function enable(): void {
		update_option( 'pneukarnik_reviews_enabled', '1' );
		update_option( 'pneukarnik_reviews_api_key', self::KEY );
		update_option( 'pneukarnik_reviews_place_id', self::PLACE );
	}

	/**
	 * Odpověď Places API (New) s pěti recenzemi v pořadí podle relevance, jak je vrací Google.
	 *
	 * @return array<string,mixed>
	 */
	private function place(): array {
		$review = static fn( int $author, float $rating, string $text, string $published, string $original = '' ): array => [
			'rating'            => $rating,
			'text'              => [
				'text'         => $text,
				'languageCode' => 'cs',
			],
			'originalText'      => [
				'text'         => '' !== $original ? $original : $text,
				'languageCode' => 'cs',
			],
			'publishTime'       => $published,
			'authorAttribution' => [
				'displayName' => [
					1 => 'Petr Dvořák',
					2 => 'Eva Nováková',
					3 => 'Karel Bez Textu',
					4 => 'Jana Malá',
					5 => 'Tomáš Nespokojený',
				][ $author ],
				'uri'         => 'https://www.google.com/maps/contrib/' . $author,
				'photoUri'    => 'https://lh3.googleusercontent.com/a/' . $author,
			],
		];
		return [
			'rating'          => 4.8,
			'userRatingCount' => 123,
			'googleMapsUri'   => 'https://maps.google.com/?cid=1',
			'googleMapsLinks' => [ 'reviewsUri' => 'https://www.google.com/maps/place//data=reviews' ],
			'reviews'         => [
				$review( 1, 5, 'Vše v pořádku.', '2027-04-01T09:00:00Z' ),
				$review( 2, 5, 'Rychlé přezutí, milý personál.', '2027-04-30T22:30:00Z' ), // 1. 5. v Praze
				$review( 3, 5, '', '2027-05-09T10:00:00Z' ),
				$review( 4, 4, 'Dobrá cena.', '2027-05-08T10:00:00Z', 'Good price.' ),
				$review( 5, 2, 'Dlouho jsem čekal.', '2027-05-09T12:00:00Z' ),
			],
		];
	}
}
