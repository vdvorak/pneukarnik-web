<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once dirname( __DIR__ ) . '/lib/fpdf/fpdf.php';

// Vlastnosti FPDF (PageBreakTrigger, lMargin, …) mají jména knihovny.
// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

/**
 * PDF denní přehled potvrzených Rezervací k vytištění do dílny: čas, Služby, jméno a firma,
 * SPZ a vozidlo, telefon, poznámka (s leasingem a uskladněnými koly). Den bez Rezervací
 * dá přehled s hláškou. Čeština přes font DejaVu Sans Condensed v cp1250 (lib/fpdf/README.md).
 */
final class Pneukarnik_Day_Sheet extends FPDF {

	private const FONT = 'DejaVu';

	/** Sloupce: nadpis => šířka v mm (A4 na šířku, okraje 12 mm). */
	private const COLUMNS = [
		'Čas'           => 22,
		'Služby'        => 55,
		'Zákazník'      => 50,
		'SPZ / vozidlo' => 34,
		'Telefon'       => 30,
		'Poznámka'      => 82,
	];

	private const LINE = 5;

	private string $date = '';

	/**
	 * PDF přehledu dne jako řetězec.
	 *
	 * @param string $date YYYY-MM-DD
	 */
	public static function render( string $date ): string {
		$sheet       = new self( 'L', 'mm', 'A4' );
		$sheet->date = $date;
		$sheet->SetMargins( 12, 12, 12 );
		$sheet->SetAutoPageBreak( true, 14 );
		$sheet->AddFont( self::FONT, '', 'DejaVuSansCondensed.php' );
		$sheet->AddFont( self::FONT, 'B', 'DejaVuSansCondensed-Bold.php' );
		$sheet->SetTitle( self::cp1250( 'Rezervace ' . pneukarnik_format_day( $date ) ) );
		$sheet->SetCreator( 'pneukarnik-booking' );
		$sheet->AliasNbPages();
		$sheet->AddPage();

		$bookings = Pneukarnik_Booking::confirmed_between( $date, $date );
		if ( ! $bookings ) {
			$sheet->SetFont( self::FONT, '', 11 );
			$sheet->Cell( 0, 10, self::cp1250( 'Na tento den nejsou žádné rezervace.' ), 0, 1 );
		}
		$sheet->SetFont( self::FONT, '', 9 );
		foreach ( $bookings as $booking ) {
			$sheet->row( self::cells( $booking ) );
		}
		return $sheet->Output( 'S' );
	}

	// phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- háčky FPDF.

	/**
	 * Nadpis dne, Pracovní doba a záhlaví tabulky na každé stránce.
	 */
	public function Header(): void {
		$this->SetFont( self::FONT, 'B', 14 );
		$this->Cell( 0, 8, self::cp1250( 'Rezervace – ' . pneukarnik_format_day( $this->date ) ), 0, 0 );
		$this->SetFont( self::FONT, '', 9 );
		$this->Cell( 0, 8, self::cp1250( get_bloginfo( 'name' ) ), 0, 1, 'R' );
		$this->Cell( 0, 5, self::cp1250( self::hours_text( $this->date ) ), 0, 1 );
		$this->Ln( 2 );

		$this->SetFont( self::FONT, 'B', 9 );
		$this->SetFillColor( 230, 230, 230 );
		foreach ( self::COLUMNS as $title => $width ) {
			$this->Cell( $width, 6, self::cp1250( $title ), 1, 0, 'L', true );
		}
		$this->Ln();
		$this->SetFont( self::FONT, '', 9 );
	}

	public function Footer(): void {
		$this->SetY( -10 );
		$this->SetFont( self::FONT, '', 8 );
		$printed = Pneukarnik_Clock::now()->format( 'j. n. Y G:i' );
		$this->Cell( 0, 5, self::cp1250( "Vytištěno {$printed} · strana {$this->PageNo()}/{nb}" ), 0, 0, 'R' );
	}

	// phpcs:enable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid

