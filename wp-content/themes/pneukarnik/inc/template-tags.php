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
 * Položka Služby ve výběru rezervace: název a cena, s platnou Akcí akční cena a za ní běžná,
 * např. „Dekarbonizace (akce 1 290 Kč, běžně od 1 690 Kč)“. U „Cena dle vozu“ jen akční cena.
 */
function pneukarnik_service_option_label( Pneukarnik_Service $service, ?Pneukarnik_Promotion $promotion ): string {
	if ( null === $promotion || null === $promotion->price ) {
		return sprintf( '%s (%s)', $service->title, pneukarnik_price_label( $service ) );
	}
	$by_vehicle = $service->price_by_vehicle || null === $service->price;
	return $by_vehicle
		/* translators: 1: název Služby, 2: akční cena */
		? sprintf( __( '%1$s (akce %2$s)', 'pneukarnik' ), $service->title, pneukarnik_amount( $promotion->price ) )
		/* translators: 1: název Služby, 2: akční cena, 3: běžná cena, např. od 1 690 Kč */
		: sprintf( __( '%1$s (akce %2$s, běžně %3$s)', 'pneukarnik' ), $service->title, pneukarnik_amount( $promotion->price ), pneukarnik_price_label( $service ) );
}

/**
 * Částka v Kč pro web, např. „1 200 Kč“ (s nezlomitelnými mezerami).
 */
function pneukarnik_amount( int $amount ): string {
	return number_format( $amount, 0, ',', "\u{00A0}" ) . "\u{00A0}Kč";
}

/**
 * Blok platné Akce v detailu Služby (tmavý): štítek, název, akční cena, popis a do kdy platí.
 */
