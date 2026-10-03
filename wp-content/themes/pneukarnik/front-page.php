<?php
/**
 * Úvod v pořadí ze zadání: (Oznámení v hlavičce) → hero s telefonem a Rezervovat → Kategorie →
 * nejžádanější Služby → Aktuální akce → proč k nám → Google recenze → otevírací doba na 7 dní → mapa.
 * Sekce bez obsahu se nevykreslí.
 *
 * @package Pneukarnik
 */

$pneukarnik_featured   = Pneukarnik_Service::featured();
$pneukarnik_promotions = Pneukarnik_Promotion::current();
$pneukarnik_why_us     = pneukarnik_why_us();
$pneukarnik_reviews    = Pneukarnik_Reviews::summary();

get_header();
?>
<main id="obsah" class="site-main site-main--uvod">
	<section class="uvod__hero">
		<h1><?php echo esc_html( Pneukarnik_Contact::company() ); ?></h1>
		<p><?php esc_html_e( 'Pneuservis a autoservis. Objednejte se online, nebo zavolejte.', 'pneukarnik' ); ?></p>
		<?php pneukarnik_contact_cta( 'uvod__cta' ); ?>
	</section>

	<section class="uvod__kategorie">
		<h2><?php esc_html_e( 'Co pro vás uděláme', 'pneukarnik' ); ?></h2>
		<ul class="dlazdice">
			<?php foreach ( Pneukarnik_Service::categories() as $pneukarnik_category => $pneukarnik_label ) : ?>
				<li><a class="dlazdice__odkaz" href="<?php echo esc_url( Pneukarnik_Service::category_url( $pneukarnik_category ) ); ?>"><?php echo esc_html( $pneukarnik_label ); ?></a></li>
			<?php endforeach; ?>
		</ul>
	</section>

	<?php if ( $pneukarnik_featured ) : ?>
		<section class="uvod__nejzadanejsi">
			<h2><?php esc_html_e( 'Nejžádanější služby', 'pneukarnik' ); ?></h2>
			<ul class="kategorie__sluzby">
				<?php foreach ( $pneukarnik_featured as $pneukarnik_service ) : ?>
					<?php pneukarnik_service_card( $pneukarnik_service, isset( $pneukarnik_promotions[ $pneukarnik_service->id ] ), 'h3' ); ?>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>

	<?php if ( $pneukarnik_promotions ) : ?>
		<section class="uvod__akce">
			<h2><?php esc_html_e( 'Aktuální akce', 'pneukarnik' ); ?></h2>
			<ul class="akce">
				<?php foreach ( $pneukarnik_promotions as $pneukarnik_promotion ) : ?>
					<?php $pneukarnik_service = $pneukarnik_promotion->service(); ?>
					<li class="akce__polozka">
						<h3><a href="<?php echo esc_url( $pneukarnik_service ? $pneukarnik_service->url() : '' ); ?>"><?php echo esc_html( $pneukarnik_promotion->title ); ?></a></h3>
						<p><?php echo esc_html( $pneukarnik_service ? $pneukarnik_service->title : '' ); ?>: <strong><?php echo esc_html( pneukarnik_amount( (int) $pneukarnik_promotion->price ) ); ?></strong></p>
						<p class="akce__platnost">
							<?php
							/* translators: %s: poslední den platnosti Akce, např. 31. 3. 2027 */
							echo esc_html( sprintf( __( 'Akce platí do %s.', 'pneukarnik' ), Pneukarnik_Clock::at( $pneukarnik_promotion->valid_to )->format( 'j. n. Y' ) ) );
							?>
						</p>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>

	<?php if ( $pneukarnik_why_us ) : ?>
		<section class="uvod__proc">
			<h2><?php esc_html_e( 'Proč k nám', 'pneukarnik' ); ?></h2>
			<ul>
				<?php foreach ( $pneukarnik_why_us as $pneukarnik_reason ) : ?>
					<li><?php echo esc_html( $pneukarnik_reason ); ?></li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>

	<?php if ( $pneukarnik_reviews ) : ?>
		<section class="uvod__recenze">
			<h2><?php esc_html_e( 'Hodnocení na Google', 'pneukarnik' ); ?></h2>
			<?php pneukarnik_reviews( $pneukarnik_reviews ); ?>
		</section>
	<?php endif; ?>

	<section class="uvod__doba">
		<h2><?php esc_html_e( 'Otevírací doba', 'pneukarnik' ); ?></h2>
		<?php pneukarnik_upcoming_hours(); ?>
	</section>

	<?php if ( '' !== Pneukarnik_Contact::map_embed_url() ) : ?>
		<section class="uvod__mapa">
			<h2><?php esc_html_e( 'Kde nás najdete', 'pneukarnik' ); ?></h2>
			<?php pneukarnik_map(); ?>
		</section>
	<?php endif; ?>
</main>
<?php
get_footer();
