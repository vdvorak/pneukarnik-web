<?php
/**
 * Ikona Služby: Provozovatel ji vybírá ve formuláři Služby z pevné sady, nepovinná.
 * Hodnota mimo sadu se neuloží a nevydá.
 */

declare(strict_types=1);

class ServiceIconTest extends Pneukarnik_REST_Test_Case {

	private int $service;

	public function set_up(): void {
		parent::set_up();
		$this->log_in_as( 'administrator' );
		$this->service = $this->create_service( 60, title: 'Uskladnění' );
	}

	public function test_icon_chosen_in_admin_is_given_out_with_the_service(): void {
		$this->save_form( 'warehouse' );

		$this->assertSame( 'warehouse', $this->icon() );
		$this->assertSame( 'publish', get_post_status( $this->service ) );
	}

	public function test_icon_can_be_removed(): void {
		$this->save_form( 'warehouse' );
		$this->save_form( '' );

		$this->assertSame( '', $this->icon() );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function invalid_icons(): array {
		return [
			'mimo sadu'    => [ 'rocket' ],
			'cesta'        => [ '../../functions' ],
			'kus SVG'      => [ '<svg onload=alert(1)>' ],
			'jen podobná'  => [ 'warehouse-2' ],
			'název Služby' => [ 'Sezónní uskladnění' ],
		];
	}

	/**
	 * @dataProvider invalid_icons
	 */
	public function test_icon_outside_the_set_is_rejected( string $icon ): void {
		$this->save_form( 'warehouse' );
		$this->save_form( $icon );

		$this->assertSame( '', $this->icon() );
		$this->assertSame( '', get_post_meta( $this->service, '_service_icon', true ) );
	}

	public function test_stored_icon_outside_the_set_is_not_given_out(): void {
		update_post_meta( $this->service, '_service_icon', 'rocket' );

		$this->assertSame( '', $this->icon() );
	}

	/**
	 * Uloží formulář Služby v administraci se všemi povinnými poli a danou ikonou.
	 */
	private function save_form( string $icon ): void {
		$_POST = [
			'pneukarnik_service_meta_nonce' => wp_create_nonce( 'pneukarnik_service_meta_save' ),
			'_service_category'             => 'pneuservis',
			'_service_perex'                => 'Sezónní uskladnění kol.',
			'_service_price'                => '600',
			'_service_duration'             => '60',
			'_service_icon'                 => $icon,
		];
		wp_update_post( [ 'ID' => $this->service ] );
		$_POST = [];
	}

	private function icon(): string {
		$response = $this->rest( 'GET', '/services/' . get_post_field( 'post_name', $this->service ) );
		$this->assertSame( 200, $response->get_status() );
		return $response->get_data()['icon'];
	}
}
