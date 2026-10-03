<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared email template renderer.
 */
class Pneukarnik_Template {

	public static function render( string $name, array $vars ): string {
		$template = PNEUKARNIK_PLUGIN_DIR . "templates/email/{$name}.php";
		if ( ! file_exists( $template ) ) {
			return '';
		}
		extract( $vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract
		ob_start();
		include $template;
		return ob_get_clean() ?: '';
	}
}
