<?php
/**
 * The sidebar containing the main widget area
 *
 * @package WordPress
 * @subpackage Twenty_Fifteen
 * @since Twenty Fifteen 1.0
 */

if ( has_nav_menu( 'primary' ) || has_nav_menu( 'social' ) || is_active_sidebar( 'sidebar-1' )  ) : ?>
	<div id="secondary" class="secondary">
	<?php
	if (strlen($_GET['path']) > 0) {
	  $path_name = str_replace("\'","'",$_GET['path']);
	  ?>
<style>
  .path-list > A {border-style: solid; border-bottom: thin dotted grey; width:100%;}
</style>
	<nav id="wikity-path-list" class="main-navigation path-list" role="navigation" style="border-width: 5px">
	    <h2 class="widget-title"><?php echo $path_name ?></h2>
			 <?php
	    $path_post = get_page_by_path(sanitize_title('Path:: '.$path_name), OBJECT, 'post');
      $path_content = $path_post->post_content;
      $path_content = str_replace("\n;","<br>",$path_content);
      $path_content = preg_replace_callback('/\[\[(.*?)\]\]/', 'wiki_links', $path_content);
			$path_content = preg_replace('/>\*([^<]*)</','><div style="margin-left:20px; font-size:80%; border:none; display:list-item">$1</div><',$path_content);
      echo $path_content;
      ?>
	</nav>
	<?php
	}
	 if ( has_nav_menu( 'primary' ) ) : ?>
			<nav id="site-navigation" class="main-navigation" role="navigation">
				<?php
					// Primary navigation menu.
					wp_nav_menu( array(
						'menu_class'     => 'nav-menu',
						'theme_location' => 'primary',
					) );
				?>
			</nav><!-- .main-navigation -->
		<?php endif; ?>

		<?php if ( has_nav_menu( 'social' ) ) : ?>
			<nav id="social-navigation" class="social-navigation" role="navigation">
				<?php
					// Social links navigation menu.
					wp_nav_menu( array(
						'theme_location' => 'social',
						'depth'          => 1,
						'link_before'    => '<span class="screen-reader-text">',
						'link_after'     => '</span>',
					) );
				?>
			</nav><!-- .social-navigation -->
		<?php endif; ?>

		<?php if ( is_active_sidebar( 'sidebar-1' ) ) : ?>
			<div id="widget-area" class="widget-area" role="complementary">

				<?php dynamic_sidebar( 'sidebar-1' ); ?>
			</div><!-- .widget-area -->
		<?php endif; ?>

	</div><!-- .secondary -->

<?php endif; ?>
