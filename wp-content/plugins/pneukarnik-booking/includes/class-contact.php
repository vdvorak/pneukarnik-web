<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Kontaktní údaje Provozovatele a Pohotovost z Nastavení: jediný zdroj pro hlavičku, patičku,
 * stránky webu, e‑maily i iCal. Změna v Nastavení se projeví všude.
 */
final class Pneukarnik_Contact {

	public const OPTION_COMPANY           = 'pneukarnik_company';
	public const OPTION_PHONE             = 'pneukarnik_phone';
	public const OPTION_EMAIL             = 'pneukarnik_email';
	public const OPTION_ADDRESS           = 'pneukarnik_address';
	public const OPTION_ICO               = 'pneukarnik_ico';
	public const OPTION_DIC               = 'pneukarnik_dic';
	public const OPTION_MAPS_EMBED_URL    = 'pneukarnik_maps_embed_url';
	public const OPTION_EMERGENCY_ENABLED = 'pneukarnik_emergency_enabled';
	public const OPTION_EMERGENCY_PHONE   = 'pneukarnik_emergency_phone';
	public const OPTION_EMERGENCY_TEXT    = 'pneukarnik_emergency_text';
	private const DEFAULT_COMPANY         = 'Pneuservis a autoservis Jan Kárník';

	/** Název Provozovatele pro web, e‑maily a fakturační údaje. */
	public static function company(): string {
		$company = self::option( self::OPTION_COMPANY );
		return '' !== $company ? $company : self::DEFAULT_COMPANY;
	}

	/** Telefon, jak se má zobrazit (např. „+420 775 565 326“), prázdný = nezadaný. */
	public static function phone(): string {
		return self::option( self::OPTION_PHONE );
	}

	/** Kontaktní e‑mail Provozovatele, prázdný = nezadaný nebo neplatný. */
	public static function email(): string {
		$email = self::option( self::OPTION_EMAIL );
		return is_email( $email ) ? $email : '';
	}

	public static function address(): string {
		return self::option( self::OPTION_ADDRESS );
	}

	public static function ico(): string {
		return self::option( self::OPTION_ICO );
	}

	public static function dic(): string {
		return self::option( self::OPTION_DIC );
	}

	/**
	 * Adresa mapy Google pro vložení (iframe). Bez nastavené adresy z Google Maps se složí z adresy,
	 * bez adresy je prázdná. Načte se až po kliknutí Zákazníka.
	 */
	public static function map_embed_url(): string {
		$url = self::option( self::OPTION_MAPS_EMBED_URL );
		if ( '' !== $url || '' === self::address() ) {
			return $url;
		}
		return 'https://www.google.com/maps?output=embed&q=' . rawurlencode( self::address() );
	}

	/** Odkaz na adresu v Google Maps (otevře se až po kliknutí), prázdný bez adresy. */
	public static function map_link(): string {
		return '' === self::address() ? '' : 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( self::address() );
	}

	/**
	 * Pohotovost, když ji Provozovatel zapnul a zadal telefon, jinak null.
	 *
	 * @return array{phone:string,text:string}|null
	 */
	public static function emergency(): ?array {
		$phone = self::option( self::OPTION_EMERGENCY_PHONE );
		if ( '1' !== self::option( self::OPTION_EMERGENCY_ENABLED ) || '' === $phone ) {
			return null;
		}
		return [
			'phone' => $phone,
			'text'  => self::option( self::OPTION_EMERGENCY_TEXT ),
		];
	}

	private static function option( string $name ): string {
		return trim( (string) get_option( $name, '' ) );
	}
}
