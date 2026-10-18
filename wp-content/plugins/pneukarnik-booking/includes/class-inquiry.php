<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Poptávka (viz CONTEXT.md): Zákazník z formuláře „Poptat pneumatiky“ v detailu Služby pošle rozměr,
 * letní/zimní, počet kusů a kontakt, Provozovatel se mu ozve s nabídkou. Na webu se neukládá, jen
 * odejde e‑mailem Provozovateli s Reply-To na Zákazníka. Zákazník žádný e‑mail nedostane.
 */
final class Pneukarnik_Inquiry {

	/** Kotva formuláře v detailu Služby. */
	public const ANCHOR = 'poptavka';

	public const MAX_PIECES = 20;

	/** Skryté pole proti robotům: člověk ho nevidí a nevyplní. */
	public const HONEYPOT = 'website';

	private const MAX_LENGTH = [
		'size'  => 60,
		'name'  => 120,
		'phone' => 30,
		'email' => 254,
		'note'  => 1000,
	];

	/**
	 * @return array<string, string> letní/zimní => název pro web a e‑mail.
	 */
	public static function seasons(): array {
		return [
			'summer' => __( 'Letní', 'pneukarnik-booking' ),
			'winter' => __( 'Zimní', 'pneukarnik-booking' ),
		];
	}

	/**
	 * Je Poptávka na webu? Jen když ji některá zveřejněná Služba nabízí.
	 */
	public static function enabled(): bool {
		return null !== Pneukarnik_Service::for_inquiry();
	}

	/**
	 * Ověří Poptávku a pošle ji Provozovateli.
	 *
	 * Vstup: size (rozměr), season (summer, winter), pieces (1..MAX_PIECES), name, phone a/nebo email,
	 * volitelně note. Vyplněný HONEYPOT = robot: tváří se jako odeslaná, nic neodejde.
	 *
	 * @param array<mixed> $data Neověřený vstup.
	 * @return array{ok:true,sent:bool}|array{ok:false,code:string,status:int,errors?:array<string,string>}
	 */
	public static function submit( array $data ): array {
		[ $input, $errors ] = self::validate( $data );
		if ( $errors ) {
			return [
				'ok'     => false,
				'code'   => 'inquiry.invalid_fields',
				'status' => 422,
				'errors' => $errors,
			];
		}
		$honeypot = $data[ self::HONEYPOT ] ?? '';
		if ( ! is_scalar( $honeypot ) || '' !== trim( (string) $honeypot ) ) {
			return [
				'ok'   => true,
				'sent' => false,
			];
		}
		if ( ! self::email( $input )->send( Pneukarnik_Notifications::provozovatel_email(), $input['email'] ) ) {
			return [
				'ok'     => false,
				'code'   => 'inquiry.not_sent',
				'status' => 500,
			];
		}
		return [
			'ok'   => true,
			'sent' => true,
		];
	}

