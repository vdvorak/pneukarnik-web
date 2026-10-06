<?php
/**
 * Úvod v pořadí ze zadání: (Oznámení v hlavičce) → hero s telefonem, Rezervovat a otevírací dobou
 * Dnes / Zítra → Co pro vás uděláme (Služby s Akcí, nejžádanější, odkaz na všechny) → proč k nám →
 * Google recenze → otevírací doba na 7 dní a mapa. Sekce bez obsahu se nevykreslí.
 *
 * @package Pneukarnik
 */

$pneukarnik_promotions = Pneukarnik_Promotion::current();
$pneukarnik_services   = Pneukarnik_Service::for_home( $pneukarnik_promotions );
$pneukarnik_all_count  = count( Pneukarnik_Service::published() );
$pneukarnik_guides     = $pneukarnik_services ? Pneukarnik_Guide::by_service() : [];
$pneukarnik_why_us     = pneukarnik_why_us();
$pneukarnik_reviews    = Pneukarnik_Reviews::summary();
$pneukarnik_year       = pneukarnik_founded_year();
$pneukarnik_phone      = Pneukarnik_Contact::phone();

get_header();
?>
<main id="obsah" class="site-main">
	<section class="uvod__hero">
		<?php pneukarnik_hero_photo(); ?>
		<div class="uvod__hero-inner">
			<p class="eyebrow eyebrow--accent">
				<?php
				echo esc_html(
					null === $pneukarnik_year
						? __( 'Znojmo', 'pneukarnik' )
						/* translators: %d: rok založení, např. 1991 */
						: sprintf( __( 'Znojmo · od roku %d', 'pneukarnik' ), $pneukarnik_year )
				);
				?>
			</p>
			<h1><?php echo esc_html( Pneukarnik_Contact::company() ); ?></h1>
			<p class="uvod__perex"><?php esc_html_e( 'Pneuservis a autoservis. Objednejte se online, nebo zavolejte.', 'pneukarnik' ); ?></p>
			<p class="uvod__cta">
				<a class="button button--glow" href="<?php echo esc_url( pneukarnik_booking_url() ); ?>"><?php esc_html_e( 'Rezervovat termín', 'pneukarnik' ); ?></a>
				<?php if ( '' !== $pneukarnik_phone ) : ?>
					<a class="button button--secondary" href="<?php echo esc_url( pneukarnik_tel_href( $pneukarnik_phone ) ); ?>"><span aria-hidden="true">☎ </span><?php echo esc_html( $pneukarnik_phone ); ?></a>
				<?php endif; ?>
			</p>
			<?php pneukarnik_today_tomorrow_hours(); ?>
		</div>
	</section>

	<?php if ( $pneukarnik_services ) : ?>
		<section class="section--muted uvod__sluzby" id="sluzby">
			<div class="section__inner">
				<h2 class="section__title"><?php esc_html_e( 'Co pro vás uděláme', 'pneukarnik' ); ?></h2>
				<ul class="karty-sluzeb">
					<?php foreach ( $pneukarnik_services as $pneukarnik_service ) : ?>
						<?php pneukarnik_service_card( $pneukarnik_service, isset( $pneukarnik_promotions[ $pneukarnik_service->id ] ), $pneukarnik_guides[ $pneukarnik_service->id ] ?? null ); ?>
					<?php endforeach; ?>
				</ul>
				<p class="uvod__vse">
					<a class="button button--outline" href="<?php echo esc_url( Pneukarnik_Service::services_url() ); ?>">
						<?php
						echo esc_html(
							$pneukarnik_all_count >= 5
								/* translators: %d: počet zveřejněných Služeb, 5 a víc */
								? sprintf( __( 'Všech %d služeb', 'pneukarnik' ), $pneukarnik_all_count )
								: __( 'Všechny služby', 'pneukarnik' )
						);
						?>
						<span aria-hidden="true">→</span>
					</a>
				</p>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( $pneukarnik_why_us ) : ?>
		<section class="uvod__proc">
			<div class="section__inner section__inner--narrow">
				<h2 class="section__title"><?php esc_html_e( 'Proč k nám', 'pneukarnik' ); ?></h2>
				<ol class="duvody">
					<?php foreach ( $pneukarnik_why_us as $pneukarnik_reason ) : ?>
						<li class="duvod">
							<div class="duvod__obsah">
								<strong class="duvod__nadpis"><?php echo esc_html( $pneukarnik_reason['title'] ); ?></strong>
								<?php if ( '' !== $pneukarnik_reason['text'] ) : ?>
									<span class="duvod__text"><?php echo esc_html( $pneukarnik_reason['text'] ); ?></span>
								<?php endif; ?>
							</div>
						</li>
					<?php endforeach; ?>
				</ol>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( $pneukarnik_reviews ) : ?>
		<section class="section--muted uvod__recenze">
			<div class="section__inner">
				<h2 class="section__title"><?php esc_html_e( 'Hodnocení na Google', 'pneukarnik' ); ?></h2>
				<?php pneukarnik_reviews( $pneukarnik_reviews ); ?>
			</div>
		</section>
	<?php endif; ?>

	<section class="uvod__kde">
		<div class="section__inner">
			<div class="uvod__kde-sloupce">
				<div class="uvod__doba">
					<h2><?php esc_html_e( 'Otevírací doba', 'pneukarnik' ); ?></h2>
					<?php pneukarnik_upcoming_hours(); ?>
				</div>
				<?php if ( '' !== Pneukarnik_Contact::map_embed_url() ) : ?>
					<div class="uvod__mapa">
						<h2><?php esc_html_e( 'Kde nás najdete', 'pneukarnik' ); ?></h2>
						<?php pneukarnik_map(); ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</section>
</main>
<?php
get_footer();
