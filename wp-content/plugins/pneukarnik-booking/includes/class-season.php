<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sezóny: jarní a podzimní, každá s datem od–do a leasingovým datem (viz CONTEXT.md).
 * Data se ukládají jako MM-DD a opakují se každý rok. Sezóna nepřechází přes Nový rok.
 * Do Sezóny patří Termín podle svého data, hraniční dny od a do včetně.
 */
final class Pneukarnik_Season {

	public const SPRING = 'spring';
	public const AUTUMN = 'autumn';

	private const OPTION = 'pneukarnik_seasons';

	/**
	 * @return array<string, string> Sezóna => název pro administraci.
	 */
	public static function names(): array {
		return [
			self::SPRING => __( 'Jarní', 'pneukarnik-booking' ),
			self::AUTUMN => __( 'Podzimní', 'pneukarnik-booking' ),
		];
	}

	/**
	 * Uložené Sezóny. Prázdné od a do = Sezóna není nastavená, prázdné leasing od = bez omezení.
	 *
	 * @return array<string, array{from:string,to:string,leasing_from:string}> MM-DD
	 */
	public static function get_all(): array {
		$saved   = get_option( self::OPTION, [] );
		$seasons = [];
		foreach ( array_keys( self::names() ) as $name ) {
			$season           = is_array( $saved ) && is_array( $saved[ $name ] ?? null ) ? $saved[ $name ] : [];
			$seasons[ $name ] = [
				'from'         => (string) ( $season['from'] ?? '' ),
				'to'           => (string) ( $season['to'] ?? '' ),
				'leasing_from' => (string) ( $season['leasing_from'] ?? '' ),
			];
		}
		return $seasons;
	}

	/**
	 * Uloží obě Sezóny, nebo nic, když nejsou platné: od i do vyplněné společně, od ≤ do,
	 * leasing od uvnitř Sezóny a Sezóny se nepřekrývají.
	 *
	 * @param array<string, array{from:string,to:string,leasing_from:string}> $seasons MM-DD
	 */
	public static function save( array $seasons ): bool {
		$clean = [];
		foreach ( array_keys( self::names() ) as $name ) {
			$season = [
				'from'         => (string) ( $seasons[ $name ]['from'] ?? '' ),
				'to'           => (string) ( $seasons[ $name ]['to'] ?? '' ),
				'leasing_from' => (string) ( $seasons[ $name ]['leasing_from'] ?? '' ),
			];
			if ( ! self::is_valid( $season ) ) {
				return false;
			}
			$clean[ $name ] = $season;
		}
		$configured = array_values( array_filter( $clean, static fn( array $s ): bool => '' !== $s['from'] ) );
		if ( 2 === count( $configured ) && $configured[0]['from'] <= $configured[1]['to'] && $configured[1]['from'] <= $configured[0]['to'] ) {
			return false;
		}
		update_option( self::OPTION, $clean );
		return true;
	}

	/**
	 * Sezóna, do které patří den, s daty v roce toho dne, nebo null.
	 *
	 * @param string $date YYYY-MM-DD
	 * @return array{name:string,from:string,to:string,leasing_from:string|null}|null YYYY-MM-DD
	 */
	public static function for_date( string $date ): ?array {
		$year      = substr( $date, 0, 4 );
		$month_day = substr( $date, 5 );
		foreach ( self::get_all() as $name => $season ) {
			if ( '' !== $season['from'] && $season['from'] <= $month_day && $month_day <= $season['to'] ) {
				return [
					'name'         => $name,
					'from'         => "{$year}-{$season['from']}",
					'to'           => "{$year}-{$season['to']}",
					'leasing_from' => '' !== $season['leasing_from'] ? "{$year}-{$season['leasing_from']}" : null,
				];
			}
		}
		return null;
	}

	/**
	 * Leasingové pravidlo, jediné místo: v Sezóně musí být datum Termínu ≥ leasingové datum dané Sezóny.
	 * Provozovatel ještě potvrdí, jestli se datum nevztahuje k okamžiku vytvoření
	 * (docs/otazky-pro-klienta.md), pak se změní jen tady.
	 *
	 * @param string $date Datum Termínu YYYY-MM-DD.
	 */
	public static function allows_leasing( string $date ): bool {
		$season = self::for_date( $date );
		return null === $season || null === $season['leasing_from'] || $date >= $season['leasing_from'];
	}

	/**
	 * @param array{from:string,to:string,leasing_from:string} $season
	 */
	private static function is_valid( array $season ): bool {
		if ( '' === $season['from'] && '' === $season['to'] ) {
			return '' === $season['leasing_from'];
		}
		if ( ! self::is_month_day( $season['from'] ) || ! self::is_month_day( $season['to'] ) || $season['from'] > $season['to'] ) {
			return false;
		}
		return '' === $season['leasing_from']
			|| ( self::is_month_day( $season['leasing_from'] ) && $season['from'] <= $season['leasing_from'] && $season['leasing_from'] <= $season['to'] );
	}

	private static function is_month_day( string $value ): bool {
		// Nepřestupný rok: 29. 2. by v ostatních letech nebyl platný den.
		return (bool) preg_match( '/^(\d{2})-(\d{2})$/', $value, $m ) && checkdate( (int) $m[1], (int) $m[2], 2027 );
	}
}
