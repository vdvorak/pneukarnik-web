<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Formulář Služby v administraci: strukturovaná pole místo volného editoru.
 * Pořadí nastavuje WordPress v boxu „Atributy“ (menu_order).
 */
class Pneukarnik_Admin_Service_Meta {

	/** Nejdelší Délka Služby v minutách (jeden pracovní den). */
	private const MAX_DURATION = 480;

	/** Počet prázdných řádků pro nové časté dotazy. */
	private const EMPTY_FAQ_ROWS = 2;

	public static function init(): void {
		add_action( 'add_meta_boxes_' . Pneukarnik_Service::POST_TYPE, [ self::class, 'register_meta_box' ] );
		add_action( 'save_post_' . Pneukarnik_Service::POST_TYPE, [ self::class, 'save' ] );
	}

	public static function register_meta_box(): void {
		add_meta_box(
			'pneukarnik_service_meta',
			__( 'Služba', 'pneukarnik-booking' ),
			[ self::class, 'render' ],
			Pneukarnik_Service::POST_TYPE,
			'normal',
			'high'
		);
	}

	public static function render( WP_Post $post ): void {
		wp_nonce_field( 'pneukarnik_service_meta_save', 'pneukarnik_service_meta_nonce' );
		$service = Pneukarnik_Service::from_post( $post );
		$faq     = $service->faq;
		for ( $i = 0; $i < self::EMPTY_FAQ_ROWS; $i++ ) {
			$faq[] = [
				'question' => '',
				'answer'   => '',
			];
		}
		$others = get_posts(
			[
				'post_type'      => Pneukarnik_Service::POST_TYPE,
				'post_status'    => [ 'publish', 'future', 'draft', 'pending', 'private' ],
				'posts_per_page' => -1,
				'orderby'        => Pneukarnik_Service::order_by(),
				'exclude'        => [ $post->ID ],
			]
		);
		$text   = static fn( string $key ): string => (string) get_post_meta( $post->ID, $key, true );
		?>
		<p><?php esc_html_e( 'Pole označená * jsou povinná pro zveřejnění. Nevyplněné volitelné části se na webu nezobrazí.', 'pneukarnik-booking' ); ?></p>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="pnk-category"><?php esc_html_e( 'Kategorie *', 'pneukarnik-booking' ); ?></label></th>
				<td>
					<select id="pnk-category" name="_service_category">
						<option value=""><?php esc_html_e( '— vyberte —', 'pneukarnik-booking' ); ?></option>
						<?php foreach ( Pneukarnik_Service::categories() as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $service->category, $value ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="pnk-perex"><?php esc_html_e( 'Perex *', 'pneukarnik-booking' ); ?></label></th>
				<td>
					<textarea id="pnk-perex" name="_service_perex" rows="3" class="large-text"><?php echo esc_textarea( $service->perex ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Jedna až dvě věty pod názvem a na kartě v rozcestníku.', 'pneukarnik-booking' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="pnk-includes"><?php esc_html_e( 'Co zahrnuje', 'pneukarnik-booking' ); ?></label></th>
				<td>
					<textarea id="pnk-includes" name="_service_includes" rows="5" class="large-text"><?php echo esc_textarea( $text( '_service_includes' ) ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Každá položka na samostatný řádek.', 'pneukarnik-booking' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="pnk-process"><?php esc_html_e( 'Jak to probíhá', 'pneukarnik-booking' ); ?></label></th>
				<td><textarea id="pnk-process" name="_service_process" rows="5" class="large-text"><?php echo esc_textarea( $service->process ); ?></textarea></td>
			</tr>
			<tr>
				<th><label for="pnk-duration-text"><?php esc_html_e( 'Jak dlouho to trvá', 'pneukarnik-booking' ); ?></label></th>
				<td>
					<input id="pnk-duration-text" type="text" name="_service_duration_text" value="<?php echo esc_attr( $service->duration_text ); ?>" class="regular-text">
					<p class="description"><?php esc_html_e( 'Pro zákazníka, např. „zhruba 30 minut“.', 'pneukarnik-booking' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="pnk-price"><?php esc_html_e( 'Cena (Kč) *', 'pneukarnik-booking' ); ?></label></th>
				<td>
					<input id="pnk-price" type="number" name="_service_price" value="<?php echo esc_attr( (string) ( $service->price ?? '' ) ); ?>" min="1" step="1">
					<label><input type="checkbox" name="_service_price_from" value="1" <?php checked( $service->price_from ); ?>> <?php esc_html_e( 'zobrazit jako „od“', 'pneukarnik-booking' ); ?></label>
					<br>
					<label><input type="checkbox" name="_service_price_by_vehicle" value="1" <?php checked( $service->price_by_vehicle ); ?>> <?php esc_html_e( 'cena dle vozu (místo ceny)', 'pneukarnik-booking' ); ?></label>
				</td>
			</tr>
			<tr>
				<th><label for="pnk-price-note"><?php esc_html_e( 'Co cena zahrnuje', 'pneukarnik-booking' ); ?></label></th>
				<td><input id="pnk-price-note" type="text" name="_service_price_note" value="<?php echo esc_attr( $service->price_note ); ?>" class="large-text"></td>
			</tr>
			<tr>
				<th><label for="pnk-bring"><?php esc_html_e( 'Co si vzít s sebou', 'pneukarnik-booking' ); ?></label></th>
				<td>
					<textarea id="pnk-bring" name="_service_bring" rows="3" class="large-text"><?php echo esc_textarea( $text( '_service_bring' ) ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Každá položka na samostatný řádek.', 'pneukarnik-booking' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Časté dotazy', 'pneukarnik-booking' ); ?></th>
				<td>
					<?php foreach ( $faq as $i => $item ) : ?>
						<p>
							<label>
								<?php
								/* translators: %d: pořadí dotazu */
								echo esc_html( sprintf( __( 'Dotaz %d', 'pneukarnik-booking' ), $i + 1 ) );
								?>
								<input type="text" name="_service_faq[<?php echo (int) $i; ?>][question]" value="<?php echo esc_attr( $item['question'] ); ?>" class="large-text">
							</label>
							<label>
								<?php esc_html_e( 'Odpověď', 'pneukarnik-booking' ); ?>
								<textarea name="_service_faq[<?php echo (int) $i; ?>][answer]" rows="2" class="large-text"><?php echo esc_textarea( $item['answer'] ); ?></textarea>
							</label>
						</p>
					<?php endforeach; ?>
					<p class="description"><?php esc_html_e( 'Dotaz bez odpovědi se neuloží. Další prázdné řádky přibudou po uložení.', 'pneukarnik-booking' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Související služby', 'pneukarnik-booking' ); ?></th>
				<td>
					<?php if ( ! $others ) : ?>
						<p class="description"><?php esc_html_e( 'Zatím žádné další služby.', 'pneukarnik-booking' ); ?></p>
					<?php endif; ?>
					<?php foreach ( $others as $other ) : ?>
						<label style="display:block">
							<input type="checkbox" name="_service_related[]" value="<?php echo (int) $other->ID; ?>" <?php checked( in_array( $other->ID, $service->related_ids, true ) ); ?>>
							<?php echo esc_html( get_the_title( $other ) ); ?>
						</label>
					<?php endforeach; ?>
				</td>
			</tr>
			<tr>
				<th><label for="pnk-duration"><?php esc_html_e( 'Délka (minuty) *', 'pneukarnik-booking' ); ?></label></th>
				<td>
					<input id="pnk-duration" type="number" name="_service_duration" value="<?php echo esc_attr( $service->duration > 0 ? (string) $service->duration : '' ); ?>" min="5" max="480" step="5">
					<p class="description"><?php esc_html_e( 'Jak dlouho Služba zabírá dílnu včetně rezervy. Podle ní se počítají volné termíny.', 'pneukarnik-booking' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Zobrazení a rezervace', 'pneukarnik-booking' ); ?></th>
				<td>
					<label style="display:block"><input type="checkbox" name="_service_bookable" value="1" <?php checked( $service->bookable ); ?>> <?php esc_html_e( 'Rezervovatelná online (jinak jen „Zavolat“)', 'pneukarnik-booking' ); ?></label>
					<label style="display:block"><input type="checkbox" name="_service_is_seasonal" value="1" <?php checked( $service->seasonal ); ?>> <?php esc_html_e( 'Sezónní (v Sezóně jde online rezervovat jen sezónní Služby)', 'pneukarnik-booking' ); ?></label>
					<label style="display:block"><input type="checkbox" name="_service_ask_stored_wheels" value="1" <?php checked( $service->ask_stored_wheels ); ?>> <?php esc_html_e( 'Ptát se na uskladněná kola („Kola mám uskladněná u vás“)', 'pneukarnik-booking' ); ?></label>
					<label style="display:block"><input type="checkbox" name="_service_featured" value="1" <?php checked( $service->featured ); ?>> <?php esc_html_e( 'Nejžádanější (zobrazit na Úvodu)', 'pneukarnik-booking' ); ?></label>
				</td>
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

		$category = sanitize_key( wp_unslash( $_POST['_service_category'] ?? '' ) );
		update_post_meta( $post_id, '_service_category', isset( Pneukarnik_Service::categories()[ $category ] ) ? $category : '' );

		foreach ( [ '_service_perex', '_service_includes', '_service_process', '_service_bring' ] as $field ) {
			update_post_meta( $post_id, $field, sanitize_textarea_field( wp_unslash( $_POST[ $field ] ?? '' ) ) );
		}
		foreach ( [ '_service_duration_text', '_service_price_note' ] as $field ) {
			update_post_meta( $post_id, $field, sanitize_text_field( wp_unslash( $_POST[ $field ] ?? '' ) ) );
		}
		// Neplatná hodnota (≤ 0, Délka přes 8 h) se neuloží a Služba pak nejde zveřejnit.
		$price    = (int) ( $_POST['_service_price'] ?? 0 );
		$duration = (int) ( $_POST['_service_duration'] ?? 0 );
		update_post_meta( $post_id, '_service_price', $price > 0 ? $price : '' );
		update_post_meta( $post_id, '_service_duration', $duration > 0 && $duration <= self::MAX_DURATION ? $duration : '' );
		foreach ( [ '_service_price_from', '_service_price_by_vehicle', '_service_bookable', '_service_is_seasonal', '_service_ask_stored_wheels', '_service_featured' ] as $field ) {
			update_post_meta( $post_id, $field, empty( $_POST[ $field ] ) ? '' : '1' );
		}

		$faq = [];
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- položky se sanitizují níž.
		foreach ( (array) wp_unslash( $_POST['_service_faq'] ?? [] ) as $item ) {
			$question = sanitize_text_field( (string) ( $item['question'] ?? '' ) );
			$answer   = sanitize_textarea_field( (string) ( $item['answer'] ?? '' ) );
			if ( '' !== $question && '' !== $answer ) {
				$faq[] = [
					'question' => $question,
					'answer'   => $answer,
				];
			}
		}
		update_post_meta( $post_id, '_service_faq', $faq );

		$related = array_map( 'absint', (array) wp_unslash( $_POST['_service_related'] ?? [] ) );
		$related = array_values( array_unique( array_filter( $related, static fn( int $id ): bool => $id > 0 && $id !== $post_id ) ) );
		update_post_meta( $post_id, '_service_related', $related );
	}
}
