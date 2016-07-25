<?php
/**
 * The template for displaying the header, based on Wordpress 2015
 *
 * Displays all of the head element and everything up until the "site-content" div.
 *
 * @package WordPress
 * @subpackage Wikity Zero
 * @since Wikity Zero 0.1
 */

 if (is_single()) {

   if (substr(get_the_title( $ID ),0,6) == 'Path::' and strlen($_GET['path']) == 0) {
     /* Get Path Name */
     $path_name= trim(explode(":",get_the_title())[2]);
     $chr_map = array(
	   // Windows codepage 1252
	   "\xC2\x82" => "'", // U+0082⇒U+201A single low-9 quotation mark
	   "\xC2\x84" => '"', // U+0084⇒U+201E double low-9 quotation mark
	   "\xC2\x8B" => "'", // U+008B⇒U+2039 single left-pointing angle quotation mark
	   "\xC2\x91" => "'", // U+0091⇒U+2018 left single quotation mark
	   "\xC2\x92" => "'", // U+0092⇒U+2019 right single quotation mark
	   "\xC2\x93" => '"', // U+0093⇒U+201C left double quotation mark
	   "\xC2\x94" => '"', // U+0094⇒U+201D right double quotation mark
	   "\xC2\x9B" => "'", // U+009B⇒U+203A single right-pointing angle quotation mark
	
	   // Regular Unicode     // U+0022 quotation mark (")
	                          // U+0027 apostrophe     (')
	   "\xC2\xAB"     => '"', // U+00AB left-pointing double angle quotation mark
	   "\xC2\xBB"     => '"', // U+00BB right-pointing double angle quotation mark
	   "\xE2\x80\x98" => "'", // U+2018 left single quotation mark
	   "\xE2\x80\x99" => "'", // U+2019 right single quotation mark
	   "\xE2\x80\x9A" => "'", // U+201A single low-9 quotation mark
	   "\xE2\x80\x9B" => "'", // U+201B single high-reversed-9 quotation mark
	   "\xE2\x80\x9C" => '"', // U+201C left double quotation mark
	   "\xE2\x80\x9D" => '"', // U+201D right double quotation mark
	   "\xE2\x80\x9E" => '"', // U+201E double low-9 quotation mark
	   "\xE2\x80\x9F" => '"', // U+201F double high-reversed-9 quotation mark
	   "\xE2\x80\xB9" => "'", // U+2039 single left-pointing angle quotation mark
	   "\xE2\x80\xBA" => "'", // U+203A single right-pointing angle quotation mark
		);
		$chr = array_keys  ($chr_map); // but: for efficiency you should
		$rpl = array_values($chr_map); // pre-calculate these two arrays
		$path_name = str_replace($chr, $rpl, html_entity_decode($path_name, ENT_QUOTES, "UTF-8"));

     $path_content= get_post(get_the_ID())->post_content;
     preg_match_all("/\[\[([^]]*)\]\]/", $path_content, $path_array);

     /* Get Start Page */

     header('Refresh:0; url=/'.sanitize_title_with_dashes($path_array[1][0]).'?path='.$path_name);
     die('');
   }
}

?><!DOCTYPE html>
<html <?php language_attributes(); ?> class="no-js">
<head>


	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width">
	<link rel="profile" href="http://gmpg.org/xfn/11">
	<link rel="pingback" href="<?php bloginfo( 'pingback_url' ); ?>">
	    <link rel="stylesheet" href="<?php echo esc_url( get_template_directory_uri() ); ?>/css/simplemde/simplemde.css">
	<!--[if lt IE 9]>
	<script src="<?php echo esc_url( get_template_directory_uri() ); ?>/js/html5.js"></script>
	<![endif]-->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/highlight.js/latest/styles/github.min.css">
	<?php wp_head(); ?>

	<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.11.2/jquery.min.js"></script>
  <script src="https://npmcdn.com/imagesloaded@4.1/imagesloaded.pkgd.min.js"></script>

  <xscript src="http://wikity.net/defiant-latest.min.js"></xscript>
  <xscript src="http://sugarjs.com/release/current/sugar.min.js"></xscript>

</head>

<body <?php body_class(); ?>>
<div id="page" class="hfeed site">
	<a class="skip-link screen-reader-text" href="#content"><?php _e( 'Skip to content', 'twentyfifteen' ); ?></a>

	<div id="sidebar" class="sidebar">
		<header id="masthead" class="site-header" role="banner" style="padding-bottom: 5px; margin-bottom: 20px;">
			<div class="site-branding">
				<?php
					if ( is_front_page() && is_home() ) : ?>
						<h1 class="site-title"><a href="<?php echo esc_url( home_url( '/?clear=1' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a></h1>
					<?php else : ?>
						<p class="site-title"><a href="<?php echo esc_url( home_url( '/?clear=1' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a></p>
					<?php endif;

					$description = get_bloginfo( 'description', 'display' );
					if ( $description || is_customize_preview() ) : ?>
						<p class="site-description"><?php echo $description; ?> <br><small><a href="http://wikity.cc/">Go to wikity.cc.</a>
             <br><a href="/home/">Go to home.</a></small></p>
					<?php endif;
				?>
				<button class="secondary-toggle"><?php _e( 'Menu and widgets', 'twentyfifteen' ); ?></button>
			</div><!-- .site-branding -->
		</header><!-- .site-header -->

		<?php get_sidebar(); ?>
	</div><!-- .sidebar -->

	<div id="content" class="site-content">
