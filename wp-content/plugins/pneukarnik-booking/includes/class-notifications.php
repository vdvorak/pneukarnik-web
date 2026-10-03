<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * E‑maily k Rezervaci: Zákazníkovi potvrzení a Zrušení, Provozovateli nová a zrušená
 * online Rezervace (zapínatelné). Šablony jsou tady, Provozovatel upravuje jen klíčové
 * texty (úvod, podpis, co si vzít s sebou). Chyba odeslání Rezervaci nevrací.
 */
final class Pneukarnik_Notifications {

	public const OPTION_NOTIFY_CREATED   = 'pneukarnik_notify_created';
	public const OPTION_NOTIFY_CANCELLED = 'pneukarnik_notify_cancelled';

	/**
	 * Editovatelné texty e‑mailů: klíč => [popisek v Nastavení, výchozí text].
	 *
	 * @return array<string, array{0:string,1:string}>
	 */
	public static function texts(): array {
		return [
			'intro'     => [ __( 'Úvod potvrzení', 'pneukarnik-booking' ), "Dobrý den,\nděkujeme za rezervaci. Těšíme se na vás." ],
			'bring'     => [ __( 'Co si vzít s sebou (každá položka na řádek)', 'pneukarnik-booking' ), "Technický průkaz vozidla\nKlíč k pojistným šroubům kol" ],
			'signature' => [ __( 'Podpis', 'pneukarnik-booking' ), "S pozdravem\nPneuservis a autoservis Jan Kárník" ],
		];
	}

	public static function text( string $key ): string {
		return (string) get_option( 'pneukarnik_email_' . $key, self::texts()[ $key ][1] ?? '' );
	}

	/**
	 * @param array<string,mixed> $booking      Rezervace (Pneukarnik_Booking::get_by_id).
	 * @param string              $cancel_token Token pro odkaz na Zrušení (v DB je jen jeho hash).
	 */
	public static function on_booking_created( array $booking, string $cancel_token ): void {
		if ( is_email( $booking['customer_email'] ) ) {
			self::customer_confirmation( $booking, $cancel_token )->send( $booking['customer_email'], self::provozovatel_email() );
		}
		if ( Pneukarnik_Booking::SOURCE_WEB === $booking['source'] && self::enabled( self::OPTION_NOTIFY_CREATED ) ) {
			self::provozovatel_created( $booking )->send( self::provozovatel_email(), $booking['customer_email'] );
		}
	}

	/**
	 * @param array<string,mixed> $booking Zrušená Rezervace.
	 * @param bool                $by_customer Zrušil ji Zákazník odkazem (jinak Provozovatel).
	 */
	public static function on_booking_cancelled( array $booking, bool $by_customer ): void {
		if ( is_email( $booking['customer_email'] ) ) {
			self::customer_cancellation( $booking )->send( $booking['customer_email'], self::provozovatel_email() );
		}
		if ( $by_customer && self::enabled( self::OPTION_NOTIFY_CANCELLED ) ) {
			self::provozovatel_cancelled( $booking )->send( self::provozovatel_email(), $booking['customer_email'] );
		}
	}

