<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Údaje pro vyhledávače a měření návštěvnosti: title, description a adresa každé stránky,
 * strukturovaná data (LocalBusiness z kontaktů a Pracovní doby v Nastavení, Service z polí Služby),
 * obsah sitemapy, Matomo bez cookies a ověření v Google Search Console.
 * Šablona webu z nich vypisuje meta značky, plugin vlastní data.
 */
final class Pneukarnik_Seo {

	public const OPTION_MATOMO_URL          = 'pneukarnik_matomo_url';
	public const OPTION_MATOMO_SITE_ID      = 'pneukarnik_matomo_site_id';
	public const OPTION_GOOGLE_VERIFICATION = 'pneukarnik_google_verification';

	/** Delší description Google stejně zkrátí. */
	private const DESCRIPTION_LENGTH = 160;

	/** Na kolik dní dopředu jdou do strukturovaných dat Výjimky a svátky. */
	private const SPECIAL_HOURS_DAYS = 30;

	/** Typ podniku ve schema.org: autoservis je podtyp LocalBusiness. */
	private const BUSINESS_TYPE = 'AutoRepair';

	private const SCHEMA_DAYS = [
		'mon' => 'Monday',
		'tue' => 'Tuesday',
		'wed' => 'Wednesday',
		'thu' => 'Thursday',
		'fri' => 'Friday',
		'sat' => 'Saturday',
		'sun' => 'Sunday',
	];

	public static function init(): void {
		add_filter( 'pre_get_document_title', [ self::class, 'document_title' ] );
		add_filter( 'wp_sitemaps_add_provider', [ self::class, 'sitemap_provider' ], 10, 2 );
		add_filter( 'wp_sitemaps_post_types', [ self::class, 'sitemap_post_types' ] );
		add_action( 'wp_sitemaps_init', [ self::class, 'register_sitemap' ] );
	}

	/**
	 * Title, description a kanonická adresa aktuální stránky. Prázdná description = stránka ji nemá
	 * (404, stránky jen pro jednu Rezervaci), prázdná adresa = bez kanonické adresy a og:url.
	 * Null pro stránky mimo mapu webu, ty nechá plugin WordPressu.
	 *
	 * @return array{title:string,description:string,url:string}|null
	 */
	public static function current(): ?array {
		if ( is_404() ) {
			return self::page( __( 'Stránka nenalezena', 'pneukarnik-booking' ), '', '' );
		}

		// Stránky rezervace WordPress považuje i za úvodní stránku, proto dřív než is_front_page().
		$booking_page = Pneukarnik_Booking_Pages::current();
		if ( 'rezervace' === $booking_page ) {
			return self::page( Pneukarnik_Booking_Pages::page_title(), self::booking_description(), home_url( '/rezervace/' ) );
		}
		if ( '' !== $booking_page ) {
			return self::page( Pneukarnik_Booking_Pages::page_title(), '', '' );
		}

		if ( is_front_page() ) {
			$city = Pneukarnik_Contact::postal_address()['city'];
			return [
				'title'       => Pneukarnik_Contact::company() . ( '' !== $city ? ' – ' . $city : '' ),
				'description' => self::front_description(),
				'url'         => home_url( '/' ),
			];
		}

		if ( is_post_type_archive( Pneukarnik_Service::POST_TYPE ) ) {
			$category = (string) get_query_var( Pneukarnik_Service_Type::QUERY_VAR );
			$label    = Pneukarnik_Service::categories()[ $category ] ?? '';
			return '' === $label ? null : self::page( $label, self::category_description( $category ), Pneukarnik_Service::category_url( $category ) );
		}

		$post = get_queried_object();
		if ( ! $post instanceof WP_Post || ! is_singular() ) {
			return null;
		}
		if ( Pneukarnik_Service::POST_TYPE === $post->post_type ) {
			$service = Pneukarnik_Service::from_post( $post );
			return [
				'title'       => self::service_title( $service ),
				'description' => self::service_description( $service ),
				'url'         => $service->url(),
			];
		}
		if ( Pneukarnik_Guide::POST_TYPE === $post->post_type ) {
			$guide = Pneukarnik_Guide::from_post( $post );
			return [
				'title'       => self::guide_title( $guide ),
				'description' => self::guide_description( $guide ),
				'url'         => $guide->url(),
			];
		}
		if ( 'page' === $post->post_type ) {
			return self::page( trim( $post->post_title ), self::page_description( $post ), (string) get_permalink( $post ) );
		}
		return null;
	}

	/**
	 * Title stránky do <title> (filtr pre_get_document_title), prázdný = výchozí WordPressu.
	 */
	public static function document_title( string $title ): string {
		return self::current()['title'] ?? $title;
	}

