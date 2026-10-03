<?php
/**
 * Záložní šablona pro stránky, které ještě nemají vlastní.
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
			<h1><?php the_title(); ?></h1>
			<?php the_content(); ?>
		</article>
		<?php
	endwhile;
	?>
</main>
<?php
get_footer();
