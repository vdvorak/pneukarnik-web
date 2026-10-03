<?php
/**
 * PDF denní přehled pro dílnu: jen s oprávněním, potvrzené Rezervace dne, čeština v PDF,
 * den bez Rezervací dá prázdný přehled.
 */

declare(strict_types=1);

class DaySheetTest extends Pneukarnik_REST_Test_Case {

	private const MONDAY = '2027-03-01';

	private int $tyres;

	public function set_up(): void {
		parent::set_up();
		Pneukarnik_Clock::freeze( '2027-02-20 12:00' );
		$this->set_working_hours_every_day(
			[
				[
					'from' => '08:00',
					'to'   => '12:00',
				],
			]
		);
		$this->set_booking_rules( 60 );
		$this->tyres = $this->create_service( 60, false, 'Přezutí' );
	}

	public function test_sheet_lists_confirmed_bookings_of_the_day_in_czech(): void {
		$this->book(
			$this->tyres,
			self::MONDAY,
			'09:00',
			[
				'name'            => 'Jiří Šťastný',
				'company'         => 'Účetnictví Říha s.r.o.',
				'plate'           => '1AB 2345',
				'vehicle'         => 'Škoda Octavia',
				'phone'           => '603 123 456',
				'note'            => 'Přiveze kola, ať je připravíte. Žluťoučký kůň úpěl ďábelské ódy.',
				'leasing'         => true,
				'leasing_company' => 'ČSOB Leasing',
			]
		);
		$cancelled = $this->created_booking( $this->book( $this->tyres, self::MONDAY, '10:00', [ 'name' => 'Zrušený Zákazník' ] ) );
		Pneukarnik_Cancellation::cancel_by_provozovatel( (int) $cancelled['id'], null );
		$this->book( $this->tyres, '2027-03-02', '09:00', [ 'name' => 'Jiný Den' ] );
		$this->log_in_as( 'pneukarnik_viewer' );

		$response = $this->rest( 'GET', '/admin/day-sheet', [ 'date' => self::MONDAY ] );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'application/pdf', $response->get_headers()['Content-Type'] );
		$this->assertSame( 'inline; filename="rezervace-2027-03-01.pdf"', $response->get_headers()['Content-Disposition'] );
		$this->assertSame( 'no-store', $response->get_headers()['Cache-Control'] );
		$pdf = (string) $response->get_data();
		$this->assertStringStartsWith( '%PDF-', $pdf );
		$this->assertStringContainsString( '+DejaVuSansCondensed', $pdf, 'font s češtinou je vložený' );
		$text = self::text_of( $pdf );
		foreach ( [ 'Rezervace – pondělí 1. 3. 2027', 'Pracovní doba 8:00–12:00', '9:00–10:00', 'Přezutí', 'Jiří Šťastný', 'Účetnictví Říha s.r.o.', '1AB2345', 'Škoda Octavia', '603 123 456', 'Leasing: ČSOB Leasing', 'Žluťoučký kůň' ] as $expected ) {
			$this->assertStringContainsString( self::cp1250( $expected ), $text, $expected );
		}
		$this->assertStringNotContainsString( self::cp1250( 'Zrušený Zákazník' ), $text );
		$this->assertStringNotContainsString( self::cp1250( 'Jiný Den' ), $text );
	}

	public function test_day_without_bookings_gives_an_empty_sheet(): void {
		$this->log_in_as( 'pneukarnik_viewer' );

		$response = $this->rest( 'GET', '/admin/day-sheet', [ 'date' => '2027-03-26' ] );

		$this->assertSame( 200, $response->get_status() );
		$text = self::text_of( (string) $response->get_data() );
		$this->assertStringContainsString( self::cp1250( 'Na tento den nejsou žádné rezervace.' ), $text );
		$this->assertStringContainsString( self::cp1250( 'Zavřeno \\(Velký pátek\\)' ), $text, 'závorky FPDF escapuje' );
	}

	public function test_busy_day_continues_on_more_pages(): void {
		$this->log_in_as( 'administrator' );
		$short = $this->create_service( 15, false, 'Kontrola tlaku' );
		for ( $i = 0; $i < 40; $i++ ) {
			$this->admin_booking( $this->admin_book( $short, self::MONDAY, sprintf( '%02d:%02d', 6 + intdiv( $i * 15, 60 ), ( $i * 15 ) % 60 ), [ 'outside_working_hours' => true ] ) );
		}

		$pdf = (string) $this->rest( 'GET', '/admin/day-sheet', [ 'date' => self::MONDAY ] )->get_data();

		$this->assertMatchesRegularExpression( '~/Count [2-9]~', $pdf );
		$this->assertStringContainsString( self::cp1250( 'strana 2/' ), self::text_of( $pdf ) );
	}

	public function test_invalid_date_is_refused(): void {
		$this->log_in_as( 'pneukarnik_viewer' );

		$response = $this->rest( 'GET', '/admin/day-sheet', [ 'date' => '2027-02-30' ] );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'day_sheet.invalid_date', $response->get_data()['code'] );
	}

	public function test_sheet_needs_permission(): void {
		$this->book( $this->tyres, self::MONDAY, '09:00', [ 'name' => 'Jiří Šťastný' ] );

		$this->assertSame( 401, $this->rest( 'GET', '/admin/day-sheet', [ 'date' => self::MONDAY ] )->get_status() );
		$this->log_in_as( 'subscriber' );
		$response = $this->rest( 'GET', '/admin/day-sheet', [ 'date' => self::MONDAY ] );
		$this->assertSame( 403, $response->get_status() );
		$this->assertStringNotContainsString( 'Jiří', (string) wp_json_encode( $response->get_data() ) );
	}

	/**
	 * Obsah všech proudů PDF (FPDF je komprimuje zlibem), v nich je text stránek v cp1250.
	 */
	private static function text_of( string $pdf ): string {
		preg_match_all( "~stream\n(.*?)\nendstream~s", $pdf, $streams );
		$text = '';
		foreach ( $streams[1] as $stream ) {
			$decoded = @gzuncompress( $stream ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- nekomprimovaný proud vezmeme jak je.
			$text   .= false === $decoded ? $stream : $decoded;
		}
		return $text;
	}

	private static function cp1250( string $text ): string {
		return (string) iconv( 'UTF-8', 'CP1250', $text );
	}
}
