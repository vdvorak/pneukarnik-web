<?php
/**
 * Detail Služby: PageHero s drobečkem na Kategorii, obsah ve dvou třetinách a vpravo přilepená karta
 * Cena (na mobilu pod hero, akce řeší mobilní lišta). Nevyplněné volitelné části se nevykreslí.
 *
 * @package Pneukarnik
 */

get_header();
the_post();
$service   = Pneukarnik_Service::from_post( get_post() );
$related   = $service->related();
$promotion = Pneukarnik_Promotion::current_for( $service->id );
?>
<main id="obsah" class="site-main">
	<article>
		<?php pneukarnik_page_hero( $service->title, $service->perex, [ $service->category_label(), Pneukarnik_Service::category_url( $service->category ) ] ); ?>

		<div class="sluzba">
			<?php pneukarnik_service_price_card( $service ); ?>

			<div class="sluzba__obsah">
				<?php
				if ( $promotion ) {
					pneukarnik_promotion_block( $promotion );
				}
				?>

				<?php if ( $service->includes ) : ?>
					<section class="sluzba__cast">
						<h2><?php esc_html_e( 'Co zahrnuje', 'pneukarnik' ); ?></h2>
						<ul class="check-list sluzba__seznam">
							<?php foreach ( $service->includes as $item ) : ?>
								<li><?php echo esc_html( $item ); ?></li>
							<?php endforeach; ?>
						</ul>
					</section>
				<?php endif; ?>

				<?php if ( '' !== $service->process || '' !== $service->duration_text ) : ?>
					<section class="sluzba__cast sluzba__prubeh">
						<h2><?php esc_html_e( 'Jak to probíhá', 'pneukarnik' ); ?></h2>
						<?php echo wp_kses_post( wpautop( esc_html( $service->process ) ) ); ?>
						<?php if ( '' !== $service->duration_text ) : ?>
							<p class="sluzba__delka"><strong><?php esc_html_e( 'Jak dlouho to trvá:', 'pneukarnik' ); ?></strong> <?php echo esc_html( $service->duration_text ); ?></p>
						<?php endif; ?>
					</section>
				<?php endif; ?>

				<?php if ( $service->bring ) : ?>
					<section class="sluzba__cast">
						<h2><?php esc_html_e( 'Co si vzít s sebou', 'pneukarnik' ); ?></h2>
						<ul class="check-list sluzba__seznam">
							<?php foreach ( $service->bring as $item ) : ?>
								<li><?php echo esc_html( $item ); ?></li>
							<?php endforeach; ?>
						</ul>
					</section>
				<?php endif; ?>

				<?php if ( $service->faq ) : ?>
					<section class="sluzba__cast sluzba__dotazy">
						<h2><?php esc_html_e( 'Časté dotazy', 'pneukarnik' ); ?></h2>
						<div class="accordion">
							<?php foreach ( $service->faq as $item ) : ?>
								<details class="accordion__item">
									<summary><?php echo esc_html( $item['question'] ); ?></summary>
									<div class="accordion__body"><?php echo wp_kses_post( wpautop( esc_html( $item['answer'] ) ) ); ?></div>
								</details>
							<?php endforeach; ?>
						</div>
					</section>
				<?php endif; ?>

				<?php if ( $related ) : ?>
					<section class="sluzba__cast sluzba__souvisejici">
						<h2><?php esc_html_e( 'Související služby', 'pneukarnik' ); ?></h2>
						<ul>
							<?php foreach ( $related as $other ) : ?>
								<li><a class="arrow-link" href="<?php echo esc_url( $other->url() ); ?>"><?php echo esc_html( $other->title ); ?></a></li>
							<?php endforeach; ?>
						</ul>
					</section>
				<?php endif; ?>
			</div>
		</div>
	</article>
</main>
<?php
get_footer();
