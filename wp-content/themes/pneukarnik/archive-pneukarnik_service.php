<?php
/**
 * Rozcestník Kategorie (/pneuservis/, /autoservis/): zveřejněné Služby v nastaveném pořadí.
 *
 * @package Pneukarnik
 */

get_header();
?>
<main id="obsah" class="site-main kategorie">
	<h1><?php post_type_archive_title(); ?></h1>
	<?php if ( have_posts() ) : ?>
		<ul class="kategorie__sluzby">
			<?php
			while ( have_posts() ) :
				the_post();
				$service = Pneukarnik_Service::from_post( get_post() );
				?>
				<li class="karta-sluzby">
					<h2><a href="<?php echo esc_url( $service->url() ); ?>"><?php echo esc_html( $service->title ); ?></a></h2>
					<p><?php echo esc_html( $service->perex ); ?></p>
					<p class="karta-sluzby__cena"><?php echo esc_html( pneukarnik_price_label( $service ) ); ?></p>
				</li>
			<?php endwhile; ?>
		</ul>
	<?php else : ?>
		<p><?php esc_html_e( 'Služby připravujeme.', 'pneukarnik' ); ?></p>
	<?php endif; ?>
</main>
<?php
get_footer();
