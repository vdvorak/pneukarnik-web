<?php
/**
 * Výchozí obsah webu: stránky O nás, Kontakt a Ochrana osobních údajů z mapy stránek
 * a koncepty tří Průvodců. Texty jsou zástupné, finální napíše vývojář a schválí Provozovatel.
 *
 * Vytvoří se jen to, co na webu chybí (podle adresy), existující obsah se nikdy nepřepíše.
 * Proběhne jednou po nasazení nové verze (PNEUKARNIK_CONTENT_VERSION).
 *
 * @package Pneukarnik
 */

declare(strict_types=1);

/** Při přidání výchozího obsahu zvyšte, chybějící obsah se doplní při dalším požadavku. */
const PNEUKARNIK_CONTENT_VERSION = '1';

add_action( 'init', 'pneukarnik_ensure_content', 20 ); // Po registraci typů obsahu pluginu.

function pneukarnik_ensure_content(): void {
	if ( get_option( 'pneukarnik_content_version' ) === PNEUKARNIK_CONTENT_VERSION || ! class_exists( 'Pneukarnik_Guide' ) ) {
		return;
	}

	foreach ( pneukarnik_default_pages() as $slug => [ $title, $excerpt, $content ] ) {
		$page = get_page_by_path( $slug );
		if ( ! $page ) {
			$id   = wp_insert_post(
				[
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_name'    => $slug,
					'post_title'   => $title,
					'post_excerpt' => $excerpt,
					'post_content' => $content,
				]
			);
			$page = $id ? get_post( $id ) : null;
		}
		if ( $page && 'ochrana-osobnich-udaju' === $slug ) {
			// Odkaz na zásady v administraci a v nástrojích WordPressu pro osobní údaje.
			update_option( 'wp_page_for_privacy_policy', $page->ID );
		}
	}

	// Průvodce zveřejní Provozovatel, až vybere Službu pro rezervaci (bez ní nejde zveřejnit).
	foreach ( pneukarnik_default_guides() as $slug => [ $title, $perex, $content, $order ] ) {
		if ( get_page_by_path( $slug, OBJECT, Pneukarnik_Guide::POST_TYPE ) ) {
			continue;
		}
		wp_insert_post(
			[
				'post_type'    => Pneukarnik_Guide::POST_TYPE,
				'post_status'  => 'draft',
				'post_name'    => $slug,
				'post_title'   => $title,
				'post_content' => $content,
				'menu_order'   => $order,
				'meta_input'   => [ '_guide_perex' => $perex ],
			]
		);
	}

	update_option( 'pneukarnik_content_version', PNEUKARNIK_CONTENT_VERSION );
}

/**
 * Stránky z mapy stránek ve specu (adresa => název, perex do PageHero, obsah v blocích editoru).
 *
 * @return array<string, array{0:string,1:string,2:string}>
 */
function pneukarnik_default_pages(): array {
	$heading     = static fn( string $text, string $id = '' ): string => '<!-- wp:heading --><h2 class="wp-block-heading"' . ( '' !== $id ? ' id="' . $id . '"' : '' ) . '>' . $text . "</h2><!-- /wp:heading -->\n\n";
	$paragraph   = static fn( string $text ): string => '<!-- wp:paragraph --><p>' . $text . "</p><!-- /wp:paragraph -->\n\n";
	$highlighted = static fn( string $blocks ): string => '<!-- wp:group {"className":"is-style-zvyrazneny"} --><div class="wp-block-group is-style-zvyrazneny">' . "\n" . $blocks . "</div><!-- /wp:group -->\n\n";
	$list        = static fn( array $items ): string => '<!-- wp:list --><ul class="wp-block-list">' . implode( '', array_map( static fn( string $item ): string => '<!-- wp:list-item --><li>' . $item . '</li><!-- /wp:list-item -->', $items ) ) . "</ul><!-- /wp:list -->\n\n";

	return [
		'o-nas'                  => [
			'O nás',
			'[Jednou větou, kdo jsme a od kdy servis ve Znojmě provozujeme, doplní Provozovatel.]',
			$heading( 'Historie', 'historie' )
				. $paragraph( 'Pneuservis a autoservis Jan Kárník ve Znojmě. [Od kdy servis funguje, jak začínal a co se od té doby změnilo, doplní Provozovatel.]' )
				. $heading( 'Tým', 'tym' )
				. $paragraph( '[Kdo se o vaše auto postará: jména, role a zkušenosti, doplní Provozovatel.]' )
				. $highlighted(
					$heading( 'BestDrive a Barum', 'bestdrive' )
					. $paragraph( 'Jsme franšízová pobočka sítě BestDrive, jedné z největších pneuservisních sítí v Česku a na Slovensku. [Co z nabídky sítě najdete i u nás (prodej pneumatik, litá a ocelová kola, autodoplňky) a vztah ke značce Barum upřesní Provozovatel.]' )
					. $paragraph( '<strong>Věrnostní karta BestDrive:</strong> [jaké výhody karta dává, kde ji Zákazník získá a jak ji u nás uplatní, doplní Provozovatel podle podkladů BestDrive.]' )
				)
				. $heading( 'Galerie', 'galerie' )
				. "<!-- wp:gallery {\"linkTo\":\"none\"} -->\n<figure class=\"wp-block-gallery has-nested-images columns-default is-cropped\"></figure>\n<!-- /wp:gallery -->\n",
		],
		'kontakt'                => [
			'Kontakt',
			'', // Bez perexu má Kontakt výchozí výzvu z šablony.
			// Adresa, telefon, otevírací doba, mapa a fakturační údaje jsou z Nastavení, tady jen popis příjezdu.
			$paragraph( '[Jak k nám dojedete: odbočka, orientační body a kde zaparkovat, doplní Provozovatel.]' ),
		],
		'ochrana-osobnich-udaju' => [
			'Ochrana osobních údajů',
			'Jak zacházíme s údaji, které nám svěříte při rezervaci.',
			// Správce (firma, adresa, IČ, e‑mail) vypíše šablona z Nastavení.
			$paragraph( '[Zástupné znění. Finální text napíše vývojář a schválí Provozovatel.]' )
				. $heading( 'Jaké údaje zpracováváme a proč' )
				. $list(
					[
						'Rezervace: jméno nebo firma, telefon, e‑mail, SPZ, značka a model vozu a poznámka, abychom vás mohli objednat, potvrdit Termín a ozvat se při změně.',
						'Připomínka přezutí: e‑mail, jen pokud k tomu dáte samostatný souhlas. Uchováváme ho do odvolání souhlasu, odvoláte ho odkazem v každé Připomínce.',
					]
				)
				. $heading( 'Jak dlouho údaje uchováváme' )
				. $paragraph( 'Osobní údaje z Rezervace se automaticky anonymizují 1 rok po Termínu. Zůstane jen to, jakou Službu a kdy jsme dělali.' )
				. $heading( 'Cookies a měření návštěvnosti' )
				. $paragraph( 'Web nepoužívá cookies, které by vyžadovaly souhlas. Mapa se načte z Google Maps až po vašem kliknutí. Návštěvnost měříme nástrojem Matomo v režimu bez cookies, adresy stránek odesíláme bez osobních údajů a odkazů z e‑mailů. [Kde Matomo běží a jak dlouho data uchovává, upřesní vývojář.]' )
				. $heading( 'Vaše práva' )
				. $paragraph( 'Máte právo na přístup k údajům, jejich opravu, výmaz, omezení zpracování a odvolání souhlasu. Stížnost můžete podat Úřadu pro ochranu osobních údajů. [Kontakt pro uplatnění práv doplní Provozovatel.]' ),
		],
	];
}

