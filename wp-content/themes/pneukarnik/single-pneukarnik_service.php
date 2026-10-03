<?php
/**
 * Detail Služby. Části v pořadí ze zadání, nevyplněné volitelné části se nevykreslí.
 *
 * @package Pneukarnik
 */

get_header();
the_post();
$service = Pneukarnik_Service::from_post( get_post() );
$related = $service->related();
?>
<main id="obsah" class="site-main sluzba">
	<article>
		<header>
			<p class="sluzba__kategorie"><a href="<?php echo esc_url( Pneukarnik_Service::category_url( $service->category ) ); ?>"><?php echo esc_html( $service->category_label() ); ?></a></p>
			<h1><?php echo esc_html( $service->title ); ?></h1>
			<p class="sluzba__perex"><?php echo esc_html( $service->perex ); ?></p>
		</header>

		<?php if ( $service->includes ) : ?>
			<section class="sluzba__zahrnuje">
				<h2><?php esc_html_e( 'Co zahrnuje', 'pneukarnik' ); ?></h2>
				<ul>
					<?php foreach ( $service->includes as $item ) : ?>
						<li><?php echo esc_html( $item ); ?></li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endif; ?>

		<?php if ( '' !== $service->process || '' !== $service->duration_text ) : ?>
			<section class="sluzba__prubeh">
				<h2><?php esc_html_e( 'Jak to probíhá', 'pneukarnik' ); ?></h2>
				<?php echo wp_kses_post( wpautop( esc_html( $service->process ) ) ); ?>
				<?php if ( '' !== $service->duration_text ) : ?>
					<p><strong><?php esc_html_e( 'Jak dlouho to trvá:', 'pneukarnik' ); ?></strong> <?php echo esc_html( $service->duration_text ); ?></p>
				<?php endif; ?>
			</section>
		<?php endif; ?>

		<section class="sluzba__cena">
			<h2><?php esc_html_e( 'Cena', 'pneukarnik' ); ?></h2>
			<p class="sluzba__castka"><?php echo esc_html( pneukarnik_price_label( $service ) ); ?></p>
			<?php if ( '' !== $service->price_note ) : ?>
				<p><?php esc_html_e( 'Cena zahrnuje:', 'pneukarnik' ); ?> <?php echo esc_html( $service->price_note ); ?></p>
			<?php endif; ?>
		</section>

		<?php if ( $service->bring ) : ?>
			<section class="sluzba__s-sebou">
				<h2><?php esc_html_e( 'Co si vzít s sebou', 'pneukarnik' ); ?></h2>
				<ul>
					<?php foreach ( $service->bring as $item ) : ?>
						<li><?php echo esc_html( $item ); ?></li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endif; ?>

		<?php if ( $service->faq ) : ?>
			<section class="sluzba__dotazy">
				<h2><?php esc_html_e( 'Časté dotazy', 'pneukarnik' ); ?></h2>
				<?php foreach ( $service->faq as $item ) : ?>
					<details>
						<summary><?php echo esc_html( $item['question'] ); ?></summary>
						<?php echo wp_kses_post( wpautop( esc_html( $item['answer'] ) ) ); ?>
					</details>
				<?php endforeach; ?>
			</section>
		<?php endif; ?>

		<?php pneukarnik_service_cta( $service ); ?>

		<?php if ( $related ) : ?>
			<section class="sluzba__souvisejici">
				<h2><?php esc_html_e( 'Související služby', 'pneukarnik' ); ?></h2>
				<ul>
					<?php foreach ( $related as $other ) : ?>
						<li><a href="<?php echo esc_url( $other->url() ); ?>"><?php echo esc_html( $other->title ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endif; ?>
	</article>
</main>
<?php
get_footer();
