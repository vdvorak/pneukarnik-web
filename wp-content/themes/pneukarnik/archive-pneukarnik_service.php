<?php
/**
 * Rozcestník Kategorie (/pneuservis/, /autoservis/): zveřejněné Služby v nastaveném pořadí,
 * Služba s platnou Akcí má na kartě štítek.
 *
 * @package Pneukarnik
 */

get_header();
$promotions = Pneukarnik_Promotion::current();
?>
<main id="obsah" class="site-main kategorie">
	<h1><?php post_type_archive_title(); ?></h1>
	<?php if ( have_posts() ) : ?>
		<ul class="kategorie__sluzby">
			<?php
			while ( have_posts() ) :
				the_post();
				pneukarnik_service_card( Pneukarnik_Service::from_post( get_post() ), isset( $promotions[ get_the_ID() ] ) );
			endwhile;
			?>
		</ul>
	<?php else : ?>
		<p><?php esc_html_e( 'Služby připravujeme.', 'pneukarnik' ); ?></p>
	<?php endif; ?>
</main>
<?php
get_footer();
