<?php
/**
 * Pomocné funkce pro šablony.
 *
 * @package Pneukarnik
 */

declare(strict_types=1);

/**
 * Cena Služby pro web: „600 Kč“, „od 600 Kč“, nebo „Cena dle vozu“.
 */
function pneukarnik_price_label( Pneukarnik_Service $service ): string {
	if ( $service->price_by_vehicle || null === $service->price ) {
		return __( 'Cena dle vozu', 'pneukarnik' );
	}
	$amount = pneukarnik_amount( $service->price );
	/* translators: %s: částka, např. 600 Kč */
	return $service->price_from ? sprintf( __( 'od %s', 'pneukarnik' ), $amount ) : $amount;
}

/**
 * Částka v Kč pro web, např. „1 200 Kč“ (s nezlomitelnými mezerami).
 */
function pneukarnik_amount( int $amount ): string {
	return number_format( $amount, 0, ',', "\u{00A0}" ) . "\u{00A0}Kč";
}

/**
 * Blok platné Akce v detailu Služby: název, akční cena, popis a do kdy platí.
 */
function pneukarnik_promotion_block( Pneukarnik_Promotion $promotion ): void {
	?>
	<section class="sluzba__akce">
		<p class="stitek-akce"><?php esc_html_e( 'Akce', 'pneukarnik' ); ?></p>
		<h2><?php echo esc_html( $promotion->title ); ?></h2>
		<p class="sluzba__akce-cena"><?php echo esc_html( pneukarnik_amount( (int) $promotion->price ) ); ?></p>
		<?php if ( '' !== $promotion->description ) : ?>
			<?php echo wp_kses_post( wpautop( esc_html( $promotion->description ) ) ); ?>
		<?php endif; ?>
		<p class="sluzba__akce-platnost">
			<?php
			/* translators: %s: poslední den platnosti Akce, např. 31. 3. 2027 */
			echo esc_html( sprintf( __( 'Akce platí do %s.', 'pneukarnik' ), Pneukarnik_Clock::at( $promotion->valid_to )->format( 'j. n. Y' ) ) );
			?>
		</p>
	</section>
	<?php
}

/**
 * Oznámení nahoře na webu: nejnovější platné, Zákazník ho může zavřít do konce relace prohlížeče.
 * Na stránce rezervace se nevykreslí, když je stejné Oznámení ve výpisu u formuláře.
 */
function pneukarnik_top_notice(): void {
	$notice = Pneukarnik_Notice::top();
	if ( ! $notice || ( $notice->at_booking && 'rezervace' === Pneukarnik_Booking_Pages::current() ) ) {
		return;
	}
	?>
	<section class="oznameni" id="oznameni" data-id="<?php echo (int) $notice->id; ?>" aria-label="<?php esc_attr_e( 'Oznámení', 'pneukarnik' ); ?>">
		<p class="oznameni__nadpis"><strong><?php echo esc_html( $notice->title ); ?></strong></p>
		<?php echo wp_kses_post( wpautop( esc_html( $notice->text ) ) ); ?>
		<button type="button" class="oznameni__zavrit" hidden><?php esc_html_e( 'Zavřít oznámení', 'pneukarnik' ); ?></button>
	</section>
	<?php
	// Hned za prvkem, aby zavřené Oznámení po načtení stránky ani nebliklo. Bez cookies, jen sessionStorage.
	wp_print_inline_script_tag(
		<<<'JS'
		(function () {
			var box = document.getElementById('oznameni');
			var key = 'pnk-oznameni-zavreno-' + box.dataset.id;
			try {
				if (sessionStorage.getItem(key)) {
					box.remove();
					return;
				}
			} catch (e) {}
			var close = box.querySelector('.oznameni__zavrit');
			close.hidden = false;
			close.addEventListener('click', function () {
				box.remove();
				try {
					sessionStorage.setItem(key, '1');
				} catch (e) {}
			});
		})();
		JS
	);
}

/**
 * Platná Oznámení s volbou „zobrazit i u rezervace“ nad rezervačním formulářem.
 */
function pneukarnik_booking_notices(): void {
	$notices = Pneukarnik_Notice::at_booking();
	if ( ! $notices ) {
		return;
	}
	?>
	<section class="rezervace__oznameni" aria-label="<?php esc_attr_e( 'Oznámení k rezervaci', 'pneukarnik' ); ?>">
		<?php foreach ( $notices as $notice ) : ?>
			<div class="oznameni-u-rezervace">
				<p class="oznameni__nadpis"><strong><?php echo esc_html( $notice->title ); ?></strong></p>
				<?php echo wp_kses_post( wpautop( esc_html( $notice->text ) ) ); ?>
			</div>
		<?php endforeach; ?>
	</section>
	<?php
}

/**
 * Odkaz tel: z telefonu ve tvaru pro lidi.
 */
