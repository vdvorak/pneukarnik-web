<?php
/**
 * Ochrana osobních údajů: PageHero s perexem, Správce z kontaktních údajů v Nastavení pluginu
 * (nevyplněné řádky se nevykreslí) a text z editoru v úzkém sloupci.
 *
 * @package Pneukarnik
 */

get_header();
the_post();
$pneukarnik_email      = Pneukarnik_Contact::email();
$pneukarnik_controller = array_filter(
	[
		__( 'Adresa', 'pneukarnik' ) => Pneukarnik_Contact::address(),
		__( 'IČ', 'pneukarnik' )     => Pneukarnik_Contact::ico(),
	]
);
?>
<main id="obsah" class="site-main">
	<article <?php post_class(); ?>>
		<?php pneukarnik_page_hero( get_the_title(), has_excerpt() ? get_the_excerpt() : '', null, true ); ?>
		<div class="stranka obsah">
			<h2><?php esc_html_e( 'Správce', 'pneukarnik' ); ?></h2>
			<dl class="spravce">
				<dt><?php esc_html_e( 'Firma', 'pneukarnik' ); ?></dt>
				<dd><strong><?php echo esc_html( Pneukarnik_Contact::company() ); ?></strong></dd>
				<?php foreach ( $pneukarnik_controller as $pneukarnik_label => $pneukarnik_value ) : ?>
					<dt><?php echo esc_html( $pneukarnik_label ); ?></dt>
					<dd><?php echo esc_html( $pneukarnik_value ); ?></dd>
				<?php endforeach; ?>
				<?php if ( '' !== $pneukarnik_email ) : ?>
					<dt><?php esc_html_e( 'E‑mail', 'pneukarnik' ); ?></dt>
					<dd><a href="<?php echo esc_url( 'mailto:' . $pneukarnik_email ); ?>"><?php echo esc_html( $pneukarnik_email ); ?></a></dd>
				<?php endif; ?>
			</dl>
			<?php the_content(); ?>
		</div>
	</article>
</main>
<?php
get_footer();
