<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pravidlo zveřejnění obsahových typů (Služba, Akce): obsah bez povinných částí
 * se vrátí do konceptu a administrace po uložení vypíše, co chybí.
 * Běží po uložení polí (priorita 20), platí pro administraci i kód.
 */
final class Pneukarnik_Publish_Guard {

	/** @var array<string, array{missing: callable(int): list<string>, notice: string}> */
	private static array $types = [];

	/** @var array<int, true> Obsah vrácený do konceptu v tomto požadavku (kvůli hlášce po přesměrování). */
	private static array $demoted = [];

	/** Brání zacyklení: vracení do konceptu samo volá save_post. */
	private static bool $demoting = false;

	/**
	 * @param callable(int): list<string> $missing Názvy chybějících částí obsahu s daným ID, prázdné = jde zveřejnit.
	 * @param string                      $notice  Hláška v administraci, %s je seznam chybějících částí.
	 */
	public static function register( string $post_type, callable $missing, string $notice ): void {
		if ( ! self::$types ) {
			add_filter( 'redirect_post_location', [ self::class, 'redirect_after_demotion' ], 10, 2 );
			add_action( 'admin_notices', [ self::class, 'render_notice' ] );
		}
		if ( ! isset( self::$types[ $post_type ] ) ) {
			add_action( 'save_post_' . $post_type, [ self::class, 'enforce' ], 20 );
		}
		self::$types[ $post_type ] = [
			'missing' => $missing,
			'notice'  => $notice,
		];
	}

	public static function enforce( int $post_id ): void {
		if ( self::$demoting || wp_is_post_revision( $post_id ) ) {
			return;
		}
		$rule = self::$types[ (string) get_post_type( $post_id ) ] ?? null;
		if ( ! $rule || ! in_array( get_post_status( $post_id ), [ 'publish', 'future' ], true ) ) {
			return;
		}
		$missing = ( $rule['missing'] )( $post_id );
		if ( ! $missing ) {
			return;
		}
		self::$demoted[ $post_id ] = true;
		self::$demoting            = true;
		try {
			wp_update_post(
				[
					'ID'          => $post_id,
					'post_status' => 'draft',
				]
			);
		} finally {
			self::$demoting = false;
		}
		set_transient( self::notice_key( $post_id ), $missing, MINUTE_IN_SECONDS );
	}

	/**
	 * Po vrácení do konceptu nezobrazovat hlášku WordPressu „publikováno“.
	 */
	public static function redirect_after_demotion( string $location, int $post_id ): string {
		if ( ! isset( self::$demoted[ $post_id ] ) ) {
			return $location;
		}
		return add_query_arg( 'message', 10, $location ); // 10 = „Koncept aktualizován“.
	}

	/**
	 * Hláška v administraci po uložení obsahu, který systém právě vrátil do konceptu.
	 */
	public static function render_notice(): void {
		$screen = get_current_screen();
		$post   = get_post();
		if ( ! $screen || 'post' !== $screen->base || ! $post || ! isset( self::$types[ $post->post_type ] ) ) {
			return;
		}
		$missing = get_transient( self::notice_key( $post->ID ) );
		delete_transient( self::notice_key( $post->ID ) );
		if ( ! is_array( $missing ) || ! $missing ) {
			return;
		}
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html( sprintf( self::$types[ $post->post_type ]['notice'], implode( ', ', $missing ) ) )
		);
	}

	private static function notice_key( int $post_id ): string {
		return 'pnk_demoted_' . $post_id;
	}
}
