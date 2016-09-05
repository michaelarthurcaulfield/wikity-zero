<?php
/**
 * The template for displaying the footer
 *
 * Contains the closing of the "site-content" div and all content after.
 *
 * @package WordPress
 * @subpackage Twenty_Fifteen
 * @since Twenty Fifteen 1.0
 */
?>

	</div><!-- .site-content -->
	


	<footer id="colophon" class="site-footer" role="contentinfo">
		<div class="site-info">
			<?php
				/**
				 * Fires before the Twenty Fifteen footer text for footer customization.
				 *
				 * @since Twenty Fifteen 1.0
				 */
				do_action( 'twentyfifteen_credits' );
                if (!is_page()){
				$footer = getSetting("Footer");
				if ($footer == ''){
		       $footer_settings = "Original content licensed CC-BY-SA. Articles may contain material under different licenses, check the links, history, and other attribution.\n\nSite proudly powered by WordPress.";
		       $my_post = array(
		        'slug'           => "settings-footer",
		        'post_content'   => $footer_settings,
		        'post_title'     => "Settings:: Footer",
		        'post_status'    => 'publish'
		        );
		      wp_insert_post( $my_post, true );
				}
				$parsedown = new Parsedown_WP_Parser();
				echo $parsedown->text($footer);
			} else {
			    echo '<i>This site is run on Wikity software, a hypertext card system that runs on WordPress. You can get <a href="https://github.com/michaelarthurcaulfield/wikity-zero/" style="color:blue">Wikity for free on GitHub</a>.</i>';
			}
                ?>
		</div><!-- .site-info -->
	</footer><!-- .site-footer -->


</div><!-- .site -->

<?php wp_footer(); ?>

</body>
</html>