	public static function service_title( Pneukarnik_Service $service ): string {
		return '' !== $service->seo_title ? $service->seo_title : self::with_company( $service->title );
	}

	public static function service_description( Pneukarnik_Service $service ): string {
		return '' !== $service->seo_description ? $service->seo_description : self::shorten( $service->perex );
	}

	public static function guide_title( Pneukarnik_Guide $guide ): string {
		return '' !== $guide->seo_title ? $guide->seo_title : self::with_company( $guide->title );
	}

	public static function guide_description( Pneukarnik_Guide $guide ): string {
		return '' !== $guide->seo_description ? $guide->seo_description : self::shorten( $guide->perex );
	}

	/**
	 * Strukturovaná data aktuální stránky: LocalBusiness na Úvodu a Kontaktu, Service v detailu Služby.
	 *
	 * @return list<array<string, mixed>>
	 */
	public static function schema(): array {
		if ( is_404() || '' !== Pneukarnik_Booking_Pages::current() ) {
			return [];
		}
		if ( is_front_page() || is_page( 'kontakt' ) ) {
			return [ self::local_business() ];
		}
		$post = get_queried_object();
		if ( $post instanceof WP_Post && is_singular( Pneukarnik_Service::POST_TYPE ) ) {
			return [ self::service_schema( Pneukarnik_Service::from_post( $post ) ) ];
		}
		return [];
	}

	/**
	 * Provozovatel pro Google (schema.org LocalBusiness): kontakty a Pracovní doba z Nastavení
	 * včetně Výjimek a svátků na nejbližší dny. Vyplňuje se jen to, co Provozovatel zadal.
	 *
	 * @return array<string, mixed>
	 */
	public static function local_business(): array {
		$address = Pneukarnik_Contact::postal_address();
		$data    = [
			'@context'                         => 'https://schema.org',
			'@type'                            => self::BUSINESS_TYPE,
			'@id'                              => home_url( '/#provozovatel' ),
			'name'                             => Pneukarnik_Contact::company(),
			'url'                              => home_url( '/' ),
			'telephone'                        => Pneukarnik_Contact::phone(),
			'email'                            => Pneukarnik_Contact::email(),
			'address'                          => '' === $address['street'] ? [] : array_filter(
				[
					'@type'           => 'PostalAddress',
					'streetAddress'   => $address['street'],
					'postalCode'      => $address['postal_code'],
					'addressLocality' => $address['city'],
					'addressCountry'  => 'CZ',
				]
			),
			'hasMap'                           => Pneukarnik_Contact::map_link(),
			'image'                            => (string) get_site_icon_url( 512 ),
			'taxID'                            => Pneukarnik_Contact::ico(),
			'vatID'                            => Pneukarnik_Contact::dic(),
			'openingHoursSpecification'        => self::opening_hours(),
			'specialOpeningHoursSpecification' => self::special_opening_hours(),
			'sameAs'                           => array_column( Pneukarnik_Contact::social(), 'url' ),
		];
		return array_filter( $data, static fn( mixed $value ): bool => '' !== $value && [] !== $value );
	}

	/**
	 * Služba pro Google (schema.org Service) s cenou a Provozovatelem.
	 *
	 * @return array<string, mixed>
	 */
	public static function service_schema( Pneukarnik_Service $service ): array {
		$provider = array_intersect_key( self::local_business(), array_flip( [ '@type', '@id', 'name', 'url', 'telephone', 'address' ] ) );
		$data     = [
			'@context'    => 'https://schema.org',
			'@type'       => 'Service',
			'name'        => $service->title,
			'description' => $service->perex,
			'url'         => $service->url(),
			'serviceType' => $service->category_label(),
			'provider'    => $provider,
		];
		if ( null !== $service->price && ! $service->price_by_vehicle ) {
			$data['offers'] = [
				'@type'         => 'Offer',
				'priceCurrency' => 'CZK',
			] + ( $service->price_from
				? [
					'priceSpecification' => [
						'@type'         => 'PriceSpecification',
						'minPrice'      => $service->price,
						'priceCurrency' => 'CZK',
					],
				]
				: [ 'price' => $service->price ] );
		}
		return $data;
	}

	/**
	 * Matomo pro měření návštěvnosti bez cookies, když ho Provozovatel nastavil, jinak null.
	 *
	 * @return array{url:string,site_id:int}|null url končí lomítkem
	 */
	public static function matomo(): ?array {
		$url     = (string) get_option( self::OPTION_MATOMO_URL, '' );
		$site_id = (int) get_option( self::OPTION_MATOMO_SITE_ID, 0 );
		if ( '' === $url || $site_id <= 0 ) {
			return null;
		}
		return [
			'url'     => trailingslashit( $url ),
			'site_id' => $site_id,
		];
	}