	/**
	 * @param array<string,mixed> $booking
	 */
	private static function customer_confirmation( array $booking, string $cancel_token ): Pneukarnik_Email {
		$deadline = Pneukarnik_Cancellation::deadline( $booking );
		$phone    = pneukarnik_phone();
		$cancel   = $deadline >= Pneukarnik_Clock::now()
			/* translators: %s: den a čas, do kdy jde Rezervaci zrušit */
			? sprintf( __( 'Když nemůžete přijet, zrušte prosím rezervaci nejpozději %s.', 'pneukarnik-booking' ), $deadline->format( 'j. n. Y \v G:i' ) )
			: __( 'Rezervaci už nejde zrušit online.', 'pneukarnik-booking' );

		/* translators: %s: Termín, např. „pondělí 1. 3. 2027 v 9:00“ */
		return ( new Pneukarnik_Email( self::subject( __( 'Potvrzení rezervace na %s', 'pneukarnik-booking' ), $booking ) ) )
			->heading( __( 'Rezervace přijata', 'pneukarnik-booking' ) )
			->paragraph( self::text( 'intro' ) )
			->details(
				self::visit( $booking ) + [
					__( 'Adresa', 'pneukarnik-booking' ) => trim( (string) get_option( 'pneukarnik_address', '' ) ),
				]
			)
			->items( __( 'Co si vzít s sebou', 'pneukarnik-booking' ), self::bring( $booking ) )
			->paragraph( $cancel )
			->button( __( 'Zrušit rezervaci', 'pneukarnik-booking' ), Pneukarnik_Cancellation::url( $cancel_token ) )
			/* translators: %s: telefon Provozovatele */
			->paragraph( '' !== $phone ? sprintf( __( 'Potřebujete něco změnit? Zavolejte nám na %s.', 'pneukarnik-booking' ), $phone ) : '' )
			->link( __( 'Objednat znovu', 'pneukarnik-booking' ), Pneukarnik_Prefill::url( (int) $booking['id'] ) )
			->paragraph( self::text( 'signature' ) );
	}

	/**
	 * @param array<string,mixed> $booking
	 */
	private static function customer_cancellation( array $booking ): Pneukarnik_Email {
		$reason = trim( (string) ( $booking['cancel_reason'] ?? '' ) );

		/* translators: %s: Termín, např. „pondělí 1. 3. 2027 v 9:00“ */
		return ( new Pneukarnik_Email( self::subject( __( 'Rezervace na %s je zrušená', 'pneukarnik-booking' ), $booking ) ) )
			->heading( __( 'Rezervace zrušena', 'pneukarnik-booking' ) )
			->paragraph( __( 'Vaše rezervace je zrušená a termín jsme uvolnili.', 'pneukarnik-booking' ) )
			/* translators: %s: důvod Zrušení od Provozovatele */
			->paragraph( '' !== $reason ? sprintf( __( 'Důvod: %s', 'pneukarnik-booking' ), $reason ) : '' )
			->details( self::visit( $booking ) )
			->button( __( 'Objednat znovu', 'pneukarnik-booking' ), Pneukarnik_Prefill::url( (int) $booking['id'] ) )
			->paragraph( self::text( 'signature' ) );
	}

	/**
	 * @param array<string,mixed> $booking
	 */
	private static function provozovatel_created( array $booking ): Pneukarnik_Email {
		/* translators: 1: Termín, 2: jméno Zákazníka */
		return ( new Pneukarnik_Email( self::provozovatel_subject( __( 'Nová rezervace: %1$s, %2$s', 'pneukarnik-booking' ), $booking ) ) )
			->heading( __( 'Nová online rezervace', 'pneukarnik-booking' ) )
			->details( self::visit( $booking ) + self::customer( $booking ) )
			->button( __( 'Otevřít v administraci', 'pneukarnik-booking' ), Pneukarnik_Admin_Calendar::url( $booking['booking_date'], (int) $booking['id'] ) );
	}

	/**
	 * @param array<string,mixed> $booking
	 */
	private static function provozovatel_cancelled( array $booking ): Pneukarnik_Email {
		/* translators: 1: Termín, 2: jméno Zákazníka */
		return ( new Pneukarnik_Email( self::provozovatel_subject( __( 'Zrušená rezervace: %1$s, %2$s', 'pneukarnik-booking' ), $booking ) ) )
			->heading( __( 'Zákazník zrušil rezervaci', 'pneukarnik-booking' ) )
			->paragraph( __( 'Zákazník rezervaci zrušil odkazem z e‑mailu. Termín je znovu volný.', 'pneukarnik-booking' ) )
			->details( self::visit( $booking ) + self::customer( $booking ) );
	}

