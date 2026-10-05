<?php
/**
 * Hlavička stránky: Pohotovost a Oznámení nad ní (když jsou), značka, menu, telefon a Rezervovat.
 * Pod 900 px jen ikona telefonu a Menu, které jde otevřít i bez JavaScriptu (<details>).
 *
 * @package Pneukarnik
 */

$pneukarnik_phone = Pneukarnik_Contact::phone();
$pneukarnik_city  = Pneukarnik_Contact::postal_address()['city'];

[ $pneukarnik_prefix, $pneukarnik_name ] = pneukarnik_brand();
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
<a class="skip-link" href="#obsah"><?php esc_html_e( 'Přeskočit na obsah', 'pneukarnik' ); ?></a>
<?php pneukarnik_emergency(); ?>
<?php pneukarnik_top_notice(); ?>
<header class="site-header">
	<div class="site-header__inner">
		<a class="znacka" href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<img class="znacka__logo" src="<?php echo esc_url( get_theme_file_uri( 'assets/img/logo-jvk.png' ) ); ?>" alt="" width="60" height="60">
			<span class="znacka__text">
				<span class="znacka__nazev"><span class="znacka__prefix"><?php echo esc_html( $pneukarnik_prefix ); ?></span> <?php echo esc_html( $pneukarnik_name ); ?></span>
				<span class="znacka__podtitul">
					<?php
					esc_html_e( 'Pneu a autoservis', 'pneukarnik' );
					echo '' !== $pneukarnik_city ? esc_html( ' · ' . $pneukarnik_city ) : '';
					?>
				</span>
			</span>
		</a>

		<nav class="site-nav" aria-label="<?php esc_attr_e( 'Hlavní menu', 'pneukarnik' ); ?>">
			<?php pneukarnik_nav_links(); ?>
		</nav>

		<div class="site-header__kontakt">
			<?php if ( '' !== $pneukarnik_phone ) : ?>
				<a class="site-header__telefon" href="<?php echo esc_url( pneukarnik_tel_href( $pneukarnik_phone ) ); ?>"><span aria-hidden="true">☎ </span><?php echo esc_html( $pneukarnik_phone ); ?></a>
			<?php endif; ?>
			<a class="button button--md" href="<?php echo esc_url( home_url( '/rezervace/' ) ); ?>"><?php esc_html_e( 'Rezervovat', 'pneukarnik' ); ?></a>
		</div>

		<?php if ( '' !== $pneukarnik_phone ) : ?>
			<a class="site-header__ikona" href="<?php echo esc_url( pneukarnik_tel_href( $pneukarnik_phone ) ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: telefonní číslo */ __( 'Zavolat %s', 'pneukarnik' ), $pneukarnik_phone ) ); ?>"><span aria-hidden="true">☎</span></a>
		<?php endif; ?>
		<details class="mobilni-menu">
			<summary><span class="mobilni-menu__otevrit"><?php esc_html_e( 'Menu', 'pneukarnik' ); ?></span><span class="mobilni-menu__zavrit"><?php esc_html_e( 'Zavřít', 'pneukarnik' ); ?></span></summary>
			<nav class="mobilni-menu__panel" aria-label="<?php esc_attr_e( 'Menu', 'pneukarnik' ); ?>">
				<?php pneukarnik_nav_links(); ?>
				<a class="button button--block" href="<?php echo esc_url( home_url( '/rezervace/' ) ); ?>"><?php esc_html_e( 'Rezervovat', 'pneukarnik' ); ?></a>
			</nav>
		</details>
	</div>
</header>