	/** Ověřovací kód Google Search Console pro meta značku, prázdný = neověřuje se značkou. */
	public static function google_verification(): string {
		return (string) get_option( self::OPTION_GOOGLE_VERIFICATION, '' );
	}

	/**
	 * Uloží nastavení měření a ověření z administrace. Do ověření jde vložit i celou meta značku
	 * od Googlu, uloží se jen kód.
	 */
	public static function save_settings( string $matomo_url, string $matomo_site_id, string $google_verification ): void {
		update_option( self::OPTION_MATOMO_URL, esc_url_raw( trim( $matomo_url ), [ 'https', 'http' ] ) );
		update_option( self::OPTION_MATOMO_SITE_ID, max( 0, (int) $matomo_site_id ) ?: '' );
		if ( preg_match( '/content\s*=\s*["\']([^"\']*)["\']/i', $google_verification, $m ) ) {
			$google_verification = $m[1];
		}
		update_option( self::OPTION_GOOGLE_VERIFICATION, (string) preg_replace( '/[^A-Za-z0-9_-]/', '', $google_verification ) );
	}

	/**
	 * Sitemapa má jen stránky, Služby a Průvodce (a rozcestníky s rezervací), ne uživatele ani rubriky.
	 */
	public static function sitemap_provider( WP_Sitemaps_Provider $provider, string $name ): WP_Sitemaps_Provider|false {
		return in_array( $name, [ 'users', 'taxonomies' ], true ) ? false : $provider;
	}

	/**
	 * Typy obsahu v sitemapě: stránky, Služby a Průvodci. Příspěvky starého webu přesměrovává #22.
	 *
	 * @param array<string, WP_Post_Type> $post_types
	 * @return array<string, WP_Post_Type>
	 */
	public static function sitemap_post_types( array $post_types ): array {
		return array_intersect_key( $post_types, array_flip( [ 'page', Pneukarnik_Service::POST_TYPE, Pneukarnik_Guide::POST_TYPE ] ) );
	}

	public static function register_sitemap( WP_Sitemaps $sitemaps ): void {
		$sitemaps->registry->add_provider( Pneukarnik_Sitemap_Provider::NAME, new Pneukarnik_Sitemap_Provider() );
	}

	/**
	 * Adresy stránek webu, které nejsou obsahem WordPressu: rozcestníky Kategorií a rezervace.
	 *
	 * @return list<string>
	 */
	public static function plugin_page_urls(): array {
		$urls = array_map( [ Pneukarnik_Service::class, 'category_url' ], array_keys( Pneukarnik_Service::categories() ) );
		return [ ...$urls, home_url( '/rezervace/' ) ];
	}

	/**
	 * Týdenní Pracovní doba: jeden záznam pro každý blok se dny, kdy platí.
	 *
	 * @return list<array{'@type':string,dayOfWeek:list<string>,opens:string,closes:string}>
	 */
	private static function opening_hours(): array {
		$blocks = [];
		foreach ( Pneukarnik_Working_Hours::get_all() as $day => $hours ) {
			foreach ( $hours ?? [] as $block ) {
				$key = $block['from'] . '-' . $block['to'];
				if ( isset( self::SCHEMA_DAYS[ $day ] ) ) {
					$blocks[ $key ]['opens']       = $block['from'];
					$blocks[ $key ]['closes']      = $block['to'];
					$blocks[ $key ]['dayOfWeek'][] = self::SCHEMA_DAYS[ $day ];
				}
			}
		}
		ksort( $blocks );
		return array_values(
			array_map(
				static fn( array $block ): array => [
					'@type'     => 'OpeningHoursSpecification',
					'dayOfWeek' => $block['dayOfWeek'],
					'opens'     => $block['opens'],
					'closes'    => $block['closes'],
				],
				$blocks
			)
		);
	}

	/**
	 * Výjimky a svátky na nejbližší dny, které mění týdenní Pracovní dobu. Zavřeno = 00:00–00:00.
	 *
	 * @return list<array{'@type':string,validFrom:string,validThrough:string,opens:string,closes:string}>
	 */
	private static function special_opening_hours(): array {
		$special = [];
		foreach ( Pneukarnik_Working_Hours::upcoming( self::SPECIAL_HOURS_DAYS ) as $day ) {
			if ( $day['hours'] === Pneukarnik_Working_Hours::get_for_date( Pneukarnik_Clock::at( $day['date'] ) ) ) {
				continue;
			}
			$blocks = $day['hours'] ?? [
				[
					'from' => '00:00',
					'to'   => '00:00',
				],
			];
			foreach ( $blocks as $block ) {
				$special[] = [
					'@type'        => 'OpeningHoursSpecification',
					'validFrom'    => $day['date'],
					'validThrough' => $day['date'],
					'opens'        => $block['from'],
					'closes'       => $block['to'],
				];
			}
		}
		return $special;
	}

