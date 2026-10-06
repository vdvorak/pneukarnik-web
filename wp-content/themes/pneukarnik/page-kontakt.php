<?php
/**
 * Kontakt: PageHero, karty Adresa a spojení (s Rezervovat) a Otevírací doba, pod nimi Jak k nám
 * (text stránky) s Fakturačními údaji a Mapa po kliknutí.
 * Vše kromě příjezdu je z Nastavení pluginu (jedno místo kontaktů). Prázdné části se nevykreslí.
 *
 * @package Pneukarnik
 */

get_header();
the_post();
$pneukarnik_postal  = Pneukarnik_Contact::postal_address();
$pneukarnik_city    = trim( $pneukarnik_postal['postal_code'] . ' ' . $pneukarnik_postal['city'] );
$pneukarnik_address = Pneukarnik_Contact::address();
$pneukarnik_phone   = Pneukarnik_Contact::phone();
$pneukarnik_email   = Pneukarnik_Contact::email();
$pneukarnik_ico     = Pneukarnik_Contact::ico();
$pneukarnik_dic     = Pneukarnik_Contact::dic();
$pneukarnik_arrival = trim( wp_strip_all_tags( get_the_content() ) );
$pneukarnik_billing = '' !== $pneukarnik_ico || '' !== $pneukarnik_dic;
$pneukarnik_lead    = has_excerpt() ? get_the_excerpt() : __( 'Máte otázku? Zavolejte nám, nebo se rovnou objednejte online.', 'pneukarnik' );
?>
<main id="obsah" class="site-main kontakt">
	<?php pneukarnik_page_hero( get_the_title(), $pneukarnik_lead ); ?>

	<div class="kontakt__inner">
		<div class="kontakt__karty">
			<section class="card kontakt__spojeni">
				<h2><?php esc_html_e( 'Adresa a spojení', 'pneukarnik' ); ?></h2>
				<address class="kontakt__adresa">
					<strong><?php echo esc_html( Pneukarnik_Contact::company() ); ?></strong>
					<?php if ( '' !== $pneukarnik_postal['street'] ) : ?>
						<br><?php echo esc_html( $pneukarnik_postal['street'] ); ?>
					<?php endif; ?>
					<?php if ( '' !== $pneukarnik_city ) : ?>
						<br><?php echo esc_html( $pneukarnik_city ); ?>
					<?php endif; ?>
				</address>
				<?php if ( '' !== $pneukarnik_phone ) : ?>
					<a class="kontakt__telefon" href="<?php echo esc_url( pneukarnik_tel_href( $pneukarnik_phone ) ); ?>"><span aria-hidden="true">☎ </span><?php echo esc_html( $pneukarnik_phone ); ?></a>
				<?php endif; ?>
				<?php if ( '' !== $pneukarnik_email ) : ?>
					<a class="kontakt__email" href="<?php echo esc_url( 'mailto:' . $pneukarnik_email ); ?>"><?php echo esc_html( $pneukarnik_email ); ?></a>
				<?php endif; ?>
				<p class="kontakt__rezervovat"><a class="button button--md" href="<?php echo esc_url( home_url( '/rezervace/' ) ); ?>"><?php esc_html_e( 'Rezervovat', 'pneukarnik' ); ?></a></p>
			</section>

			<section class="card kontakt__doba">
				<h2><?php esc_html_e( 'Otevírací doba', 'pneukarnik' ); ?></h2>
				<?php pneukarnik_upcoming_hours(); ?>
			</section>
		</div>

		<div class="kontakt__sloupce">
			<?php if ( '' !== $pneukarnik_arrival || $pneukarnik_billing ) : ?>
				<div class="kontakt__sloupec">
					<?php if ( '' !== $pneukarnik_arrival ) : ?>
						<section class="kontakt__prijezd">
							<h2><?php esc_html_e( 'Jak k nám', 'pneukarnik' ); ?></h2>
							<div class="obsah"><?php the_content(); ?></div>
						</section>
					<?php endif; ?>

					<?php if ( $pneukarnik_billing ) : ?>
						<section class="kontakt__fakturace">
							<h2><?php esc_html_e( 'Fakturační údaje', 'pneukarnik' ); ?></h2>
							<p>
								<?php echo esc_html( Pneukarnik_Contact::company() ); ?><br>
								<?php if ( '' !== $pneukarnik_address ) : ?>
									<?php echo esc_html( $pneukarnik_address ); ?><br>
								<?php endif; ?>
								<?php if ( '' !== $pneukarnik_ico ) : ?>
									<?php esc_html_e( 'IČ:', 'pneukarnik' ); ?> <?php echo esc_html( $pneukarnik_ico ); ?><br>
								<?php endif; ?>
								<?php if ( '' !== $pneukarnik_dic ) : ?>
									<?php esc_html_e( 'DIČ:', 'pneukarnik' ); ?> <?php echo esc_html( $pneukarnik_dic ); ?>
								<?php endif; ?>
							</p>
						</section>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( '' !== Pneukarnik_Contact::map_embed_url() ) : ?>
				<section class="kontakt__sloupec kontakt__mapa">
					<h2><?php esc_html_e( 'Mapa', 'pneukarnik' ); ?></h2>
					<?php pneukarnik_map( false ); ?>
				</section>
			<?php endif; ?>
		</div>
	</div>
</main>
<?php
get_footer();
