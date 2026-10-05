<?php
/**
 * Záložní šablona pro stránky, které ještě nemají vlastní: PageHero s názvem a text z editoru.
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
			<?php pneukarnik_page_hero( get_the_title(), '', null, true ); ?>
			<div class="obsah">
				<?php the_content(); ?>
			</div>
		</article>
		<?php
	endwhile;
	?>
</main>
<?php
get_footer();
