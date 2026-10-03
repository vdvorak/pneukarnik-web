<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Formulář Akce v administraci (Služba, akční cena, popis, platnost) a sloupce v jejich přehledu.
 */
class Pneukarnik_Admin_Promotion_Meta {

	public static function init(): void {
		add_action( 'add_meta_boxes_' . Pneukarnik_Promotion::POST_TYPE, [ self::class, 'register_meta_box' ] );
		add_action( 'save_post_' . Pneukarnik_Promotion::POST_TYPE, [ self::class, 'save' ] );
		add_filter( 'manage_' . Pneukarnik_Promotion::POST_TYPE . '_posts_columns', [ self::class, 'columns' ] );
		add_action( 'manage_' . Pneukarnik_Promotion::POST_TYPE . '_posts_custom_column', [ self::class, 'render_column' ], 10, 2 );
	}

	public static function register_meta_box(): void {
		add_meta_box(
			'pneukarnik_promotion_meta',
			__( 'Akce', 'pneukarnik-booking' ),
			[ self::class, 'render' ],
			Pneukarnik_Promotion::POST_TYPE,
			'normal',
			'high'
		);
	}

	public static function render( WP_Post $post ): void {
		wp_nonce_field( 'pneukarnik_promotion_meta_save', 'pneukarnik_promotion_meta_nonce' );
		$promotion = Pneukarnik_Promotion::from_post( $post );
		$services  = get_posts(
			[
				'post_type'      => Pneukarnik_Service::POST_TYPE,
				'post_status'    => [ 'publish', 'future', 'draft', 'pending', 'private' ],
				'posts_per_page' => -1,
				'orderby'        => Pneukarnik_Service::order_by(),
			]
		);
		?>
		<p><?php esc_html_e( 'Pole označená * jsou povinná pro zveřejnění. Akce se u Služby zobrazí jen v době platnosti (oba dny včetně), pak sama zmizí.', 'pneukarnik-booking' ); ?></p>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="pnk-promotion-service"><?php esc_html_e( 'Služba *', 'pneukarnik-booking' ); ?></label></th>
				<td>
					<select id="pnk-promotion-service" name="_promotion_service_id">
						<option value=""><?php esc_html_e( '— vyberte —', 'pneukarnik-booking' ); ?></option>
						<?php foreach ( $services as $service ) : ?>
							<option value="<?php echo (int) $service->ID; ?>" <?php selected( $promotion->service_id, $service->ID ); ?>><?php echo esc_html( get_the_title( $service ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="pnk-promotion-price"><?php esc_html_e( 'Akční cena (Kč) *', 'pneukarnik-booking' ); ?></label></th>
				<td><input id="pnk-promotion-price" type="number" name="_promotion_price" value="<?php echo esc_attr( (string) ( $promotion->price ?? '' ) ); ?>" min="1" step="1"></td>
			</tr>
			<tr>
				<th><label for="pnk-promotion-description"><?php esc_html_e( 'Popis', 'pneukarnik-booking' ); ?></label></th>
				<td>
					<textarea id="pnk-promotion-description" name="_promotion_description" rows="3" class="large-text"><?php echo esc_textarea( $promotion->description ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Pro zákazníka, např. co akční cena zahrnuje nebo pro koho platí.', 'pneukarnik-booking' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="pnk-promotion-from"><?php esc_html_e( 'Platí od *', 'pneukarnik-booking' ); ?></label></th>
				<td><input id="pnk-promotion-from" type="date" name="_promotion_valid_from" value="<?php echo esc_attr( $promotion->valid_from ); ?>"></td>
			</tr>
			<tr>
				<th><label for="pnk-promotion-to"><?php esc_html_e( 'Platí do *', 'pneukarnik-booking' ); ?></label></th>
				<td><input id="pnk-promotion-to" type="date" name="_promotion_valid_to" value="<?php echo esc_attr( $promotion->valid_to ); ?>"></td>
			</tr>
		</table>
		<?php
	}

	public static function save( int $post_id ): void {
		if ( ! isset( $_POST['pneukarnik_promotion_meta_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pneukarnik_promotion_meta_nonce'] ) ), 'pneukarnik_promotion_meta_save' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Neplatná hodnota se neuloží a Akce pak nejde zveřejnit.
		$service_id = absint( $_POST['_promotion_service_id'] ?? 0 );
		$price      = (int) ( $_POST['_promotion_price'] ?? 0 );
		update_post_meta( $post_id, '_promotion_service_id', $service_id > 0 && Pneukarnik_Service::find( $service_id ) ? $service_id : '' );
		update_post_meta( $post_id, '_promotion_price', $price > 0 ? $price : '' );
		update_post_meta( $post_id, '_promotion_description', sanitize_textarea_field( wp_unslash( $_POST['_promotion_description'] ?? '' ) ) );
		foreach ( [ '_promotion_valid_from', '_promotion_valid_to' ] as $field ) {
			update_post_meta( $post_id, $field, Pneukarnik_Promotion::date( sanitize_text_field( wp_unslash( $_POST[ $field ] ?? '' ) ) ) );
		}
	}

	/**
	 * @param array<string, string> $columns
	 * @return array<string, string>
	 */
	public static function columns( array $columns ): array {
		$date = $columns['date'] ?? null;
		unset( $columns['date'] );
		$columns['pnk_service'] = __( 'Služba', 'pneukarnik-booking' );
		$columns['pnk_price']   = __( 'Akční cena', 'pneukarnik-booking' );
		$columns['pnk_valid']   = __( 'Platnost', 'pneukarnik-booking' );
		if ( null !== $date ) {
			$columns['date'] = $date;
		}
		return $columns;
	}

	public static function render_column( string $column, int $post_id ): void {
		$promotion = Pneukarnik_Promotion::find( $post_id );
		if ( ! $promotion ) {
			return;
		}
		switch ( $column ) {
			case 'pnk_service':
				echo esc_html( $promotion->service()->title ?? '—' );
				break;
			case 'pnk_price':
				echo esc_html( null === $promotion->price ? '—' : number_format( $promotion->price, 0, ',', "\u{00A0}" ) . "\u{00A0}Kč" );
				break;
			case 'pnk_valid':
				if ( '' === $promotion->valid_from || '' === $promotion->valid_to ) {
					echo '—';
					break;
				}
				$today = Pneukarnik_Clock::today()->format( 'Y-m-d' );
				$state = match ( true ) {
					$today < $promotion->valid_from => __( 'naplánovaná', 'pneukarnik-booking' ),
					$today > $promotion->valid_to   => __( 'skončila', 'pneukarnik-booking' ),
					default                         => __( 'platí', 'pneukarnik-booking' ),
				};
				printf(
					'%s – %s<br><small>%s</small>',
					esc_html( Pneukarnik_Clock::at( $promotion->valid_from )->format( 'j. n. Y' ) ),
					esc_html( Pneukarnik_Clock::at( $promotion->valid_to )->format( 'j. n. Y' ) ),
					esc_html( $state )
				);
				break;
		}
	}
}
