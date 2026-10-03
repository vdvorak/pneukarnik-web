<?php
/**
 * Hlavička stránky: Oznámení, Pohotovost (když je zapnutá), telefon a Rezervovat na každé stránce.
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
<?php pneukarnik_top_notice(); ?>
<header class="site-header">
	<?php pneukarnik_emergency(); ?>
	<a class="site-title" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php bloginfo( 'name' ); ?></a>
	<?php pneukarnik_contact_cta( 'site-header__kontakt' ); ?>
	<nav class="site-nav" aria-label="<?php esc_attr_e( 'Hlavní menu', 'pneukarnik' ); ?>">
		<?php foreach ( Pneukarnik_Service::categories() as $pneukarnik_category => $pneukarnik_label ) : ?>
			<a href="<?php echo esc_url( Pneukarnik_Service::category_url( $pneukarnik_category ) ); ?>"><?php echo esc_html( $pneukarnik_label ); ?></a>
		<?php endforeach; ?>
		<a href="<?php echo esc_url( home_url( '/o-nas/' ) ); ?>"><?php esc_html_e( 'O nás', 'pneukarnik' ); ?></a>
		<a href="<?php echo esc_url( home_url( '/kontakt/' ) ); ?>"><?php esc_html_e( 'Kontakt', 'pneukarnik' ); ?></a>
		<a href="<?php echo esc_url( home_url( '/rezervace/' ) ); ?>"><?php esc_html_e( 'Rezervace', 'pneukarnik' ); ?></a>
	</nav>
</header>