	/**
	 * Co Zákazník a Provozovatel potřebují vědět o návštěvě.
	 *
	 * @param array<string,mixed> $booking
	 * @return array<string, string|list<string>|null>
	 */
	private static function visit( array $booking ): array {
		$services = array_column( $booking['services'], 'name' );
		return [
			__( 'Termín', 'pneukarnik-booking' )          => pneukarnik_format_day( $booking['booking_date'] ) . ', ' . self::time( $booking['time_start'] ) . '–' . self::time( $booking['time_end'] ),
			( count( $services ) > 1 ? __( 'Služby', 'pneukarnik-booking' ) : __( 'Služba', 'pneukarnik-booking' ) ) => $services,
			__( 'SPZ', 'pneukarnik-booking' )             => $booking['customer_plate'],
			__( 'Vozidlo', 'pneukarnik-booking' )         => $booking['vehicle'],
			__( 'Leasing', 'pneukarnik-booking' )         => $booking['leasing'] ? $booking['leasing_company'] : null,
			__( 'Uskladněná kola', 'pneukarnik-booking' ) => $booking['stored_wheels'] ? __( 'ano, připravíme je', 'pneukarnik-booking' ) : null,
		];
	}

	/**
	 * Kontakt na Zákazníka pro Provozovatele.
	 *
	 * @param array<string,mixed> $booking
	 * @return array<string, string|null>
	 */
	private static function customer( array $booking ): array {
		return [
			__( 'Jméno', 'pneukarnik-booking' )    => $booking['customer_name'],
			__( 'Firma', 'pneukarnik-booking' )    => $booking['customer_company'],
			__( 'Telefon', 'pneukarnik-booking' )  => $booking['customer_phone'],
			__( 'E‑mail', 'pneukarnik-booking' )   => $booking['customer_email'],
			__( 'Poznámka', 'pneukarnik-booking' ) => $booking['customer_note'],
		];
	}

	/**
	 * Co si vzít s sebou: obecný blok z Nastavení a položky Služeb Rezervace, bez opakování.
	 *
	 * @param array<string,mixed> $booking
	 * @return list<string>
	 */
	private static function bring( array $booking ): array {
		$items = self::lines( self::text( 'bring' ) );
		foreach ( $booking['services'] as $service ) {
			$current = Pneukarnik_Service::find( (int) $service['service_id'] );
			$items   = array_merge( $items, $current ? $current->bring : [] );
		}
		return array_values( array_unique( $items ) );
	}

	/**
	 * @param array<string,mixed> $booking
	 */
	private static function subject( string $format, array $booking ): string {
		return sprintf( $format, self::termin( $booking, true ) );
	}

	/**
	 * @param array<string,mixed> $booking
	 */
	private static function provozovatel_subject( string $format, array $booking ): string {
		return sprintf( $format, self::termin( $booking ), $booking['customer_name'] );
	}

	/**
	 * „středa 3. 3. 2027 v 9:00“, ve 4. pádě „středu 3. 3. 2027 v 9:00“.
	 *
	 * @param array<string,mixed> $booking
	 */
	private static function termin( array $booking, bool $accusative = false ): string {
		return pneukarnik_format_day( $booking['booking_date'], $accusative ) . ' v ' . self::time( $booking['time_start'] );
	}

	/** „09:00“ → „9:00“ */
	private static function time( string $hhmm ): string {
		[ $hours, $minutes ] = explode( ':', $hhmm ) + [ '0', '00' ];
		return (int) $hours . ':' . $minutes;
	}

	private static function provozovatel_email(): string {
		$email = trim( (string) get_option( 'pneukarnik_email', '' ) );
		return is_email( $email ) ? $email : (string) get_option( 'admin_email' );
	}

	private static function enabled( string $option ): bool {
		return '1' === (string) get_option( $option, '1' );
	}

	/**
	 * @return list<string>
	 */
	private static function lines( string $text ): array {
		return array_values( array_filter( array_map( 'trim', explode( "\n", $text ) ), static fn( string $line ): bool => '' !== $line ) );
	}
}
