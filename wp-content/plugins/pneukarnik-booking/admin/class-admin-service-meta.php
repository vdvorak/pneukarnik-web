<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Meta box for pneukarnik_service CPT — all service-specific fields.
 */
class Pneukarnik_Admin_Service_Meta {

	public static function init(): void {
		add_action( 'add_meta_boxes', [ self::class, 'register_meta_box' ] );
		add_action( 'save_post_pneukarnik_service', [ self::class, 'save' ] );
	}

	public static function register_meta_box(): void {
		add_meta_box(
			'pneukarnik_service_meta',
			__( 'Detaily služby', 'pneukarnik-booking' ),
			[ self::class, 'render' ],
			'pneukarnik_service',
			'normal',
			'high'
		);
	}

	public static function render( WP_Post $post ): void {
		wp_nonce_field( 'pneukarnik_service_meta_save', 'pneukarnik_service_meta_nonce' );
		$id = $post->ID;
		?>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Ikona (FA třída, např. fa-car)', 'pneukarnik-booking' ); ?></th>
				<td><input type="text" name="_service_icon" value="<?php echo esc_attr( get_post_meta( $id, '_service_icon', true ) ); ?>" style="width:300px"></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Trvání (minuty)', 'pneukarnik-booking' ); ?></th>
				<td><input type="number" name="_service_duration" value="<?php echo (int) get_post_meta( $id, '_service_duration', true ); ?>" min="15" max="480"></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Cena (Kč)', 'pneukarnik-booking' ); ?></th>
				<td><input type="number" name="_service_price" value="<?php echo esc_attr( get_post_meta( $id, '_service_price', true ) ); ?>" step="0.01" min="0"></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Zobrazit cenu', 'pneukarnik-booking' ); ?></th>
				<td><input type="checkbox" name="_service_show_price" value="1" <?php checked( get_post_meta( $id, '_service_show_price', true ) ); ?>></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Cena po slevě (Kč)', 'pneukarnik-booking' ); ?></th>
				<td><input type="number" name="_service_price_sale" value="<?php echo esc_attr( get_post_meta( $id, '_service_price_sale', true ) ); ?>" step="0.01" min="0"></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Ve slevě', 'pneukarnik-booking' ); ?></th>
				<td><input type="checkbox" name="_service_is_sale" value="1" <?php checked( get_post_meta( $id, '_service_is_sale', true ) ); ?>></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Rezervovatelné online', 'pneukarnik-booking' ); ?></th>
				<td><input type="checkbox" name="_service_bookable" value="1" <?php checked( get_post_meta( $id, '_service_bookable', true ) ); ?>></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Pořadí (sort_index)', 'pneukarnik-booking' ); ?></th>
				<td><input type="number" name="_service_index" value="<?php echo esc_attr( get_post_meta( $id, '_service_index', true ) ); ?>" min="0"></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Autoservis (ne pneuservis)', 'pneukarnik-booking' ); ?></th>
				<td><input type="checkbox" name="_service_is_autoservice" value="1" <?php checked( get_post_meta( $id, '_service_is_autoservice', true ) ); ?>></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Sezónní (pouze v sezóně)', 'pneukarnik-booking' ); ?></th>
				<td><input type="checkbox" name="_service_is_seasonal" value="1" <?php checked( get_post_meta( $id, '_service_is_seasonal', true ) ); ?>></td>
			</tr>
		</table>
		<?php
	}

	public static function save( int $post_id ): void {
		if ( ! isset( $_POST['pneukarnik_service_meta_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pneukarnik_service_meta_nonce'] ) ), 'pneukarnik_service_meta_save' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$bool_fields = [ '_service_show_price', '_service_is_sale', '_service_bookable', '_service_is_autoservice', '_service_is_seasonal' ];
		foreach ( $bool_fields as $field ) {
			update_post_meta( $post_id, $field, ! empty( $_POST[ $field ] ) ? '1' : '' );
		}

		$text_fields = [ '_service_icon' ];
		foreach ( $text_fields as $field ) {
			update_post_meta( $post_id, $field, sanitize_text_field( wp_unslash( $_POST[ $field ] ?? '' ) ) );
		}

		$number_fields = [ '_service_duration', '_service_index' ];
		foreach ( $number_fields as $field ) {
			update_post_meta( $post_id, $field, (int) ( $_POST[ $field ] ?? 0 ) );
		}

		$float_fields = [ '_service_price', '_service_price_sale' ];
		foreach ( $float_fields as $field ) {
			$val = isset( $_POST[ $field ] ) && $_POST[ $field ] !== '' ? (float) $_POST[ $field ] : '';
			update_post_meta( $post_id, $field, $val );
		}
	}
}