	/**
	 * @return array{title:string,description:string,url:string}
	 */
	private static function page( string $title, string $description, string $url ): array {
		return [
			'title'       => self::with_company( $title ),
			'description' => $description,
			'url'         => $url,
		];
	}

	private static function with_company( string $title ): string {
		return $title . ' – ' . Pneukarnik_Contact::company();
	}

	private static function front_description(): string {
		$offer = array_map( static fn( Pneukarnik_Service $service ): string => $service->title, Pneukarnik_Service::featured() );
		$phone = Pneukarnik_Contact::phone();
		return self::shorten(
			implode( ', ', $offer ?: array_values( Pneukarnik_Service::categories() ) ) . '. '
			. ( '' !== $phone
				/* translators: %s: telefon Provozovatele */
				? sprintf( __( 'Objednejte se online na volný termín, nebo zavolejte %s.', 'pneukarnik-booking' ), $phone )
				: __( 'Objednejte se online na volný termín.', 'pneukarnik-booking' ) )
			. ' ' . Pneukarnik_Contact::address()
		);
	}

	private static function category_description( string $category ): string {
		$services = array_map( static fn( Pneukarnik_Service $service ): string => $service->title, Pneukarnik_Service::in_category( $category ) );
		return self::shorten(
			Pneukarnik_Service::categories()[ $category ] . ( $services ? ': ' . implode( ', ', $services ) : '' ) . '. '
			. __( 'Ceny, co která služba zahrnuje, a online rezervace termínu.', 'pneukarnik-booking' )
		);
	}

	private static function booking_description(): string {
		$phone = Pneukarnik_Contact::phone();
		return self::shorten(
			__( 'Online rezervace termínu bez registrace: vyberte službu a volný termín, potvrzení přijde e‑mailem.', 'pneukarnik-booking' ) . ' '
			. Pneukarnik_Contact::company()
			/* translators: %s: telefon Provozovatele */
			. ( '' !== $phone ? ', ' . sprintf( __( 'tel. %s', 'pneukarnik-booking' ), $phone ) : '' )
		);
	}

	/**
	 * Stránka WordPressu: zadaný úryvek, u Kontaktu kontakty z Nastavení, jinak první odstavec textu.
	 */
	private static function page_description( WP_Post $page ): string {
		if ( '' !== trim( $page->post_excerpt ) ) {
			return self::shorten( $page->post_excerpt );
		}
		if ( 'kontakt' === $page->post_name ) {
			$phone = Pneukarnik_Contact::phone();
			return self::shorten(
				__( 'Adresa, telefon, otevírací doba, mapa a fakturační údaje.', 'pneukarnik-booking' ) . ' '
				. implode(
					', ',
					array_filter(
						[
							Pneukarnik_Contact::company(),
							Pneukarnik_Contact::address(),
							/* translators: %s: telefon Provozovatele */
							'' !== $phone ? sprintf( __( 'tel. %s', 'pneukarnik-booking' ), $phone ) : '',
						]
					)
				)
			);
		}
		$content = excerpt_remove_blocks( $page->post_content );
		preg_match_all( '~<p[^>]*>(.*?)</p>~is', $content, $paragraphs );
		foreach ( [ ...$paragraphs[1], $content ] as $text ) {
			$text = self::shorten( wp_strip_all_tags( str_replace( '<', ' <', $text ) ) );
			if ( '' !== $text ) {
				return $text;
			}
		}
		return '';
	}

	/**
	 * Text na jeden řádek a nejvýš DESCRIPTION_LENGTH znaků, zkrácený na celé slovo.
	 */
	private static function shorten( string $text ): string {
		$text = trim( (string) preg_replace( '/\s+/u', ' ', html_entity_decode( $text, ENT_QUOTES, 'UTF-8' ) ) );
		if ( mb_strlen( $text ) <= self::DESCRIPTION_LENGTH ) {
			return $text;
		}
		$cut   = mb_substr( $text, 0, self::DESCRIPTION_LENGTH - 1 );
		$space = mb_strrpos( $cut, ' ' );
		return rtrim( false === $space ? $cut : mb_substr( $cut, 0, $space ), ' ,.;:–-' ) . '…';
	}
}
