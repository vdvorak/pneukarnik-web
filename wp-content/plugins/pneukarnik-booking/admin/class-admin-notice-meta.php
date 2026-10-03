<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Formulář Oznámení v administraci (text, platnost, „zobrazit i u rezervace“) a sloupce v jejich přehledu.
 */
class Pneukarnik_Admin_Notice_Meta {

	public static function init(): void {
		add_action( 'add_meta_boxes_' . Pneukarnik_Notice::POST_TYPE, [ self::class, 'register_meta_box' ] );
		add_action( 'save_post_' . Pneukarnik_Notice::POST_TYPE, [ self::class, 'save' ] );
		add_filter( 'manage_' . Pneukarnik_Notice::POST_TYPE . '_posts_columns', [ self::class, 'columns' ] );
		add_action( 'manage_' . Pneukarnik_Notice::POST_TYPE . '_posts_custom_column', [ self::class, 'render_column' ], 10, 2 );
	}

	public static function register_meta_box(): void {
		add_meta_box(
			'pneukarnik_notice_meta',
			__( 'Oznámení', 'pneukarnik-booking' ),
			[ self::class, 'render' ],
			Pneukarnik_Notice::POST_TYPE,
			'normal',
			'high'
		);
	}

	public static function render( WP_Post $post ): void {
		wp_nonce_field( 'pneukarnik_notice_meta_save', 'pneukarnik_notice_meta_nonce' );
		$notice = Pneukarnik_Notice::from_post( $post );
		?>
		<p><?php esc_html_e( 'Název nahoře je nadpis Oznámení. Pole označená * jsou povinná pro zveřejnění. Oznámení se zobrazí jen v době platnosti (oba dny včetně), pak samo zmizí. Když jich platí víc, nahoře na webu je to s nejpozdějším začátkem.', 'pneukarnik-booking' ); ?></p>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="pnk-notice-text"><?php esc_html_e( 'Text *', 'pneukarnik-booking' ); ?></label></th>
				<td><textarea id="pnk-notice-text" name="_notice_text" rows="3" class="large-text"><?php echo esc_textarea( $notice->text ); ?></textarea></td>
			</tr>
			<tr>
				<th><label for="pnk-notice-from"><?php esc_html_e( 'Platí od *', 'pneukarnik-booking' ); ?></label></th>
				<td><input id="pnk-notice-from" type="date" name="_notice_valid_from" value="<?php echo esc_attr( $notice->valid_from ); ?>"></td>
			</tr>
			<tr>
				<th><label for="pnk-notice-to"><?php esc_html_e( 'Platí do *', 'pneukarnik-booking' ); ?></label></th>
				<td><input id="pnk-notice-to" type="date" name="_notice_valid_to" value="<?php echo esc_attr( $notice->valid_to ); ?>"></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Zobrazení', 'pneukarnik-booking' ); ?></th>
				<td><label><input type="checkbox" name="_notice_at_booking" value="1" <?php checked( $notice->at_booking ); ?>> <?php esc_html_e( 'Zobrazit i u rezervace (např. podmínky pro Leasingové zákazníky)', 'pneukarnik-booking' ); ?></label></td>
			</tr>
		</table>
		<?php
	}

	public static function save( int $post_id ): void {
		if ( ! isset( $_POST['pneukarnik_notice_meta_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pneukarnik_notice_meta_nonce'] ) ), 'pneukarnik_notice_meta_save' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Neplatné datum se neuloží a Oznámení pak nejde zveřejnit.
		update_post_meta( $post_id, '_notice_text', sanitize_textarea_field( wp_unslash( $_POST['_notice_text'] ?? '' ) ) );
		foreach ( [ '_notice_valid_from', '_notice_valid_to' ] as $field ) {
			update_post_meta( $post_id, $field, Pneukarnik_Validity::date( sanitize_text_field( wp_unslash( $_POST[ $field ] ?? '' ) ) ) );
		}
		update_post_meta( $post_id, '_notice_at_booking', empty( $_POST['_notice_at_booking'] ) ? '' : '1' );
	}

	/**
	 * @param array<string, string> $columns
	 * @return array<string, string>
	 */
	public static function columns( array $columns ): array {
		$date = $columns['date'] ?? null;
		unset( $columns['date'] );
		$columns['pnk_valid']      = __( 'Platnost', 'pneukarnik-booking' );
		$columns['pnk_at_booking'] = __( 'U rezervace', 'pneukarnik-booking' );
		if ( null !== $date ) {
			$columns['date'] = $date;
		}
		return $columns;
	}

	public static function render_column( string $column, int $post_id ): void {
		$notice = Pneukarnik_Notice::find( $post_id );
		if ( ! $notice ) {
			return;
		}
		switch ( $column ) {
			case 'pnk_valid':
				Pneukarnik_Validity::render_admin_cell( $notice->valid_from, $notice->valid_to );
				break;
			case 'pnk_at_booking':
				echo esc_html( $notice->at_booking ? __( 'ano', 'pneukarnik-booking' ) : '—' );
				break;
		}
	}
}
