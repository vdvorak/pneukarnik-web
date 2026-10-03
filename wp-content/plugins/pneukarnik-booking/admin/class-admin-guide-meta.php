<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pole Průvodce v administraci (perex, Služba pro odkaz na rezervaci) a sloupec Služby v přehledu.
 * Text Průvodce se píše v editoru, pořadí v boxu „Atributy“ (menu_order).
 */
class Pneukarnik_Admin_Guide_Meta {

	public static function init(): void {
		add_action( 'add_meta_boxes_' . Pneukarnik_Guide::POST_TYPE, [ self::class, 'register_meta_box' ] );
		add_action( 'save_post_' . Pneukarnik_Guide::POST_TYPE, [ self::class, 'save' ] );
		add_filter( 'manage_' . Pneukarnik_Guide::POST_TYPE . '_posts_columns', [ self::class, 'columns' ] );
		add_action( 'manage_' . Pneukarnik_Guide::POST_TYPE . '_posts_custom_column', [ self::class, 'render_column' ], 10, 2 );
	}

	public static function register_meta_box(): void {
		add_meta_box(
			'pneukarnik_guide_meta',
			__( 'Průvodce', 'pneukarnik-booking' ),
			[ self::class, 'render' ],
			Pneukarnik_Guide::POST_TYPE,
			'normal',
			'high'
		);
	}

	public static function render( WP_Post $post ): void {
		wp_nonce_field( 'pneukarnik_guide_meta_save', 'pneukarnik_guide_meta_nonce' );
		$guide    = Pneukarnik_Guide::from_post( $post );
		$services = get_posts(
			[
				'post_type'      => Pneukarnik_Service::POST_TYPE,
				'post_status'    => [ 'publish', 'future', 'draft', 'pending', 'private' ],
				'posts_per_page' => -1,
				'orderby'        => Pneukarnik_Service::order_by(),
			]
		);
		?>
		<p><?php esc_html_e( 'Pole označená * jsou povinná pro zveřejnění, stejně jako text v editoru.', 'pneukarnik-booking' ); ?></p>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="pnk-guide-perex"><?php esc_html_e( 'Perex *', 'pneukarnik-booking' ); ?></label></th>
				<td>
					<textarea id="pnk-guide-perex" name="_guide_perex" rows="2" class="large-text"><?php echo esc_textarea( $guide->perex ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Jedna až dvě věty pod nadpisem: na jakou otázku Průvodce odpovídá.', 'pneukarnik-booking' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="pnk-guide-service"><?php esc_html_e( 'Služba pro rezervaci *', 'pneukarnik-booking' ); ?></label></th>
				<td>
					<select id="pnk-guide-service" name="_guide_service_id">
						<option value=""><?php esc_html_e( '— vyberte —', 'pneukarnik-booking' ); ?></option>
						<?php foreach ( $services as $service ) : ?>
							<option value="<?php echo (int) $service->ID; ?>" <?php selected( $guide->service_id, $service->ID ); ?>><?php echo esc_html( get_the_title( $service ) ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Průvodce končí tlačítkem Rezervovat s touto Službou. U Služby jen na telefon nabídne Zavolat.', 'pneukarnik-booking' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="pnk-guide-seo-title"><?php esc_html_e( 'Titulek pro vyhledávače', 'pneukarnik-booking' ); ?></label></th>
				<td>
					<input id="pnk-guide-seo-title" type="text" name="_guide_seo_title" value="<?php echo esc_attr( $guide->seo_title ); ?>" class="large-text">
					<p class="description"><?php esc_html_e( 'Celý titulek ve výsledcích Googlu. Prázdné = „název Průvodce – název firmy“.', 'pneukarnik-booking' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="pnk-guide-seo-description"><?php esc_html_e( 'Popis pro vyhledávače', 'pneukarnik-booking' ); ?></label></th>
				<td>
					<textarea id="pnk-guide-seo-description" name="_guide_seo_description" rows="2" class="large-text"><?php echo esc_textarea( $guide->seo_description ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Text pod titulkem ve výsledcích Googlu, nejlépe do 160 znaků. Prázdné = perex.', 'pneukarnik-booking' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	public static function save( int $post_id ): void {
		if ( ! isset( $_POST['pneukarnik_guide_meta_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pneukarnik_guide_meta_nonce'] ) ), 'pneukarnik_guide_meta_save' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Neplatná Služba se neuloží a Průvodce pak nejde zveřejnit.
		$service_id = absint( $_POST['_guide_service_id'] ?? 0 );
		update_post_meta( $post_id, '_guide_service_id', $service_id > 0 && Pneukarnik_Service::find( $service_id ) ? $service_id : '' );
		update_post_meta( $post_id, '_guide_perex', sanitize_textarea_field( wp_unslash( $_POST['_guide_perex'] ?? '' ) ) );
		update_post_meta( $post_id, '_guide_seo_title', sanitize_text_field( wp_unslash( $_POST['_guide_seo_title'] ?? '' ) ) );
		update_post_meta( $post_id, '_guide_seo_description', sanitize_text_field( wp_unslash( $_POST['_guide_seo_description'] ?? '' ) ) );
	}

	/**
	 * @param array<string, string> $columns
	 * @return array<string, string>
	 */
	public static function columns( array $columns ): array {
		$date = $columns['date'] ?? null;
		unset( $columns['date'] );
		$columns['pnk_service'] = __( 'Služba pro rezervaci', 'pneukarnik-booking' );
		if ( null !== $date ) {
			$columns['date'] = $date;
		}
		return $columns;
	}

	public static function render_column( string $column, int $post_id ): void {
		if ( 'pnk_service' !== $column ) {
			return;
		}
		$guide = Pneukarnik_Guide::find( $post_id );
		$id    = $guide ? $guide->service_id : 0;
		echo esc_html( $id > 0 ? ( Pneukarnik_Service::find( $id )->title ?? '—' ) : '—' );
	}
}