function pneukarnik_tel_href( string $phone ): string {
	return 'tel:' . preg_replace( '/[^0-9+]/', '', $phone );
}

/**
 * Karta Služby v přehledu (rozcestník, Úvod): název s odkazem, perex, cena, štítek platné Akce.
 *
 * @param string $heading Úroveň nadpisu karty podle okolí (h2 v rozcestníku, h3 v sekci Úvodu).
 */
function pneukarnik_service_card( Pneukarnik_Service $service, bool $has_promotion, string $heading = 'h2' ): void {
	$heading = tag_escape( $heading );
	?>
	<li class="karta-sluzby">
		<?php if ( $has_promotion ) : ?>
			<p class="stitek-akce"><?php esc_html_e( 'Akce', 'pneukarnik' ); ?></p>
		<?php endif; ?>
		<<?php echo $heading; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- tag_escape výše. ?>><a href="<?php echo esc_url( $service->url() ); ?>"><?php echo esc_html( $service->title ); ?></a></<?php echo $heading; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<p><?php echo esc_html( $service->perex ); ?></p>
		<p class="karta-sluzby__cena"><?php echo esc_html( pneukarnik_price_label( $service ) ); ?></p>
	</li>
	<?php
}

/**
 * Telefon a Rezervovat, aby byly hned vidět (hlavička na každé stránce, hero na Úvodu).
 */
function pneukarnik_contact_cta( string $css_class ): void {
	$phone = Pneukarnik_Contact::phone();
	?>
	<p class="<?php echo esc_attr( $css_class ); ?>">
		<?php if ( '' !== $phone ) : ?>
			<a class="kontakt-cta__telefon" href="<?php echo esc_url( pneukarnik_tel_href( $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a>
		<?php endif; ?>
		<a class="kontakt-cta__rezervovat" href="<?php echo esc_url( home_url( '/rezervace/' ) ); ?>"><?php esc_html_e( 'Rezervovat', 'pneukarnik' ); ?></a>
	</p>
	<?php
}

/**
 * Pohotovost výrazně v hlavičce, jen když ji Provozovatel zapnul.
 */
function pneukarnik_emergency(): void {
	$emergency = Pneukarnik_Contact::emergency();
	if ( ! $emergency ) {
		return;
	}
	?>
	<p class="pohotovost">
		<strong><?php esc_html_e( 'Pohotovost', 'pneukarnik' ); ?></strong>
		<a href="<?php echo esc_url( pneukarnik_tel_href( $emergency['phone'] ) ); ?>"><?php echo esc_html( $emergency['phone'] ); ?></a>
		<?php if ( '' !== $emergency['text'] ) : ?>
			<span><?php echo esc_html( $emergency['text'] ); ?></span>
		<?php endif; ?>
	</p>
	<?php
}

/**
 * Bloky Pracovní doby pro lidi: „8:00–12:00, 13:00–17:00“, null = „Zavřeno“.
 *
 * @param list<array{from:string,to:string}>|null $hours
 */
function pneukarnik_hours_label( ?array $hours ): string {
	if ( ! $hours ) {
		return __( 'Zavřeno', 'pneukarnik' );
	}
	$time = static fn( string $hhmm ): string => (int) substr( $hhmm, 0, 2 ) . substr( $hhmm, 2 ); // 08:00 → 8:00
	return implode( ', ', array_map( static fn( array $block ): string => $time( $block['from'] ) . '–' . $time( $block['to'] ), $hours ) );
}

/**
 * Otevírací doba na 7 dní od dneška včetně Výjimek a svátků (stejná pravidla jako Termíny).
 */
function pneukarnik_upcoming_hours(): void {
	$weekdays = [ 'neděle', 'pondělí', 'úterý', 'středa', 'čtvrtek', 'pátek', 'sobota' ];
	$relative = [ __( 'Dnes', 'pneukarnik' ), __( 'Zítra', 'pneukarnik' ) ];
	?>
	<table class="oteviraci-doba">
		<?php foreach ( Pneukarnik_Working_Hours::upcoming( 7 ) as $i => $day ) : ?>
			<?php $date = Pneukarnik_Clock::at( $day['date'] ); ?>
			<tr class="<?php echo 0 === $i ? 'oteviraci-doba__dnes' : ''; ?>">
				<th scope="row"><?php echo esc_html( $relative[ $i ] ?? $weekdays[ (int) $date->format( 'w' ) ] ); ?> <small><?php echo esc_html( $date->format( 'j. n.' ) ); ?></small></th>
				<td>
					<?php echo esc_html( pneukarnik_hours_label( $day['hours'] ) ); ?>
					<?php if ( '' !== $day['note'] ) : ?>
						<small>(<?php echo esc_html( $day['note'] ); ?>)</small>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
	</table>
	<?php
}

