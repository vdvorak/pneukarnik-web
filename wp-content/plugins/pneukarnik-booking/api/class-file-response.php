<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Odpověď REST se souborem (PDF, iCal) místo JSON. Hlavičky pošle WordPress z odpovědi,
 * tělo vypíše serve() místo JSON serializace. Testy přes rest_do_request dostanou tělo v get_data().
 */
final class Pneukarnik_File_Response extends WP_REST_Response {

	/**
	 * @param string $disposition inline (otevřít v prohlížeči) nebo attachment (stáhnout).
	 */
	public function __construct( string $body, string $content_type, string $filename, string $disposition = 'inline' ) {
		parent::__construct( $body, 200 );
		$this->header( 'Content-Type', $content_type );
		$this->header( 'Content-Disposition', sprintf( '%s; filename="%s"', $disposition, $filename ) );
		$this->header( 'Cache-Control', 'no-store' );
		$this->header( 'X-Content-Type-Options', 'nosniff' );
	}

	public static function init(): void {
		add_filter( 'rest_pre_serve_request', [ self::class, 'serve' ], 10, 2 );
	}

	public static function serve( bool $served, WP_HTTP_Response $result ): bool {
		if ( $served || ! $result instanceof self ) {
			return $served;
		}
		echo (string) $result->get_data(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- binární soubor, ne HTML.
		return true;
	}
}
