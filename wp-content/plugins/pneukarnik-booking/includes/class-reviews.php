<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Google recenze pro Úvod: hodnocení, počet hodnocení a tři nejlepší nejnovější recenze.
 *
 * Plánovaná úloha je 1× denně stáhne na serveru z Places API (New) do cache, prohlížeč
 * od Googlu nic nenačítá a klíč API zůstává jen na serveru. Chyba API cache nesmaže,
 * jen se zapíše do stavu pro Nastavení. Při vypnutí se nic nestahuje ani nezobrazuje.
 *
 * @phpstan-type Summary array{rating:float,count:int,url:string,reviews:list<array{author:string,author_url:string,rating:int,text:string,date:string}>}
 */
final class Pneukarnik_Reviews {

	public const CRON_HOOK = 'pneukarnik_reviews_refresh';

	private const OPTION_ENABLED  = 'pneukarnik_reviews_enabled';
	private const OPTION_API_KEY  = 'pneukarnik_reviews_api_key';
	private const OPTION_PLACE_ID = 'pneukarnik_reviews_place_id';
	private const OPTION_CACHE    = 'pneukarnik_reviews_cache';
	private const OPTION_ERROR    = 'pneukarnik_reviews_error';

	private const SHOWN_REVIEWS = 3;

	/** Recenze s nižším hodnocením se na Úvodu neukážou, ani když lepších je málo. */
	private const MIN_RATING = 4;

	private const FIELDS = 'rating,userRatingCount,reviews,googleMapsUri,googleMapsLinks';

	public static function init(): void {
		add_action( self::CRON_HOOK, [ self::class, 'refresh' ] );
		add_action( 'init', [ self::class, 'schedule' ] );
	}

	/**
	 * Denní stažení ve 4:00, naplánuje se samo i u už aktivního pluginu.
	 */
	public static function schedule(): void {
		if ( wp_next_scheduled( self::CRON_HOOK ) ) {
			return;
		}
		wp_schedule_event( Pneukarnik_Clock::next_at( 4 )->getTimestamp(), 'daily', self::CRON_HOOK );
	}

	public static function enabled(): bool {
		return '1' === get_option( self::OPTION_ENABLED, '0' );
	}

	public static function place_id(): string {
		return trim( (string) get_option( self::OPTION_PLACE_ID, '' ) );
	}

	/**
	 * Stáhne recenze do cache. Vypnuté nebo bez klíče a ID místa nedělá nic. Výsledek viz status().
	 */
	public static function refresh(): void {
		$key   = trim( (string) get_option( self::OPTION_API_KEY, '' ) );
		$place = self::place_id();
		if ( ! self::enabled() || '' === $key || '' === $place ) {
			return;
		}

		$response = wp_remote_get(
			'https://places.googleapis.com/v1/places/' . rawurlencode( $place ) . '?languageCode=cs',
			[
				'timeout' => 10,
				// Klíč v hlavičce, ne v adrese, aby se nedostal do logů s adresami požadavků.
				'headers' => [
					'X-Goog-Api-Key'   => $key,
					'X-Goog-FieldMask' => self::FIELDS,
				],
			]
		);
		$data     = self::parse( $response );
		if ( is_string( $data ) ) {
			update_option( self::OPTION_ERROR, $data, false );
			error_log( '[pneukarnik] Google recenze se nestáhly: ' . $data );
			return;
		}

		update_option(
			self::OPTION_CACHE,
			[
				'data'       => $data,
				'updated_at' => Pneukarnik_Clock::now()->format( 'Y-m-d H:i' ),
			]
		);
		update_option( self::OPTION_ERROR, '', false );
	}

	/**
	 * Data pro Úvod z posledního úspěšného stažení, null = vypnuto nebo zatím nic.
	 *
	 * @return Summary|null
	 */
	public static function summary(): ?array {
		if ( ! self::enabled() ) {
			return null;
		}
		$cache = get_option( self::OPTION_CACHE );
		return is_array( $cache ) && is_array( $cache['data'] ?? null ) ? $cache['data'] : null;
	}

	/**
	 * Stav pro Nastavení: kdy se recenze naposledy stáhly a poslední chyba (prázdná = bez chyby).
	 *
	 * @return array{updated_at:string,error:string}
	 */
	public static function status(): array {
		$cache = get_option( self::OPTION_CACHE );
		return [
			'updated_at' => is_array( $cache ) ? (string) ( $cache['updated_at'] ?? '' ) : '',
			'error'      => (string) get_option( self::OPTION_ERROR, '' ),
		];
	}