/**
 * Mapa, která nic nenačte od Googlu, dokud na ni Zákazník neklikne. Bez adresy se nevykreslí.
 */
function pneukarnik_map(): void {
	$embed = Pneukarnik_Contact::map_embed_url();
	if ( '' === $embed ) {
		return;
	}
	wp_enqueue_script(
		'pneukarnik-mapa',
		get_theme_file_uri( 'assets/js/mapa.js' ),
		[],
		(string) wp_get_theme()->get( 'Version' ),
		[
			'strategy'  => 'defer',
			'in_footer' => true,
		]
	);
	$address = Pneukarnik_Contact::address();
	?>
	<div class="mapa" data-mapa="<?php echo esc_url( $embed ); ?>">
		<button type="button" class="mapa__nacist" hidden><?php esc_html_e( 'Zobrazit mapu', 'pneukarnik' ); ?></button>
		<p class="mapa__info"><?php esc_html_e( 'Mapa se načte z Google Maps až po kliknutí.', 'pneukarnik' ); ?></p>
	</div>
	<?php if ( '' !== $address ) : ?>
		<p class="mapa__adresa">
			<?php echo esc_html( $address ); ?>
			· <a href="<?php echo esc_url( Pneukarnik_Contact::map_link() ); ?>" rel="noopener" target="_blank"><?php esc_html_e( 'Otevřít v Google Maps', 'pneukarnik' ); ?></a>
		</p>
	<?php endif; ?>
	<?php
}

/**
 * Výzva k akci u Služby: Rezervovat (jen u online rezervovatelných) a Zavolat.
 */
function pneukarnik_service_cta( Pneukarnik_Service $service ): void {
	$phone = Pneukarnik_Contact::phone();
	?>
	<div class="cta">
		<?php if ( $service->bookable ) : ?>
			<a class="cta__rezervovat" href="<?php echo esc_url( add_query_arg( 'sluzba', $service->slug, home_url( '/rezervace/' ) ) ); ?>"><?php esc_html_e( 'Rezervovat', 'pneukarnik' ); ?></a>
		<?php endif; ?>
		<?php if ( '' !== $phone ) : ?>
			<a class="cta__zavolat" href="<?php echo esc_url( pneukarnik_tel_href( $phone ) ); ?>">
				<?php
				/* translators: %s: telefonní číslo */
				echo esc_html( sprintf( __( 'Zavolat %s', 'pneukarnik' ), $phone ) );
				?>
			</a>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Google recenze z cache pluginu: hodnocení, počet, recenze a odkaz na všechna hodnocení.
 * Nic se nenačítá od Googlu (ani fotky autorů), jen odkazy.
 *
 * @param array{rating:float,count:int,url:string,reviews:list<array{author:string,author_url:string,rating:int,text:string,date:string}>} $summary
 */
function pneukarnik_reviews( array $summary ): void {
	$rating = number_format( $summary['rating'], 1, ',', '' );
	?>
	<p class="recenze__souhrn">
		<?php
		/* translators: 1: průměrné hodnocení, např. 4,8, 2: počet hodnocení */
		echo esc_html( sprintf( __( '%1$s z 5 (%2$s hodnocení)', 'pneukarnik' ), $rating, number_format_i18n( $summary['count'] ) ) );
		?>
	</p>
	<?php if ( $summary['reviews'] ) : ?>
		<ul class="recenze">
			<?php foreach ( $summary['reviews'] as $review ) : ?>
				<li class="recenze__polozka">
					<p class="recenze__hvezdy" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: počet hvězdiček */ __( '%d z 5 hvězdiček', 'pneukarnik' ), $review['rating'] ) ); ?>"><?php echo esc_html( str_repeat( '★', $review['rating'] ) . str_repeat( '☆', 5 - $review['rating'] ) ); ?></p>
					<blockquote><?php echo wp_kses_post( wpautop( esc_html( $review['text'] ) ) ); ?></blockquote>
					<p class="recenze__autor">
						<?php if ( '' !== $review['author_url'] ) : ?>
							<a href="<?php echo esc_url( $review['author_url'] ); ?>" rel="noopener nofollow" target="_blank"><?php echo esc_html( $review['author'] ); ?></a>,
						<?php else : ?>
							<?php echo esc_html( $review['author'] ); ?>,
						<?php endif; ?>
						<?php echo esc_html( Pneukarnik_Clock::at( $review['date'] )->format( 'j. n. Y' ) ); ?>
					</p>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
	<?php if ( '' !== $summary['url'] ) : ?>
		<p><a class="recenze__vse" href="<?php echo esc_url( $summary['url'] ); ?>" rel="noopener" target="_blank"><?php esc_html_e( 'Všechna hodnocení na Google', 'pneukarnik' ); ?></a></p>
	<?php endif; ?>
	<?php
}