/**
 * Průvodci ze zadání (adresa => název, perex, text, pořadí).
 *
 * @return array<string, array{0:string,1:string,2:string,3:int}>
 */
function pneukarnik_default_guides(): array {
	return [
		'kdy-prezout'          => [
			'Kdy přezout na zimní a letní pneumatiky',
			'Kdy je ten správný čas na přezutí a co říká zákon.',
			"<h2>Zimní pneumatiky</h2>\n<p>Zimní pneumatiky se vyplatí, jakmile průměrná denní teplota klesne pod 7 °C. Ze zákona je musíte mít od 1. 11. do 31. 3., když je na silnici sníh, led nebo námraza, nebo kde to přikazuje dopravní značka. [Zástupný text, upřesní vývojář.]</p>\n<h2>Letní pneumatiky</h2>\n<p>Na letní pneumatiky přezujte, když teploty trvale vystoupají nad 7 °C. [Zástupný text, upřesní vývojář.]</p>\n<h2>Termín v sezóně</h2>\n<p>V sezóně přezouvání je o Termíny velký zájem, objednejte se raději s předstihem.</p>",
			1,
		],
		'uskladneni-pneumatik' => [
			'Uskladnění pneumatik u nás',
			'Kola nemusíte skladovat doma, uschováme je do další sezóny.',
			"<h2>Jak uskladnění funguje</h2>\n<p>[Jak kola převezmeme, kde a jak je skladujeme a jak je při dalším přezutí připravíme, doplní Provozovatel.]</p>\n<h2>Cena</h2>\n<p>[Cena a co zahrnuje, doplní Provozovatel.]</p>\n<h2>Při rezervaci</h2>\n<p>Když máte kola uskladněná u nás, zaškrtněte to v rezervačním formuláři, ať je máme připravená.</p>",
			2,
		],
		'kdy-vymenit'          => [
			'Kdy pneumatiky vyměnit',
			'Podle čeho poznáte, že pneumatiky dosloužily.',
			"<h2>Hloubka dezénu</h2>\n<p>Zákonné minimum je 1,6 mm u letních a 4 mm u zimních pneumatik osobních aut. Bezpečně ale pneumatika brzdí jen s hlubším dezénem, letní doporučujeme měnit kolem 3 mm. [Zástupný text, upřesní vývojář.]</p>\n<h2>Stáří a poškození</h2>\n<p>Pneumatika stárne, i když se nejezdí. Datum výroby najdete na boku (DOT, např. 2320 = 23. týden roku 2020). Praskliny, boule nebo nerovnoměrné sjetí jsou důvod k výměně hned. [Zástupný text, upřesní vývojář.]</p>\n<h2>Kontrola u nás</h2>\n<p>Při přezutí stav pneumatik zkontrolujeme a řekneme vám, jestli vydrží další sezónu.</p>",
			3,
		],
	];
}
