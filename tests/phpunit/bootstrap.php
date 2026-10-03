<?php
/**
 * Bootstrap PHPUnit nad WordPress test suite. Načte plugin pneukarnik-booking
 * jako mu-plugin.
 */

declare(strict_types=1);

require dirname( __DIR__, 2 ) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';

$_tests_dir = getenv( 'WP_PHPUNIT__DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = dirname( __DIR__, 2 ) . '/vendor/wp-phpunit/wp-phpunit';
}

require_once $_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function (): void {
		require dirname( __DIR__, 2 ) . '/wp-content/plugins/pneukarnik-booking/pneukarnik-booking.php';
	}
);

// Tabulky a oprávnění pluginu vytvoří jeho vlastní Pneukarnik_DB::maybe_upgrade na plugins_loaded.
require $_tests_dir . '/includes/bootstrap.php';

require __DIR__ . '/class-rest-test-case.php';
