<?php
/**
 * Hlavička stránky.
 *
 * @package Pneukarnik
 */

?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<header class="site-header">
	<a class="site-title" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php bloginfo( 'name' ); ?></a>
	<nav class="site-nav" aria-label="<?php esc_attr_e( 'Hlavní menu', 'pneukarnik' ); ?>">
		<?php if ( class_exists( 'Pneukarnik_Service' ) ) : ?>
			<?php foreach ( Pneukarnik_Service::categories() as $pneukarnik_category => $pneukarnik_label ) : ?>
				<a href="<?php echo esc_url( Pneukarnik_Service::category_url( $pneukarnik_category ) ); ?>"><?php echo esc_html( $pneukarnik_label ); ?></a>
			<?php endforeach; ?>
		<?php endif; ?>
	</nav>
</header>