	/**
	 * Uloží Nastavení recenzí. Prázdný klíč ponechá uložený (formulář ho nikdy nevypisuje).
	 * Po zapnutí nebo změně klíče či místa se recenze rovnou stáhnou, aby Provozovatel hned
	 * viděl výsledek. Uložení beze změny Google nevolá. Recenze jiného místa se zahodí.
	 */
	public static function save_settings( bool $enabled, string $api_key, string $place_id ): void {
		$api_key  = trim( sanitize_text_field( $api_key ) );
		$place_id = trim( sanitize_text_field( $place_id ) );
		$changed  = ( $enabled && ! self::enabled() )
			|| ( '' !== $api_key && get_option( self::OPTION_API_KEY ) !== $api_key )
			|| self::place_id() !== $place_id;

		if ( self::place_id() !== $place_id ) {
			delete_option( self::OPTION_CACHE );
			delete_option( self::OPTION_ERROR );
		}
		update_option( self::OPTION_ENABLED, $enabled ? '1' : '0' );
		if ( '' !== $api_key ) {
			update_option( self::OPTION_API_KEY, $api_key, false );
		}
		update_option( self::OPTION_PLACE_ID, $place_id );
		if ( $enabled && ( $changed || null === self::summary() ) ) {
			self::refresh();
		}
	}

	/** Konec uloženého klíče pro Nastavení (celý klíč se nevypisuje), prázdný bez klíče. */
	public static function api_key_hint(): string {
		$key = (string) get_option( self::OPTION_API_KEY, '' );
		return '' === $key ? '' : '…' . substr( $key, -4 );
	}

	/**
	 * Odpověď Places API → data pro Úvod, nebo popis chyby.
	 *
	 * @param array<string,mixed>|WP_Error $response
	 * @return Summary|string
	 */
	private static function parse( array|WP_Error $response ): array|string {
		if ( is_wp_error( $response ) ) {
			return $response->get_error_message();
		}
		$code  = (int) wp_remote_retrieve_response_code( $response );
		$place = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 200 !== $code ) {
			$message = is_array( $place ) ? (string) ( $place['error']['message'] ?? '' ) : '';
			return trim( 'HTTP ' . $code . ' ' . $message );
		}
		if ( ! is_array( $place ) || ! is_numeric( $place['rating'] ?? null ) ) {
			return __( 'Odpověď Googlu neobsahuje hodnocení.', 'pneukarnik-booking' );
		}

		$reviews = [];
		foreach ( (array) ( $place['reviews'] ?? [] ) as $review ) {
			$text = trim( (string) ( $review['originalText']['text'] ?? $review['text']['text'] ?? '' ) );
			$time = strtotime( (string) ( $review['publishTime'] ?? '' ) );
			if ( '' === $text || false === $time || (float) ( $review['rating'] ?? 0 ) < self::MIN_RATING ) {
				continue;
			}
			$reviews[] = [
				'time'   => $time,
				'review' => [
					'author'     => trim( (string) ( $review['authorAttribution']['displayName'] ?? '' ) ),
					'author_url' => esc_url_raw( (string) ( $review['authorAttribution']['uri'] ?? '' ), [ 'https' ] ),
					'rating'     => (int) round( (float) ( $review['rating'] ?? 0 ) ),
					'text'       => $text,
					'date'       => ( new DateTimeImmutable( '@' . $time ) )->setTimezone( Pneukarnik_Clock::timezone() )->format( 'Y-m-d' ),
				],
			];
		}
		// Nejvyšší hodnocení, mezi stejnými nejnovější.
		usort( $reviews, static fn( array $a, array $b ): int => [ $b['review']['rating'], $b['time'] ] <=> [ $a['review']['rating'], $a['time'] ] );

		return [
			'rating'  => round( (float) $place['rating'], 1 ),
			'count'   => (int) ( $place['userRatingCount'] ?? 0 ),
			'url'     => esc_url_raw( (string) ( $place['googleMapsLinks']['reviewsUri'] ?? $place['googleMapsUri'] ?? '' ), [ 'https' ] ),
			'reviews' => array_column( array_slice( $reviews, 0, self::SHOWN_REVIEWS ), 'review' ),
		];
	}
}
