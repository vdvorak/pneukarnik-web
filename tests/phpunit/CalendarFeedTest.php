<?php
/**
 * iCal feed Rezervací: text od Zákazníka nesmí změnit strukturu kalendáře.
 */

declare(strict_types=1);

class CalendarFeedTest extends Pneukarnik_REST_Test_Case {

	public function test_customer_text_is_escaped_and_cannot_inject_events(): void {
		Pneukarnik_Clock::freeze( '2027-02-26 12:00' );
		$this->set_working_hours_every_day(
			[
				[
					'from' => '08:00',
					'to'   => '12:00',
				],
			]
		);
		$this->set_booking_rules( 30 );
		$injection = "Pozor\r\nEND:VEVENT\r\nBEGIN:VEVENT\r\nSUMMARY:Podvrh; a, b\\c";
		$this->assertSame(
			201,
			$this->book( $this->create_service( 60 ), '2027-03-01', '09:00', [ 'note' => $injection ] )->get_status()
		);
		update_option( 'pneukarnik_ical_token', 'tajny-token' );

		$ical = (string) $this->rest( 'GET', '/calendar', [ 'token' => 'tajny-token' ] )->get_data();

		$this->assertSame( 1, substr_count( $ical, "\r\nBEGIN:VEVENT\r\n" ) );
		$unfolded = str_replace( "\r\n ", '', $ical );
		$this->assertStringContainsString( 'Poznámka: Pozor\nEND:VEVENT\nBEGIN:VEVENT\nSUMMARY:Podvrh\; a\, b\\\\c', $unfolded );
	}
}
