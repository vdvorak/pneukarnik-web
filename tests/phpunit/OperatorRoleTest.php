<?php
/**
 * Role Provozovatel: spravuje Služby a stránky jako Editor, Rezervace a provoz (Nastavení, Výjimky,
 * E‑maily Zákazníkům), ale ne pluginy, uživatele ani Převod ze starého webu. Technik s rolí
 * Rezervace – prohlížení se do provozu nedostane.
 */

declare(strict_types=1);

class OperatorRoleTest extends Pneukarnik_REST_Test_Case {

	/** Stránky provozu v menu Rezervace. */
	private const OPERATIONS_PAGES = [ 'pneukarnik-settings', Pneukarnik_Admin_Day_Exceptions::PAGE, Pneukarnik_Admin_Customer_Emails::PAGE ];

	public function test_operator_edits_content_and_bookings_but_not_the_site(): void {
		$this->log_in_as( 'pneukarnik_operator' );

		foreach ( [ 'edit_pages', 'edit_others_posts', 'publish_posts', 'upload_files', 'manage_categories', Pneukarnik_Access::VIEW, Pneukarnik_Access::MANAGE, Pneukarnik_Access::OPERATE ] as $cap ) {
			$this->assertTrue( current_user_can( $cap ), $cap );
		}
		foreach ( [ 'manage_options', 'activate_plugins', 'switch_themes', 'list_users' ] as $cap ) {
			$this->assertFalse( current_user_can( $cap ), $cap );
		}
	}

	public function test_operator_sees_operations_pages_and_not_the_legacy_import(): void {
		$this->log_in_as( 'pneukarnik_operator' );
		$pages = $this->menu_pages();

		foreach ( self::OPERATIONS_PAGES as $page ) {
			$this->assertContains( $page, $pages );
		}
		$this->assertNotContains( Pneukarnik_Admin_Legacy_Import::PAGE, $pages );
	}

	public function test_operator_opens_the_settings(): void {
		$this->log_in_as( 'pneukarnik_operator' );

		ob_start();
		Pneukarnik_Admin_Settings::render_page();
		$this->assertStringContainsString( 'pnk-emergency-text', (string) ob_get_clean() );
	}

	public function test_technician_only_views_bookings(): void {
		$this->log_in_as( 'pneukarnik_viewer' );

		$this->assertSame( [ Pneukarnik_Admin_Calendar::PAGE, Pneukarnik_Admin_Bookings::PAGE ], $this->menu_pages() );
		$this->assertFalse( current_user_can( Pneukarnik_Access::MANAGE ) );
		$this->assertFalse( current_user_can( 'edit_posts' ) );
	}

	public function test_administrator_keeps_the_operations_pages(): void {
		$this->log_in_as( 'administrator' );

		$this->assertTrue( current_user_can( Pneukarnik_Access::OPERATE ) );
	}

	/**
	 * Stránky v menu Rezervace, které WordPress přihlášenému ukáže (bez oprávnění je do menu nepřidá).
	 *
	 * @return list<string>
	 */
	private function menu_pages(): array {
		global $submenu;
		unset( $submenu[ Pneukarnik_Admin_Calendar::PAGE ] );
		pneukarnik_register_admin_menus();

		return array_column( $submenu[ Pneukarnik_Admin_Calendar::PAGE ] ?? [], 2 );
	}
}
