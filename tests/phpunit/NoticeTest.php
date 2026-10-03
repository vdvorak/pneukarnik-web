<?php
/**
 * Oznámení: zobrazení jen v platnosti (oba dny včetně, podle hodin pluginu), nahoře nejnovější
 * podle začátku platnosti, u rezervace všechna platná s volbou „u rezervace“, pravidlo zveřejnění.
 */

declare(strict_types=1);

class NoticeTest extends Pneukarnik_REST_Test_Case {

	/**
	 * @return array<string, array{string, bool}>
	 */
	public static function moments(): array {
		return [
			'den před začátkem'  => [ '2027-07-31 23:59', false ],
			'první den ráno'     => [ '2027-08-01 00:00', true ],
			'poslední den večer' => [ '2027-08-14 23:59', true ],
			'den po konci'       => [ '2027-08-15 00:00', false ],
		];
	}

	/**
	 * @dataProvider moments
	 */
	public function test_notice_is_shown_only_within_validity( string $now, bool $shown ): void {
		$id = $this->notice( '2027-08-01', '2027-08-14', at_booking: true );

		Pneukarnik_Clock::freeze( $now );

		$this->assertSame( $shown ? $id : null, Pneukarnik_Notice::top()?->id );
		$this->assertSame( $shown ? [ $id ] : [], $this->ids( Pneukarnik_Notice::at_booking() ) );
	}

	public function test_top_notice_is_the_newest_by_start_of_validity(): void {
		$this->notice( '2027-07-01', '2027-08-31', 'Sezóna' );
		$newest = $this->notice( '2027-08-01', '2027-08-14', 'Dovolená' );
		$this->notice( '2027-07-15', '2027-08-20', 'Leasing' );
		$this->notice( '2027-08-05', '2027-08-06', 'Skončilo' );

		Pneukarnik_Clock::freeze( '2027-08-10 09:00' );

		$this->assertSame( $newest, Pneukarnik_Notice::top()?->id );
	}

	public function test_top_notice_carries_title_and_text(): void {
		$this->notice( '2027-08-01', '2027-08-14', 'Dovolená', 'Od 1. do 14. 8. máme zavřeno.' );
		Pneukarnik_Clock::freeze( '2027-08-10 09:00' );

		$notice = Pneukarnik_Notice::top();

		$this->assertNotNull( $notice );
		$this->assertSame( 'Dovolená', $notice->title );
		$this->assertSame( 'Od 1. do 14. 8. máme zavřeno.', $notice->text );
	}

	public function test_booking_shows_all_current_notices_marked_for_booking_newest_first(): void {
		$older = $this->notice( '2027-07-01', '2027-08-31', 'Leasing', at_booking: true );
		$this->notice( '2027-08-01', '2027-08-14', 'Dovolená' );
		$newer = $this->notice( '2027-07-15', '2027-08-20', 'Uskladnění', at_booking: true );
		$this->notice( '2027-08-01', '2027-08-05', 'Skončilo', at_booking: true );
		$this->notice( '2027-08-11', '2027-08-20', 'Teprve bude', at_booking: true );

		Pneukarnik_Clock::freeze( '2027-08-10 09:00' );

		$this->assertSame( [ $newer, $older ], $this->ids( Pneukarnik_Notice::at_booking() ) );
	}

	public function test_draft_notice_is_hidden(): void {
		$this->notice( '2027-08-01', '2027-08-14', status: 'draft' );
		Pneukarnik_Clock::freeze( '2027-08-10 09:00' );

		$this->assertNull( Pneukarnik_Notice::top() );
	}

	/**
	 * @return array<string, array{array<string, mixed>}>
	 */
	public static function incomplete_notices(): array {
		return [
			'bez nadpisu'         => [ [ 'post_title' => '' ] ],
			'bez textu'           => [ [ '_notice_text' => ' ' ] ],
			'bez začátku'         => [ [ '_notice_valid_from' => '' ] ],
			'bez konce'           => [ [ '_notice_valid_to' => '' ] ],
			'neplatné datum'      => [ [ '_notice_valid_from' => '1. 8. 2027' ] ],
			'konec před začátkem' => [ [ '_notice_valid_to' => '2027-07-31' ] ],
		];
	}

	/**
	 * @dataProvider incomplete_notices
	 * @param array<string, mixed> $override Pole příspěvku (post_title) nebo meta Oznámení.
	 */
	public function test_incomplete_notice_stays_draft( array $override ): void {
		$post = $this->notice_post( '2027-08-01', '2027-08-14' );
		foreach ( $override as $key => $value ) {
			if ( str_starts_with( $key, '_' ) ) {
				$post['meta_input'][ $key ] = $value;
			} else {
				$post[ $key ] = $value;
			}
		}

		$id = wp_insert_post( $post, true );

		$this->assertIsInt( $id );
		$this->assertSame( 'draft', get_post_status( $id ) );
	}

	private function notice( string $from, string $to, string $title = 'Oznámení', string $text = 'Text.', bool $at_booking = false, string $status = 'publish' ): int {
		$id = wp_insert_post( $this->notice_post( $from, $to, $title, $text, $at_booking, $status ), true );
		$this->assertIsInt( $id );
		$this->assertSame( $status, get_post_status( $id ) );
		return $id;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function notice_post( string $from, string $to, string $title = 'Oznámení', string $text = 'Text.', bool $at_booking = false, string $status = 'publish' ): array {
		return [
			'post_type'   => 'pneukarnik_notice',
			'post_status' => $status,
			'post_title'  => $title,
			'meta_input'  => [
				'_notice_text'       => $text,
				'_notice_valid_from' => $from,
				'_notice_valid_to'   => $to,
				'_notice_at_booking' => $at_booking ? '1' : '',
			],
		];
	}

	/**
	 * @param list<Pneukarnik_Notice> $notices
	 * @return list<int>
	 */
	private function ids( array $notices ): array {
		return array_map( static fn( Pneukarnik_Notice $notice ): int => $notice->id, $notices );
	}
}
