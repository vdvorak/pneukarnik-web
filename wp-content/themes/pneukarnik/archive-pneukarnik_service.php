<?php
/**
 * Stránka Služby (/sluzby/) s přepínačem Vše / Pneuservis / Autoservis. Rozcestníky Kategorií
 * (/pneuservis/, /autoservis/) jsou tatáž stránka s jednou Kategorií. Zveřejněné Služby v nastaveném
 * pořadí, Služba s platnou Akcí má na kartě štítek.
 *
 * @package Pneukarnik
 */

$pneukarnik_current    = Pneukarnik_Service_Type::shown_category();
$pneukarnik_categories = null === $pneukarnik_current
	? Pneukarnik_Service::categories()
	: [ $pneukarnik_current => Pneukarnik_Service::categories()[ $pneukarnik_current ] ];
$pneukarnik_promotions = Pneukarnik_Promotion::current();

// Hlavní dotaz už má jen zveřejněné Služby zobrazených Kategorií v nastaveném pořadí, tady se jen rozdělí.
$pneukarnik_groups = array_fill_keys( array_keys( $pneukarnik_categories ), [] );
while ( have_posts() ) {
	the_post();
	$pneukarnik_service = Pneukarnik_Service::from_post( get_post() );
	if ( isset( $pneukarnik_groups[ $pneukarnik_service->category ] ) ) {
		$pneukarnik_groups[ $pneukarnik_service->category ][] = $pneukarnik_service;
	}
}

get_header();
?>
<main id="obsah" class="site-main">
	<?php
	pneukarnik_page_hero(
		__( 'Služby', 'pneukarnik' ),
		__( 'Pneuservis i autoservis pod jednou střechou. Vyberte, co potřebujete, a rovnou se objednejte.', 'pneukarnik' ),
		null,
		false,
		static fn() => pneukarnik_services_filter( $pneukarnik_current )
	);
	?>
	<div class="sluzby">
		<?php foreach ( $pneukarnik_categories as $pneukarnik_category => $pneukarnik_label ) : ?>
			<?php $pneukarnik_services = $pneukarnik_groups[ $pneukarnik_category ]; ?>
			<section class="sluzby__kategorie" aria-labelledby="kategorie-<?php echo esc_attr( $pneukarnik_category ); ?>">
				<h2 id="kategorie-<?php echo esc_attr( $pneukarnik_category ); ?>"><?php echo esc_html( $pneukarnik_label ); ?></h2>
				<?php if ( $pneukarnik_services ) : ?>
					<ul class="karty-sluzeb">
						<?php foreach ( $pneukarnik_services as $pneukarnik_service ) : ?>
							<?php pneukarnik_service_card( $pneukarnik_service, isset( $pneukarnik_promotions[ $pneukarnik_service->id ] ) ); ?>
						<?php endforeach; ?>
					</ul>
				<?php else : ?>
					<p class="sluzby__prazdne"><?php esc_html_e( 'Služby připravujeme.', 'pneukarnik' ); ?></p>
				<?php endif; ?>
			</section>
		<?php endforeach; ?>
	</div>
</main>
<?php
get_footer();
