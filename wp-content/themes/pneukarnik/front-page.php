<?php
/**
 * Úvod v pořadí ze zadání: (Oznámení v hlavičce) → hero s telefonem, Rezervovat a otevírací dobou
 * Dnes / Zítra → Kategorie → nejžádanější Služby → Aktuální akce → proč k nám → Google recenze →
 * otevírací doba na 7 dní a mapa. Sekce bez obsahu se nevykreslí.
 *
 * @package Pneukarnik
 */

$pneukarnik_featured   = Pneukarnik_Service::featured();
$pneukarnik_promotions = Pneukarnik_Promotion::current();
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

	<section class="uvod__kategorie" id="sluzby">
		<div class="section__inner">
			<h2 class="section__title"><?php esc_html_e( 'Co pro vás uděláme', 'pneukarnik' ); ?></h2>
			<ul class="dlazdice">
				<?php foreach ( Pneukarnik_Service::categories() as $pneukarnik_category => $pneukarnik_label ) : ?>
					<li class="card card--muted dlazdice__karta">
						<p class="dlazdice__stitek"><?php esc_html_e( 'Kategorie', 'pneukarnik' ); ?></p>
						<h3 class="dlazdice__nazev"><a class="dlazdice__odkaz" href="<?php echo esc_url( Pneukarnik_Service::category_url( $pneukarnik_category ) ); ?>"><?php echo esc_html( $pneukarnik_label ); ?></a></h3>
						<?php $pneukarnik_lead = pneukarnik_category_lead( $pneukarnik_category ); ?>
						<?php if ( '' !== $pneukarnik_lead ) : ?>
							<p class="dlazdice__popis"><?php echo esc_html( $pneukarnik_lead ); ?></p>
						<?php endif; ?>
						<p class="dlazdice__vice" aria-hidden="true"><?php esc_html_e( 'Zobrazit služby →', 'pneukarnik' ); ?></p>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</section>

	<?php if ( $pneukarnik_featured ) : ?>
		<section class="section--muted uvod__nejzadanejsi">
			<div class="section__inner">
				<h2 class="section__title"><?php esc_html_e( 'Nejžádanější služby', 'pneukarnik' ); ?></h2>
				<ul class="karty-sluzeb">
					<?php foreach ( $pneukarnik_featured as $pneukarnik_service ) : ?>
						<?php pneukarnik_service_card( $pneukarnik_service, isset( $pneukarnik_promotions[ $pneukarnik_service->id ] ) ); ?>
					<?php endforeach; ?>
				</ul>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( $pneukarnik_promotions ) : ?>
		<section class="uvod__akce">
			<div class="uvod__akce-panel">
				<h2><?php esc_html_e( 'Aktuální akce', 'pneukarnik' ); ?></h2>
				<ul class="akce">
					<?php foreach ( $pneukarnik_promotions as $pneukarnik_promotion ) : ?>
						<?php $pneukarnik_service = $pneukarnik_promotion->service(); ?>
						<li class="akce__polozka">
							<h3 class="akce__nazev">
								<?php if ( $pneukarnik_service ) : ?>
									<a class="arrow-link" href="<?php echo esc_url( $pneukarnik_service->url() ); ?>"><?php echo esc_html( $pneukarnik_promotion->title ); ?></a>
								<?php else : ?>
									<?php echo esc_html( $pneukarnik_promotion->title ); ?>
								<?php endif; ?>
							</h3>
							<p class="akce__cena"><?php echo esc_html( ( $pneukarnik_service ? $pneukarnik_service->title . ': ' : '' ) . pneukarnik_amount( (int) $pneukarnik_promotion->price ) ); ?></p>
							<p class="akce__platnost">
								<?php
								/* translators: %s: poslední den platnosti Akce, např. 31. 3. 2027 */
								echo esc_html( sprintf( __( 'Akce platí do %s.', 'pneukarnik' ), Pneukarnik_Clock::at( $pneukarnik_promotion->valid_to )->format( 'j. n. Y' ) ) );
								?>
							</p>
						</li>
					<?php endforeach; ?>
				</ul>
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
