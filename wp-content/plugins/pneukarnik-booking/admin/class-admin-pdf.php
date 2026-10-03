<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PDF export: daily booking schedule.
 * Handles admin-post action pneukarnik_export_day_pdf.
 * Requires lib/fpdf/fpdf.php (FPDF v1.86, přibalené v pluginu).
 * Czech characters: transliterated to ISO-8859-1 (ASCII compatible).
 */
class Pneukarnik_Admin_Pdf {

	public static function handle_export(): void {
		if ( ! current_user_can( 'pneukarnik_view_bookings' ) && ! current_user_can( 'pneukarnik_manage_bookings' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Nemáte oprávnění.', 'pneukarnik-booking' ) );
		}

		check_admin_referer( 'pneukarnik_export_day_pdf', 'pneukarnik_pdf_nonce' );

		$fpdf_path = PNEUKARNIK_PLUGIN_DIR . 'lib/fpdf/fpdf.php';
		if ( ! file_exists( $fpdf_path ) ) {
			wp_die( 'FPDF není nainstalováno. Stáhněte fpdf.php z fpdf.org a umístěte do ' . esc_html( $fpdf_path ) );
		}
		require_once $fpdf_path;

		$raw  = sanitize_text_field( $_POST['pdf_date'] ?? '' );
		$date = $raw ? pneukarnik_parse_date_cz( $raw ) : '';
		if ( ! $date ) {
			$date = Pneukarnik_Clock::today()->format( 'Y-m-d' );
		}

		$bookings = self::get_day_bookings( $date );

		$pdf = new FPDF( 'L', 'mm', 'A4' );
		$pdf->AddPage();
		$pdf->SetMargins( 15, 15, 15 );
		$pdf->SetAutoPageBreak( true, 15 );

		// Header
		$pdf->SetFont( 'Helvetica', 'B', 14 );
		$pdf->Cell( 0, 8, self::enc( get_bloginfo( 'name' ) . ' — Rezervace ' . pneukarnik_format_date( $date ) ), 0, 1, 'C' );
		$pdf->SetFont( 'Helvetica', '', 9 );
		$pdf->Cell( 0, 5, self::enc( 'Celkem: ' . count( $bookings ) . ' rezervaci' ), 0, 1, 'C' );
		$pdf->Ln( 3 );

		// Column widths (A4 landscape usable ~267mm)
		$cols = [
			'cas'      => 22,
			'sluzba'   => 50,
			'zakaznik' => 45,
			'spz'      => 22,
			'telefon'  => 28,
			'firma'    => 40,
			'poznamka' => 60,
		];

		// Table header
		$pdf->SetFont( 'Helvetica', 'B', 8 );
		$pdf->SetFillColor( 230, 230, 230 );
		$pdf->Cell( $cols['cas'], 6, 'Cas', 1, 0, 'C', true );
		$pdf->Cell( $cols['sluzba'], 6, 'Sluzba', 1, 0, 'C', true );
		$pdf->Cell( $cols['zakaznik'], 6, 'Zakaznik', 1, 0, 'C', true );
		$pdf->Cell( $cols['spz'], 6, 'SPZ', 1, 0, 'C', true );
		$pdf->Cell( $cols['telefon'], 6, 'Telefon', 1, 0, 'C', true );
		$pdf->Cell( $cols['firma'], 6, 'Firma', 1, 0, 'C', true );
		$pdf->Cell( $cols['poznamka'], 6, 'Poznamka', 1, 1, 'C', true );

		// Rows
		$pdf->SetFont( 'Helvetica', '', 8 );
		$pdf->SetFillColor( 255, 255, 255 );

		foreach ( $bookings as $b ) {
			$time    = substr( $b['time_start'], 0, 5 );
			$service = get_the_title( (int) $b['service_id'] );
			$name    = $b['customer_name'];
			$plate   = $b['customer_plate'];
			$phone   = $b['customer_phone'];
			$company = $b['customer_company'] ?? '';
			$note    = $b['customer_note'] ?? '';

			// Row height — use MultiCell for note column, calculate max height
			$line_h = 5;

			$pdf->Cell( $cols['cas'], $line_h, self::enc( $time ), 1, 0 );
			$pdf->Cell( $cols['sluzba'], $line_h, self::enc( $service ), 1, 0 );
			$pdf->Cell( $cols['zakaznik'], $line_h, self::enc( $name ), 1, 0 );
			$pdf->Cell( $cols['spz'], $line_h, self::enc( $plate ), 1, 0 );
			$pdf->Cell( $cols['telefon'], $line_h, self::enc( $phone ), 1, 0 );
			$pdf->Cell( $cols['firma'], $line_h, self::enc( $company ), 1, 0 );
			$pdf->Cell( $cols['poznamka'], $line_h, self::enc( $note ), 1, 1 );
		}

		$filename = 'rezervace-' . $date . '.pdf';

		if ( ob_get_length() ) {
			ob_end_clean();
		}

		$pdf->Output( 'D', $filename );
		exit;
	}

	private static function get_day_bookings( string $date ): array {
		global $wpdb;
		$table = Pneukarnik_DB::bookings_table();
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE booking_date = %s AND status = 'CONFIRMED' ORDER BY time_start ASC",
				$table,
				$date
			),
			ARRAY_A
		) ?: [];
	}

	private static function enc( string $str ): string {
		return iconv( 'UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $str ) ?: $str;
	}
}
