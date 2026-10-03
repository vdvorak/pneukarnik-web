<?php
/**
 * Kontakt: adresa a spojení, otevírací doba, příjezd (text stránky), mapa po kliknutí a fakturační údaje.
 * Vše kromě příjezdu je z Nastavení pluginu (jedno místo kontaktů). Prázdné části se nevykreslí.
 *
 * @package Pneukarnik
 */

get_header();
the_post();
$pneukarnik_address = Pneukarnik_Contact::address();
$pneukarnik_phone   = Pneukarnik_Contact::phone();
$pneukarnik_email   = Pneukarnik_Contact::email();
$pneukarnik_ico     = Pneukarnik_Contact::ico();
$pneukarnik_dic     = Pneukarnik_Contact::dic();
$pneukarnik_arrival = trim( wp_strip_all_tags( get_the_content() ) );
?>
<main id="obsah" class="site-main kontakt">
	<h1><?php the_title(); ?></h1>

	<section class="kontakt__spojeni">
		<h2><?php esc_html_e( 'Adresa a spojení', 'pneukarnik' ); ?></h2>
		<address>
			<strong><?php echo esc_html( Pneukarnik_Contact::company() ); ?></strong><br>
			<?php if ( '' !== $pneukarnik_address ) : ?>
				<?php echo esc_html( $pneukarnik_address ); ?><br>
			<?php endif; ?>
			<?php if ( '' !== $pneukarnik_phone ) : ?>
				<?php esc_html_e( 'Tel.:', 'pneukarnik' ); ?> <a href="<?php echo esc_url( pneukarnik_tel_href( $pneukarnik_phone ) ); ?>"><?php echo esc_html( $pneukarnik_phone ); ?></a><br>
			<?php endif; ?>
			<?php if ( '' !== $pneukarnik_email ) : ?>
				<?php esc_html_e( 'E‑mail:', 'pneukarnik' ); ?> <a href="<?php echo esc_url( 'mailto:' . $pneukarnik_email ); ?>"><?php echo esc_html( $pneukarnik_email ); ?></a>
			<?php endif; ?>
		</address>
	</section>

	<section class="kontakt__doba">
		<h2><?php esc_html_e( 'Otevírací doba', 'pneukarnik' ); ?></h2>
		<?php pneukarnik_upcoming_hours(); ?>
	</section>

	<?php if ( '' !== $pneukarnik_arrival ) : ?>
		<section class="kontakt__prijezd">
			<h2><?php esc_html_e( 'Jak k nám', 'pneukarnik' ); ?></h2>
			<?php the_content(); ?>
		</section>
	<?php endif; ?>

	<?php if ( '' !== Pneukarnik_Contact::map_embed_url() ) : ?>
		<section class="kontakt__mapa">
			<h2><?php esc_html_e( 'Mapa', 'pneukarnik' ); ?></h2>
			<?php pneukarnik_map(); ?>
		</section>
	<?php endif; ?>

	<?php if ( '' !== $pneukarnik_ico || '' !== $pneukarnik_dic ) : ?>
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
</main>
<?php
get_footer();
