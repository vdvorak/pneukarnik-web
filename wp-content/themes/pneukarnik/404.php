<?php
/**
 * Stránka nenalezena.
 *
 * @package Pneukarnik
 */

get_header();
?>
<main id="obsah" class="site-main">
	<h1><?php esc_html_e( 'Stránka nenalezena', 'pneukarnik' ); ?></h1>
	<p><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Zpět na úvod', 'pneukarnik' ); ?></a></p>
</main>
<?php
get_footer();