	/**
	 * Řádek tabulky: buňky zalomené na víc řádků, všechny stejně vysoké.
	 *
	 * @param list<string> $cells Text v UTF‑8 v pořadí COLUMNS.
	 */
	private function row( array $cells ): void {
		$widths = array_values( self::COLUMNS );
		$cells  = array_map( [ self::class, 'cp1250' ], $cells );
		$lines  = 1;
		foreach ( $cells as $i => $text ) {
			$lines = max( $lines, $this->line_count( $widths[ $i ], $text ) );
		}
		$height = self::LINE * $lines;
		if ( $this->GetY() + $height > $this->PageBreakTrigger ) {
			$this->AddPage();
		}
		$y = $this->GetY();
		$x = $this->lMargin;
		foreach ( $cells as $i => $text ) {
			$this->Rect( $x, $y, $widths[ $i ], $height );
			$this->SetXY( $x, $y );
			$this->MultiCell( $widths[ $i ], self::LINE, $text, 0, 'L' );
			$x += $widths[ $i ];
		}
		$this->SetXY( $this->lMargin, $y + $height );
	}

	/**
	 * Kolik řádků zabere text (cp1250) v buňce dané šířky. Stejný algoritmus jako MultiCell
	 * (podle skriptu „Table with MultiCells“ z fpdf.org).
	 */
	private function line_count( float $width, string $text ): int {
		$widths = $this->CurrentFont['cw'];
		$max    = ( $width - 2 * $this->cMargin ) * 1000 / $this->FontSize;
		$text   = rtrim( str_replace( "\r", '', $text ), "\n" );
		$length = strlen( $text );
		$space  = -1;
		$start  = 0;
		$used   = 0;
		$lines  = 1;
		for ( $i = 0; $i < $length; ) {
			$char = $text[ $i ];
			if ( "\n" === $char ) {
				++$i;
				$space = -1;
				$start = $i;
				$used  = 0;
				++$lines;
				continue;
			}
			if ( ' ' === $char ) {
				$space = $i;
			}
			$used += $widths[ $char ] ?? 0;
			if ( $used <= $max ) {
				++$i;
				continue;
			}
			if ( -1 === $space ) {
				$i = $i === $start ? $i + 1 : $i;
			} else {
				$i = $space + 1;
			}
			$space = -1;
			$start = $i;
			$used  = 0;
			++$lines;
		}
		return $lines;
	}

	/**
	 * @param array<string,mixed> $booking
	 * @return list<string>
	 */
	private static function cells( array $booking ): array {
		$time  = static fn( string $hhmm ): string => ltrim( substr( $hhmm, 0, 1 ), '0' ) . substr( $hhmm, 1 );
		$notes = array_filter(
			[
				$booking['leasing'] ? 'Leasing: ' . ( $booking['leasing_company'] ?: 'ano' ) : '',
				$booking['stored_wheels'] ? 'Kola uskladněná u nás' : '',
				(string) $booking['customer_note'],
			]
		);
		return [
			$time( $booking['time_start'] ) . '–' . $time( $booking['time_end'] ),
			implode( "\n", array_column( $booking['services'], 'name' ) ),
			implode( "\n", array_filter( [ $booking['customer_name'], (string) $booking['customer_company'] ] ) ),
			implode( "\n", array_filter( [ $booking['customer_plate'], (string) $booking['vehicle'] ] ) ),
			$booking['customer_phone'],
			implode( "\n", $notes ),
		];
	}

	/**
	 * „Pracovní doba 8:00–12:00, 13:00–17:00“ nebo „Zavřeno (Velký pátek)“.
	 */
	private static function hours_text( string $date ): string {
		$hours     = Pneukarnik_Slot_Engine::resolve_effective_hours( $date );
		$exception = Pneukarnik_Day_Exceptions::for_date( $date );
		$note      = '' !== ( $exception['note'] ?? '' ) ? ' (' . $exception['note'] . ')' : '';
		if ( null === $hours ) {
			return 'Zavřeno' . $note;
		}
		$blocks = array_map(
			static fn( array $block ): string => ltrim( substr( $block['from'], 0, 1 ), '0' ) . substr( $block['from'], 1 ) . '–' . ltrim( substr( $block['to'], 0, 1 ), '0' ) . substr( $block['to'], 1 ),
			$hours
		);
		return 'Pracovní doba ' . implode( ', ', $blocks ) . $note;
	}

	/**
	 * UTF‑8 → cp1250 (kódování fontu). Znaky mimo cp1250 se přepíšou nejbližší podobou.
	 */
	private static function cp1250( string $text ): string {
		$converted = iconv( 'UTF-8', 'CP1250//TRANSLIT', $text );
		return false === $converted ? (string) iconv( 'UTF-8', 'CP1250//IGNORE', $text ) : $converted;
	}
}
