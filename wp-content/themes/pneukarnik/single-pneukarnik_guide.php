<?php
/**
 * Průvodce: nadpis, perex, text a na konci rezervace vybrané Služby.
 * Bez zveřejněné Služby nabídne obecnou rezervaci a telefon.
 *
 * @package Pneukarnik
 */

get_header();
the_post();
$guide   = Pneukarnik_Guide::from_post( get_post() );
$service = $guide->service();
?>
<main id="obsah" class="site-main pruvodce">
	<article>
		<header>
			<p class="pruvodce__stitek"><?php esc_html_e( 'Průvodce', 'pneukarnik' ); ?></p>
			<h1><?php echo esc_html( $guide->title ); ?></h1>
			<p class="pruvodce__perex"><?php echo esc_html( $guide->perex ); ?></p>
		</header>

		<div class="pruvodce__text">
			<?php the_content(); ?>
		</div>

		<section class="pruvodce__objednat">
			<?php if ( $service ) : ?>
				<h2>
					<?php
					/* translators: %s: název Služby */
					echo esc_html( sprintf( __( 'Objednejte se: %s', 'pneukarnik' ), $service->title ) );
					?>
				</h2>
				<p><a href="<?php echo esc_url( $service->url() ); ?>"><?php esc_html_e( 'Co Služba zahrnuje a kolik stojí', 'pneukarnik' ); ?></a></p>
				<?php pneukarnik_service_cta( $service ); ?>
			<?php else : ?>
				<h2><?php esc_html_e( 'Objednejte se', 'pneukarnik' ); ?></h2>
				<?php pneukarnik_contact_cta( 'pruvodce__cta' ); ?>
			<?php endif; ?>
		</section>
	</article>
</main>
<?php
get_footer();
