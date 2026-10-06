<?php
/**
 * Stránka bez vlastní šablony (O nás …): PageHero s názvem a perexem (výňatek stránky)
 * a text z editoru v úzkém sloupci.
 *
 * @package Pneukarnik
 */

get_header();
?>
<main id="obsah" class="site-main">
	<?php
	while ( have_posts() ) :
		the_post();
		?>
		<article <?php post_class(); ?>>
			<?php pneukarnik_page_hero( get_the_title(), has_excerpt() ? get_the_excerpt() : '' ); ?>
			<div class="stranka obsah">
				<?php the_content(); ?>
			</div>
		</article>
		<?php
	endwhile;
	?>
</main>
<?php
get_footer();