function pneukarnik_promotion_block( Pneukarnik_Promotion $promotion ): void {
	?>
	<section class="sluzba__akce">
		<p class="sluzba__akce-stitek"><?php esc_html_e( 'Akce', 'pneukarnik' ); ?></p>
		<div class="sluzba__akce-hlava">
			<h2><?php echo esc_html( $promotion->title ); ?></h2>
			<p class="sluzba__akce-cena"><?php echo esc_html( pneukarnik_amount( (int) $promotion->price ) ); ?></p>
		</div>
		<?php if ( '' !== $promotion->description ) : ?>
			<div class="sluzba__akce-popis"><?php echo wp_kses_post( wpautop( esc_html( $promotion->description ) ) ); ?></div>
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
 * Karta Cena v detailu Služby: cena, co zahrnuje, Rezervovat (jen online rezervovatelná) a Zavolat.
 * Na desktopu přilepená vpravo, na mobilu pod hero jen s cenou (akce jsou v mobilní liště).
 */
function pneukarnik_service_price_card( Pneukarnik_Service $service ): void {
	$phone = Pneukarnik_Contact::phone();
	?>
	<aside class="cena-sluzby" aria-labelledby="cena-sluzby">
		<div class="cena-sluzby__radek">
			<h2 class="eyebrow" id="cena-sluzby"><?php esc_html_e( 'Cena', 'pneukarnik' ); ?></h2>
			<p class="cena-sluzby__castka"><?php echo esc_html( pneukarnik_price_label( $service ) ); ?></p>
		</div>
		<?php if ( '' !== $service->price_note ) : ?>
			<p class="cena-sluzby__zahrnuje"><?php esc_html_e( 'Cena zahrnuje:', 'pneukarnik' ); ?> <?php echo esc_html( $service->price_note ); ?></p>
		<?php endif; ?>
		<?php if ( $service->bookable || '' !== $phone ) : ?>
			<p class="cena-sluzby__akce">
				<?php if ( $service->bookable ) : ?>
					<a class="button button--block" href="<?php echo esc_url( pneukarnik_booking_url( $service ) ); ?>"><?php esc_html_e( 'Rezervovat', 'pneukarnik' ); ?></a>
				<?php endif; ?>
				<?php if ( '' !== $phone ) : ?>
					<a class="button button--secondary button--block" href="<?php echo esc_url( pneukarnik_tel_href( $phone ) ); ?>">
						<?php
						/* translators: %s: telefonní číslo */
						echo esc_html( sprintf( __( 'Zavolat %s', 'pneukarnik' ), $phone ) );
						?>
					</a>
				<?php endif; ?>
			</p>
		<?php endif; ?>
		<?php if ( ! $service->bookable ) : ?>
			<p class="cena-sluzby__poznamka"><?php esc_html_e( 'Tuto službu objednáváme jen telefonicky.', 'pneukarnik' ); ?></p>
		<?php endif; ?>
	</aside>
	<?php
}

/**
 * Adresa rezervačního formuláře, u online rezervovatelné Služby s ní předvybranou.
 */
function pneukarnik_booking_url( ?Pneukarnik_Service $service = null ): string {
	$url = home_url( '/rezervace/' );
	return $service && $service->bookable ? add_query_arg( 'sluzba', $service->slug, $url ) : $url;
}

/**
 * Oznámení nad hlavičkou: nejnovější platné, Zákazník ho může zavřít do konce relace prohlížeče.
 * Na stránce rezervace se nevykreslí vůbec, Oznámení k rezervaci jsou u formuláře.
 */
function pneukarnik_top_notice(): void {
	$notice = Pneukarnik_Notice::top();
	if ( ! $notice || 'rezervace' === Pneukarnik_Booking_Pages::current() ) {
		return;
	}
	?>
	<section class="oznameni" id="oznameni" data-id="<?php echo (int) $notice->id; ?>" aria-label="<?php esc_attr_e( 'Oznámení', 'pneukarnik' ); ?>">
		<div class="oznameni__inner">
			<p class="oznameni__text"><strong class="oznameni__nadpis"><?php echo esc_html( $notice->title ); ?></strong> <?php echo nl2br( esc_html( $notice->text ) ); ?></p>
			<button type="button" class="oznameni__zavrit" aria-label="<?php esc_attr_e( 'Zavřít oznámení', 'pneukarnik' ); ?>" title="<?php esc_attr_e( 'Zavřít oznámení', 'pneukarnik' ); ?>" hidden><span aria-hidden="true">×</span></button>
		</div>
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
			<p class="notice"><strong class="oznameni__nadpis"><?php echo esc_html( $notice->title ); ?></strong> <?php echo nl2br( esc_html( $notice->text ) ); ?></p>
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
 * Karta Služby v přehledu (stránka Služby, Úvod): ikona, štítek platné Akce a „i“ s odkazem na Průvodce
 * (horní řádek jen s nimi), název, perex, cena (s Akcí akční a přeškrtnutá běžná) a Rezervovat s touto
 * Službou, u Služby jen na telefon Zavolat. Celá karta je odkazem na detail (roztažený odkaz názvu),
 * „i“ a Rezervovat/Zavolat leží nad ním.
 *
 * @param Pneukarnik_Promotion|null $promotion Platná Akce Služby (Pneukarnik_Promotion::current()).
 * @param Pneukarnik_Guide|null     $guide     Zveřejněný Průvodce ke Službě (Pneukarnik_Guide::by_service()).
 * @param string                    $heading   Úroveň nadpisu karty podle okolí.
 */
function pneukarnik_service_card( Pneukarnik_Service $service, ?Pneukarnik_Promotion $promotion, ?Pneukarnik_Guide $guide = null, string $heading = 'h3' ): void {
	$heading = tag_escape( $heading );
	$icon    = pneukarnik_service_icon( $service->icon );
	$phone   = Pneukarnik_Contact::phone();
	?>
	<li class="card karta-sluzby">
		<?php if ( '' !== $icon || $promotion || $guide ) : ?>
			<div class="karta-sluzby__hlava">
				<?php if ( '' !== $icon ) : ?>
					<span class="karta-sluzby__ikona"><?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG ze šablony. ?></span>
				<?php endif; ?>
				<?php if ( $promotion ) : ?>
					<?php
					/* translators: %s: poslední den platnosti Akce, např. 31. 3. */
					$until = sprintf( __( 'Akce do %s', 'pneukarnik' ), pneukarnik_short_date( $promotion->valid_to ) );
					?>
					<span class="pill karta-sluzby__akce"><?php echo esc_html( $until ); ?></span>
				<?php endif; ?>
				<?php if ( $guide ) : ?>
					<?php
					/* translators: %s: název Průvodce */
					$label = sprintf( __( 'Průvodce: %s', 'pneukarnik' ), $guide->title );
					?>
					<a class="ikona-info karta-sluzby__pruvodce" href="<?php echo esc_url( $guide->url() ); ?>" title="<?php echo esc_attr( $label ); ?>"><?php echo pneukarnik_icon( 'info' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG ze šablony. ?><span class="screen-reader-text"><?php echo esc_html( $label ); ?></span></a>
				<?php endif; ?>
			</div>
		<?php endif; ?>
		<<?php echo $heading; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- tag_escape výše. ?> class="karta-sluzby__nazev"><a class="karta-sluzby__odkaz" href="<?php echo esc_url( $service->url() ); ?>"><?php echo esc_html( $service->title ); ?></a></<?php echo $heading; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<p class="karta-sluzby__perex"><?php echo esc_html( $service->perex ); ?></p>
		<p class="karta-sluzby__spodek">
			<span class="karta-sluzby__cena">
				<?php if ( $promotion && null !== $promotion->price ) : ?>
					<span class="screen-reader-text"><?php esc_html_e( 'Akční cena', 'pneukarnik' ); ?></span>
					<span class="karta-sluzby__cena-akce"><?php echo esc_html( pneukarnik_amount( $promotion->price ) ); ?></span>
					<?php if ( ! $service->price_by_vehicle && null !== $service->price ) : ?>
						<span class="screen-reader-text"><?php esc_html_e( ', běžně', 'pneukarnik' ); ?></span>
						<s class="karta-sluzby__cena-bezna"><?php echo esc_html( pneukarnik_price_label( $service ) ); ?></s>
					<?php endif; ?>
				<?php else : ?>
					<?php echo esc_html( pneukarnik_price_label( $service ) ); ?>
				<?php endif; ?>
			</span>
			<?php if ( $service->bookable ) : ?>
				<?php /* translators: %s: název Služby */ ?>
				<a class="karta-sluzby__rezervovat" href="<?php echo esc_url( pneukarnik_booking_url( $service ) ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Rezervovat: %s', 'pneukarnik' ), $service->title ) ); ?>"><?php esc_html_e( 'Rezervovat', 'pneukarnik' ); ?> <span aria-hidden="true">→</span></a>
			<?php elseif ( '' !== $phone ) : ?>
				<?php /* translators: %s: telefonní číslo */ ?>
				<a class="karta-sluzby__rezervovat" href="<?php echo esc_url( pneukarnik_tel_href( $phone ) ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Zavolat %s', 'pneukarnik' ), $phone ) ); ?>"><?php esc_html_e( 'Zavolat', 'pneukarnik' ); ?> <span aria-hidden="true">→</span></a>
			<?php endif; ?>
		</p>
	</li>
	<?php
}

/**
 * Den na štítku, např. „31. 3.“, z jiného roku než letos s rokem („31. 3. 2028“).
 *
 * @param string $date YYYY-MM-DD.
 */
function pneukarnik_short_date( string $date ): string {
	$day = Pneukarnik_Clock::at( $date );
	return $day->format( $day->format( 'Y' ) === Pneukarnik_Clock::now()->format( 'Y' ) ? 'j. n.' : 'j. n. Y' );
}

/**
 * Ikona Služby ze sady v assets/icons jako vložené SVG (barva textu), prázdný řetězec bez ikony.
 */
function pneukarnik_service_icon( string $name ): string {
	return '' !== $name && isset( Pneukarnik_Service::icons()[ $name ] ) ? pneukarnik_icon( $name ) : '';
}

/**
 * Ikona ze sady v assets/icons jako vložené SVG (barva textu), skrytá pro čtečky.
 *
 * @param string $name Název souboru bez přípony, jen ze šablony (ne od uživatele).
 */
function pneukarnik_icon( string $name ): string {
	static $cache = [];
	if ( ! isset( $cache[ $name ] ) ) {
		$file           = get_theme_file_path( "assets/icons/{$name}.svg" );
		$svg            = is_readable( $file ) ? (string) file_get_contents( $file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- soubor šablony.
		$cache[ $name ] = trim( str_replace( '<svg ', '<svg aria-hidden="true" focusable="false" ', $svg ) );
	}
	return $cache[ $name ];
}

/**
 * Pohotovost jako tmavý pruh nad hlavičkou, jen když ji Provozovatel zapnul.
 */
function pneukarnik_emergency(): void {
	$emergency = Pneukarnik_Contact::emergency();
	if ( ! $emergency ) {
		return;
	}
	?>
	<div class="pohotovost">
		<p class="pohotovost__inner">
			<strong class="pohotovost__stitek"><?php esc_html_e( 'Pohotovost', 'pneukarnik' ); ?></strong>
			<?php if ( '' !== $emergency['text'] ) : ?>
				<span class="pohotovost__text"><?php echo esc_html( $emergency['text'] ); ?></span>
			<?php endif; ?>
			<a class="pohotovost__telefon" href="<?php echo esc_url( pneukarnik_tel_href( $emergency['phone'] ) ); ?>"><span aria-hidden="true">☎ </span><?php echo esc_html( $emergency['phone'] ); ?></a>
		</p>
	</div>
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
 * Zkrácená Pracovní doba z Nastavení, např. „Po–Pá 8:00–12:00, 13:00–17:00; So 8:00–11:00“.
 * Po sobě jdoucí dny se stejnými bloky sloučí, zavřené dny vynechá. Vše zavřené = prázdný řetězec.
 */
function pneukarnik_weekly_hours(): string {
	$names  = [
		'mon' => 'Po',
		'tue' => 'Út',
		'wed' => 'St',
		'thu' => 'Čt',
		'fri' => 'Pá',
		'sat' => 'So',
		'sun' => 'Ne',
	];
	$all    = Pneukarnik_Working_Hours::get_all();
	$groups = []; // [ první den, poslední den, bloky ]
	$prev   = null;
	foreach ( array_keys( $names ) as $day ) {
		$hours = $all[ $day ] ?? null;
		if ( $hours && $hours === $prev ) {
			$groups[ array_key_last( $groups ) ][1] = $day;
		} elseif ( $hours ) {
			$groups[] = [ $day, $day, $hours ];
		}
		$prev = $hours;
	}
	return implode(
		'; ',
		array_map(
			static fn( array $group ): string => $names[ $group[0] ] . ( $group[0] !== $group[1] ? '–' . $names[ $group[1] ] : '' ) . ' ' . pneukarnik_hours_label( $group[2] ),
			$groups
		)
	);
}

/**
 * Otevírací doba jednoho dne z Pneukarnik_Working_Hours::upcoming() s důvodem Výjimky nebo svátku,
 * např. „Zavřeno (Den české státnosti)“.
 *
 * @param array{hours:list<array{from:string,to:string}>|null,note:string} $day
 */
function pneukarnik_day_hours_label( array $day ): string {
	return pneukarnik_hours_label( $day['hours'] ) . ( '' !== $day['note'] ? ' (' . $day['note'] . ')' : '' );
}

/**
 * Otevírací doba Dnes a Zítra v hero Úvodu (stejná pravidla jako tabulka na 7 dní).
 */
function pneukarnik_today_tomorrow_hours(): void {
	[ $today, $tomorrow ] = Pneukarnik_Working_Hours::upcoming( 2 );
	?>
	<p class="uvod__dnes">
		<span><span class="uvod__dnes-den"><?php esc_html_e( 'Dnes:', 'pneukarnik' ); ?></span> <strong class="uvod__dnes-doba"><?php echo esc_html( pneukarnik_day_hours_label( $today ) ); ?></strong></span>
		<span><span class="uvod__dnes-den"><?php esc_html_e( 'Zítra:', 'pneukarnik' ); ?></span> <strong><?php echo esc_html( pneukarnik_day_hours_label( $tomorrow ) ); ?></strong></span>
	</p>
	<?php
}

/**
 * Fotka provozovny do hero Úvodu: WebP v několika šířkách, prohlížeč si vybere podle šířky okna.
 * Zdroj je v návrhu (assets/foto-provozovna.jpg), zmenšené verze jsou v assets/img.
 */
function pneukarnik_hero_photo(): void {
	$widths = [ 640, 960, 1280, 1920, 2560 ];
	$url    = static fn( int $width ): string => get_theme_file_uri( "assets/img/foto-provozovna-{$width}.webp" );
	$srcset = implode( ', ', array_map( static fn( int $width ): string => esc_url( $url( $width ) ) . " {$width}w", $widths ) );
	?>
	<img class="uvod__foto" src="<?php echo esc_url( $url( 1280 ) ); ?>" srcset="<?php echo $srcset; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- adresy esc_url výše. ?>" sizes="100vw" width="2560" height="1706" alt="" fetchpriority="high">
	<?php
}

/**
 * Krátký popis Kategorie pod nadpisem jejího rozcestníku, prázdný = bez popisu.
 */
function pneukarnik_category_lead( string $category ): string {
	$leads = [
		Pneukarnik_Service::PNEUSERVIS => __( 'Přezutí, uskladnění, prodej pneu, geometrie a opravy defektů.', 'pneukarnik' ),
		Pneukarnik_Service::AUTOSERVIS => __( 'Příprava na STK, klimatizace, brzdy, olej, diagnostika a další.', 'pneukarnik' ),
	];
	return $leads[ $category ] ?? '';
}

/**
 * Otevírací doba na 7 dní od dneška včetně Výjimek a svátků (stejná pravidla jako Termíny).
 */
function pneukarnik_upcoming_hours(): void {
	$weekdays = [ 'Neděle', 'Pondělí', 'Úterý', 'Středa', 'Čtvrtek', 'Pátek', 'Sobota' ];
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
 * Pod ní odkaz do Google Maps, s $with_address i s názvem a adresou (Úvod).
 */
function pneukarnik_map( bool $with_address = true ): void {
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
		<button type="button" class="mapa__nacist button button--dark button--md" hidden><?php esc_html_e( 'Zobrazit mapu', 'pneukarnik' ); ?></button>
		<p class="mapa__info"><?php esc_html_e( 'Mapa se načte z Google Maps až po kliknutí.', 'pneukarnik' ); ?></p>
	</div>
	<?php if ( '' !== $address ) : ?>
		<p class="mapa__adresa">
			<?php if ( $with_address ) : ?>
				<span><strong><?php bloginfo( 'name' ); ?></strong>, <?php echo esc_html( $address ); ?></span>
			<?php endif; ?>
			<a class="arrow-link" href="<?php echo esc_url( Pneukarnik_Contact::map_link() ); ?>" rel="noopener" target="_blank"><?php esc_html_e( 'Otevřít v Google Maps', 'pneukarnik' ); ?></a>
		</p>
	<?php endif; ?>
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
	$stars  = static fn( int $count ): string => str_repeat( '★', $count ) . str_repeat( '☆', 5 - $count );
	?>
	<p class="recenze__souhrn">
		<strong class="recenze__prumer"><?php echo esc_html( $rating ); ?></strong>
		<span class="recenze__hvezdy" aria-hidden="true"><?php echo esc_html( $stars( max( 0, min( 5, (int) round( $summary['rating'] ) ) ) ) ); ?></span>
		<span class="recenze__pocet">
			<?php
			/* translators: %s: počet hodnocení */
			echo esc_html( sprintf( __( 'z 5 (%s hodnocení)', 'pneukarnik' ), number_format_i18n( $summary['count'] ) ) );
			?>
		</span>
	</p>
	<?php if ( $summary['reviews'] ) : ?>
		<ul class="recenze">
			<?php foreach ( $summary['reviews'] as $review ) : ?>
				<li class="card recenze__polozka">
					<p class="recenze__hvezdy" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: počet hvězdiček */ __( '%d z 5 hvězdiček', 'pneukarnik' ), $review['rating'] ) ); ?>"><?php echo esc_html( $stars( $review['rating'] ) ); ?></p>
					<blockquote class="recenze__text"><?php echo wp_kses_post( wpautop( esc_html( $review['text'] ) ) ); ?></blockquote>
					<p class="recenze__autor">
						<?php if ( '' !== $review['author_url'] ) : ?>
							<a href="<?php echo esc_url( $review['author_url'] ); ?>" rel="noopener nofollow" target="_blank"><?php echo esc_html( $review['author'] ); ?></a>
						<?php else : ?>
							<?php echo esc_html( $review['author'] ); ?>
						<?php endif; ?>
						· <?php echo esc_html( Pneukarnik_Clock::at( $review['date'] )->format( 'j. n. Y' ) ); ?>
					</p>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
	<?php if ( '' !== $summary['url'] ) : ?>
		<p class="recenze__vse"><a class="arrow-link" href="<?php echo esc_url( $summary['url'] ); ?>" rel="noopener" target="_blank"><?php esc_html_e( 'Všechna hodnocení na Google', 'pneukarnik' ); ?></a></p>
	<?php endif; ?>
	<?php
}

/**
 * Název webu pro značku: první slovo akcentem („Pneuservis“), zbytek („Kárník“).
 *
 * @return array{0:string,1:string} předpona a zbytek názvu
 */
function pneukarnik_brand(): array {
	$parts = explode( ' ', trim( (string) get_bloginfo( 'name' ) ), 2 );
	return 2 === count( $parts ) ? [ $parts[0], $parts[1] ] : [ '', $parts[0] ];
}

/**
 * Odkazy hlavního menu, aktivní položka má aria-current (stránka sama „page“, stránka pod ní „true“).
 */
function pneukarnik_nav_links(): void {
	$items = [
		'sluzby'    => [ __( 'Služby', 'pneukarnik' ), Pneukarnik_Service::services_url() ],
		'o-nas'     => [ __( 'O nás', 'pneukarnik' ), home_url( '/o-nas/' ) ],
		'kontakt'   => [ __( 'Kontakt', 'pneukarnik' ), home_url( '/kontakt/' ) ],
		'rezervace' => [ __( 'Rezervace', 'pneukarnik' ), home_url( '/rezervace/' ) ],
	];

	[ $active, $exact ] = pneukarnik_nav_active();
	foreach ( $items as $key => [ $label, $url ] ) {
		printf(
			'<a href="%s"%s>%s</a>',
			esc_url( $url ),
			$key === $active ? ' aria-current="' . ( $exact ? 'page' : 'true' ) . '"' : '',
			esc_html( $label )
		);
	}
}

/**
 * Položka menu, pod kterou patří aktuální stránka, a jestli je to přímo ona.
 *
 * @return array{0:string,1:bool}
 */
function pneukarnik_nav_active(): array {
	if ( is_post_type_archive( Pneukarnik_Service::POST_TYPE ) ) {
		return [ 'sluzby', null === Pneukarnik_Service_Type::shown_category() ];
	}
	if ( is_singular( Pneukarnik_Service::POST_TYPE ) ) {
		return [ 'sluzby', false ];
	}
	if ( is_page( [ 'o-nas', 'kontakt' ] ) ) {
		return [ (string) get_post_field( 'post_name', get_queried_object_id() ), true ];
	}
	$booking_page = Pneukarnik_Booking_Pages::current();
	if ( 'rezervace' === $booking_page || 'potvrzeni' === $booking_page ) {
		return [ 'rezervace', 'rezervace' === $booking_page ];
	}
	return [ '', false ];
}

/**
 * Ikona sociální sítě (obrys ve stylu Lucide, barva textu).
 */
function pneukarnik_social_icon( string $network ): void {
	$paths = [
		'facebook'  => '<path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>',
		'instagram' => '<rect width="20" height="20" x="2" y="2" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><path d="M17.5 6.5h.01"/>',
		'google'    => '<path d="M21 12a9 9 0 1 1-2.64-6.36"/><path d="M21 12h-9"/>',
	];
	if ( ! isset( $paths[ $network ] ) ) {
		return;
	}
	echo '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $network ] . '</svg>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pevné SVG výše.
}

/**
 * Lišta Zavolat + Rezervovat dole na mobilu (Úvod, Služby, detail Služby, Kontakt, O nás, Průvodce, 404).
 * Na detailu online rezervovatelné Služby vede Rezervovat na formulář s touto Službou.
 */
function pneukarnik_mobile_bar(): void {
	// Stránky rezervace jdou přes dotaz Úvodu, is_front_page() je pro ně také true.
	$on_page = ( '' === Pneukarnik_Booking_Pages::current() && ( is_front_page() || is_404() ) )
		|| is_page( [ 'kontakt', 'o-nas' ] ) || is_post_type_archive( Pneukarnik_Service::POST_TYPE ) || is_singular( [ Pneukarnik_Service::POST_TYPE, Pneukarnik_Guide::POST_TYPE ] );
	if ( ! $on_page ) {
		return;
	}
	$booking = pneukarnik_booking_url( is_singular( Pneukarnik_Service::POST_TYPE ) ? Pneukarnik_Service::from_post( get_post() ) : null );
	$phone   = Pneukarnik_Contact::phone();
	?>
	<nav class="mobilni-lista" aria-label="<?php esc_attr_e( 'Rychlý kontakt', 'pneukarnik' ); ?>">
		<?php if ( '' !== $phone ) : ?>
			<a class="mobilni-lista__zavolat" href="<?php echo esc_url( pneukarnik_tel_href( $phone ) ); ?>"><?php esc_html_e( 'Zavolat', 'pneukarnik' ); ?></a>
		<?php endif; ?>
		<a class="mobilni-lista__rezervovat" href="<?php echo esc_url( $booking ); ?>"><?php esc_html_e( 'Rezervovat', 'pneukarnik' ); ?></a>
	</nav>
	<?php
}

/**
 * Úvod stránky (PageHero): nadpis, nepovinný perex a drobeček zpět.
 *
 * @param array{0:string,1:string}|null $back     popisek a adresa drobečku zpět
 * @param callable|null                 $children vypíše obsah pod perexem (např. přepínač)
 */
function pneukarnik_page_hero( string $title, string $lead = '', ?array $back = null, bool $narrow = false, ?callable $children = null ): void {
	?>
	<section class="page-hero">
		<div class="page-hero__inner<?php echo $narrow ? ' page-hero__inner--narrow' : ''; ?>">
			<?php if ( $back ) : ?>
				<a class="page-hero__back arrow-link--back" href="<?php echo esc_url( $back[1] ); ?>"><?php echo esc_html( $back[0] ); ?></a>
			<?php endif; ?>
			<h1 class="page-hero__title"><?php echo esc_html( $title ); ?></h1>
			<?php if ( '' !== $lead ) : ?>
				<p class="page-hero__lead"><?php echo esc_html( $lead ); ?></p>
			<?php endif; ?>
			<?php
			if ( $children ) {
				$children();
			}
			?>
		</div>
	</section>
	<?php
}

/**
 * Souhrn Rezervace jako řádky „klíč – hodnota“ (Potvrzení, Zrušení).
 *
 * @param array<string,string> $rows popisek => hodnota
 */
function pneukarnik_booking_summary( array $rows ): void {
	?>
	<dl class="udaje">
		<?php foreach ( $rows as $label => $value ) : ?>
			<div><dt><?php echo esc_html( $label ); ?></dt><dd><?php echo esc_html( $value ); ?></dd></div>
		<?php endforeach; ?>
	</dl>
	<?php
}
