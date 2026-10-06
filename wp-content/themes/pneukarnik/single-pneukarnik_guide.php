<?php
/**
 * Průvodce: hero s nadtitulkem, nadpisem a perexem, text a na konci tmavý blok „Objednejte se“
 * s vybranou Službou (Rezervovat jen u online rezervovatelné) a Zavolat.
 * Bez zveřejněné Služby nabídne obecnou rezervaci a telefon.
 *
 * @package Pneukarnik
 */

get_header();
the_post();
$guide   = Pneukarnik_Guide::from_post( get_post() );
$service = $guide->service();
$phone   = Pneukarnik_Contact::phone();
?>
<main id="obsah" class="site-main pruvodce">
	<article>
		<header class="page-hero">
			<div class="page-hero__inner page-hero__inner--narrow">
				<p class="eyebrow"><?php esc_html_e( 'Průvodce', 'pneukarnik' ); ?></p>
				<h1 class="page-hero__title"><?php echo esc_html( $guide->title ); ?></h1>
				<p class="page-hero__lead pruvodce__perex"><?php echo esc_html( $guide->perex ); ?></p>
			</div>
		</header>

		<div class="stranka">
			<div class="obsah">
				<?php the_content(); ?>
			</div>

			<section class="pruvodce__objednat">
				<?php if ( $service ) : ?>
					<p class="eyebrow eyebrow--accent"><?php esc_html_e( 'Objednejte se', 'pneukarnik' ); ?></p>
					<h2 class="pruvodce__sluzba"><?php echo esc_html( $service->title ); ?></h2>
					<a class="pruvodce__odkaz arrow-link" href="<?php echo esc_url( $service->url() ); ?>"><?php esc_html_e( 'Co Služba zahrnuje a kolik stojí', 'pneukarnik' ); ?></a>
				<?php else : ?>
					<h2 class="pruvodce__sluzba"><?php esc_html_e( 'Objednejte se', 'pneukarnik' ); ?></h2>
				<?php endif; ?>
				<p class="pruvodce__tlacitka">
					<?php if ( ! $service || $service->bookable ) : ?>
						<a class="button" href="<?php echo esc_url( pneukarnik_booking_url( $service ) ); ?>"><?php esc_html_e( 'Rezervovat', 'pneukarnik' ); ?></a>
					<?php endif; ?>
					<?php if ( '' !== $phone ) : ?>
						<a class="button button--secondary" href="<?php echo esc_url( pneukarnik_tel_href( $phone ) ); ?>">
							<?php
							/* translators: %s: telefonní číslo */
							echo esc_html( sprintf( __( 'Zavolat %s', 'pneukarnik' ), $phone ) );
							?>
						</a>
					<?php endif; ?>
				</p>
			</section>
		</div>
	</article>
</main>
<?php
get_footer();
