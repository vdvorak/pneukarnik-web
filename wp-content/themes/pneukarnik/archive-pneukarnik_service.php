<?php
/**
 * Stránka Služby (/sluzby/) se všemi Službami pod nadpisy Kategorií. Rozcestníky Kategorií
 * (/pneuservis/, /autoservis/) jsou tatáž stránka s jednou Kategorií, slouží hlavně vyhledávačům
 * (odkaz z patičky) a vedou zpět na všechny Služby. Zveřejněné Služby v nastaveném pořadí, Služby
 * s platnou Akcí napřed a se štítkem, Služba se zveřejněným Průvodcem „i“ s odkazem na něj.
 *
 * @package Pneukarnik
 */

$pneukarnik_current    = Pneukarnik_Service_Type::shown_category();
$pneukarnik_categories = null === $pneukarnik_current
	? Pneukarnik_Service::categories()
	: [ $pneukarnik_current => Pneukarnik_Service::categories()[ $pneukarnik_current ] ];
$pneukarnik_promotions = Pneukarnik_Promotion::current();
$pneukarnik_guides     = Pneukarnik_Guide::by_service();

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
	if ( null === $pneukarnik_current ) {
		pneukarnik_page_hero( __( 'Služby', 'pneukarnik' ), __( 'Pneuservis i autoservis pod jednou střechou. Vyberte, co potřebujete, a rovnou se objednejte.', 'pneukarnik' ) );
	} else {
		pneukarnik_page_hero( $pneukarnik_categories[ $pneukarnik_current ], pneukarnik_category_lead( $pneukarnik_current ), [ __( 'Všechny služby', 'pneukarnik' ), Pneukarnik_Service::services_url() ] );
	}
	?>
	<div class="sluzby">
		<?php foreach ( $pneukarnik_categories as $pneukarnik_category => $pneukarnik_label ) : ?>
			<?php $pneukarnik_services = Pneukarnik_Service::promoted_first( $pneukarnik_groups[ $pneukarnik_category ], $pneukarnik_promotions ); ?>
			<section class="sluzby__kategorie"<?php echo null === $pneukarnik_current ? ' aria-labelledby="kategorie-' . esc_attr( $pneukarnik_category ) . '"' : ''; ?>>
				<?php if ( null === $pneukarnik_current ) : ?>
					<h2 id="kategorie-<?php echo esc_attr( $pneukarnik_category ); ?>"><?php echo esc_html( $pneukarnik_label ); ?></h2>
				<?php endif; ?>
				<?php if ( $pneukarnik_services ) : ?>
					<ul class="karty-sluzeb">
						<?php foreach ( $pneukarnik_services as $pneukarnik_service ) : ?>
							<?php pneukarnik_service_card( $pneukarnik_service, $pneukarnik_promotions[ $pneukarnik_service->id ] ?? null, $pneukarnik_guides[ $pneukarnik_service->id ] ?? null ); ?>
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
