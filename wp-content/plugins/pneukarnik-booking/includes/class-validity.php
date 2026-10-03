<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Platnost od–do obsahu s omezenou dobou (Akce, Oznámení).
 * Platí od začátku dne „od“ do konce dne „do“ v Europe/Prague podle Pneukarnik_Clock.
 * Dny jsou uložené v meta jako YYYY-MM-DD.
 */
final class Pneukarnik_Validity {

	/**
	 * Den jako YYYY-MM-DD, prázdný řetězec pro neplatné datum.
	 */
	public static function date( string $value ): string {
		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $value );
		return $date && $date->format( 'Y-m-d' ) === $value ? $value : '';
	}

	/**
	 * Podmínka pro get_posts( meta_query ): obsah, který dnes platí.
	 *
	 * @return list<array{key:string,value:string,compare:string,type:string}>
	 */
	public static function current_meta_query( string $from_key, string $to_key ): array {
		$today = Pneukarnik_Clock::today()->format( 'Y-m-d' );
		return [
			[
				'key'     => $from_key,
				'value'   => $today,
				'compare' => '<=',
				'type'    => 'DATE',
			],
			[
				'key'     => $to_key,
				'value'   => $today,
				'compare' => '>=',
				'type'    => 'DATE',
			],
		];
	}

	/**
	 * Co platnosti chybí ke zveřejnění.
	 *
	 * @return list<string> Názvy chybějících částí pro hlášku v administraci.
	 */
	public static function missing( string $from, string $to ): array {
		$missing = [];
		if ( '' === $from ) {
			$missing[] = __( 'platnost od', 'pneukarnik-booking' );
		}
		if ( '' === $to ) {
			$missing[] = __( 'platnost do', 'pneukarnik-booking' );
		}
		if ( '' !== $from && '' !== $to && $to < $from ) {
			$missing[] = __( 'platnost do nejdřív v den začátku', 'pneukarnik-booking' );
		}
		return $missing;
	}

	/**
	 * Sloupec Platnost v přehledu administrace: dny a stav (naplánované / platí / skončilo).
	 */
	public static function render_admin_cell( string $from, string $to ): void {
		if ( '' === $from || '' === $to ) {
			echo '—';
			return;
		}
		$today = Pneukarnik_Clock::today()->format( 'Y-m-d' );
		$state = match ( true ) {
			$today < $from => __( 'naplánováno', 'pneukarnik-booking' ),
			$today > $to   => __( 'skončilo', 'pneukarnik-booking' ),
			default        => __( 'platí', 'pneukarnik-booking' ),
		};
		printf(
			'%s – %s<br><small>%s</small>',
			esc_html( Pneukarnik_Clock::at( $from )->format( 'j. n. Y' ) ),
			esc_html( Pneukarnik_Clock::at( $to )->format( 'j. n. Y' ) ),
			esc_html( $state )
		);
	}
}