	/**
	 * Ověří a znormalizuje pole. Chyby po polích: required, invalid, too_long, u telefonu a e‑mailu
	 * contact_required, když chybí obojí.
	 *
	 * @param array<mixed> $data
	 * @return array{0:array{size:string,season:string,pieces:int,name:string,phone:string,email:string,note:string},1:array<string,string>}
	 */
	private static function validate( array $data ): array {
		$errors = [];
		$text   = static function ( string $field, bool $multiline = false ) use ( $data, &$errors ): string {
			$value = $data[ $field ] ?? null;
			if ( null === $value ) {
				return '';
			}
			if ( ! is_scalar( $value ) ) {
				$errors[ $field ] = 'invalid';
				return '';
			}
			return trim( $multiline ? sanitize_textarea_field( (string) $value ) : sanitize_text_field( (string) $value ) );
		};
		$check  = static function ( string $field, string $value, bool $required, ?callable $valid = null ) use ( &$errors ): void {
			if ( isset( $errors[ $field ] ) ) {
				return;
			}
			if ( '' === $value ) {
				if ( $required ) {
					$errors[ $field ] = 'required';
				}
				return;
			}
			if ( isset( self::MAX_LENGTH[ $field ] ) && mb_strlen( $value ) > self::MAX_LENGTH[ $field ] ) {
				$errors[ $field ] = 'too_long';
				return;
			}
			if ( $valid && ! $valid( $value ) ) {
				$errors[ $field ] = 'invalid';
			}
		};

		// Rozměr se píše různě (205/55 R16 91H, 195/75 R16C, 120/70 ZR17), stačí, že v něm jsou čísla.
		$size = $text( 'size' );
		$check( 'size', $size, true, static fn( string $v ): bool => (bool) preg_match( '/\d{2}/', $v ) );

		$season = $text( 'season' );
		$check( 'season', $season, true, static fn( string $v ): bool => isset( self::seasons()[ $v ] ) );

		$pieces = $data['pieces'] ?? null;
		if ( null === $pieces || '' === $pieces ) {
			$errors['pieces'] = 'required';
		} elseif ( ! ( is_int( $pieces ) || ( is_string( $pieces ) && ctype_digit( $pieces ) ) ) || (int) $pieces < 1 || (int) $pieces > self::MAX_PIECES ) {
			$errors['pieces'] = 'invalid';
		}

		$name = $text( 'name' );
		$check( 'name', $name, true );

		$phone = $text( 'phone' );
		$check( 'phone', $phone, false, [ Pneukarnik_Booking::class, 'valid_phone' ] );

		$email = $text( 'email' );
		$check( 'email', $email, false, static fn( string $v ): bool => (bool) is_email( $v ) );

		if ( '' === $phone && '' === $email && ! isset( $errors['phone'] ) && ! isset( $errors['email'] ) ) {
			$errors['phone'] = 'contact_required';
			$errors['email'] = 'contact_required';
		}

		$note = $text( 'note', true );
		$check( 'note', $note, false );

		return [
			[
				'size'   => $size,
				'season' => $season,
				'pieces' => isset( $errors['pieces'] ) ? 0 : (int) $pieces,
				'name'   => $name,
				'phone'  => $phone,
				'email'  => $email,
				'note'   => $note,
			],
			$errors,
		];
	}

	/**
	 * @param array{size:string,season:string,pieces:int,name:string,phone:string,email:string,note:string} $input
	 */
	private static function email( array $input ): Pneukarnik_Email {
		$season = self::seasons()[ $input['season'] ];
		/* translators: %d: počet kusů */
		$pieces = sprintf( __( '%d ks', 'pneukarnik-booking' ), $input['pieces'] );

		/* translators: 1: rozměr, 2: letní/zimní, 3: počet kusů, 4: jméno Zákazníka */
		$email = ( new Pneukarnik_Email( sprintf( __( 'Poptávka pneumatik: %1$s, %2$s, %3$s, %4$s', 'pneukarnik-booking' ), $input['size'], mb_strtolower( $season ), $pieces, $input['name'] ) ) )
			->heading( __( 'Nová poptávka pneumatik', 'pneukarnik-booking' ) )
			->details(
				[
					__( 'Rozměr', 'pneukarnik-booking' )   => $input['size'],
					__( 'Pneumatiky', 'pneukarnik-booking' ) => $season,
					__( 'Počet kusů', 'pneukarnik-booking' ) => (string) $input['pieces'],
					__( 'Jméno', 'pneukarnik-booking' )    => $input['name'],
					__( 'Telefon', 'pneukarnik-booking' )  => $input['phone'],
					__( 'E‑mail', 'pneukarnik-booking' )   => $input['email'],
					__( 'Poznámka', 'pneukarnik-booking' ) => $input['note'],
				]
			)
			->paragraph(
				'' !== $input['email']
					? __( 'Na tento e‑mail stačí odpovědět, odpověď dojde Zákazníkovi.', 'pneukarnik-booking' )
					: __( 'Zákazník nechal jen telefon, ozvěte se mu prosím telefonicky.', 'pneukarnik-booking' )
			);
		if ( '' !== $input['email'] ) {
			/* translators: %s: rozměr pneumatik */
			$subject = sprintf( __( 'Nabídka pneumatik %s', 'pneukarnik-booking' ), $input['size'] );
			$email->button( __( 'Odpovědět e‑mailem', 'pneukarnik-booking' ), 'mailto:' . $input['email'] . '?subject=' . rawurlencode( $subject ), Pneukarnik_Email::BUTTON_DARK );
		}
		if ( '' !== $input['phone'] ) {
			/* translators: %s: telefon Zákazníka */
			$email->link( sprintf( __( 'Zavolat %s', 'pneukarnik-booking' ), $input['phone'] ), 'tel:' . preg_replace( '/[^\d+]/', '', $input['phone'] ) );
		}
		return $email;
	}
}
