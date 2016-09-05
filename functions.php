<?php
/**
 * Wikity functions and definitions
 *
 * Set up the theme and provides some helper functions, which are used in the
 * theme as custom template tags. Others are attached to action and filter
 * hooks in WordPress to change core functionality.
 *
 * When using a child theme you can override certain functions (those wrapped
 * in a function_exists() call) by defining them first in your child theme's
 * functions.php file. The child theme's functions.php file is included before
 * the parent theme's file, so the child theme functions would be used.
 *
 * @link https://codex.wordpress.org/Theme_Development
 * @link https://codex.wordpress.org/Child_Themes
 *
 * Functions that are not pluggable (not wrapped in function_exists()) are
 * instead attached to a filter or action hook.
 *
 * For more information on hooks, actions, and filters,
 * {@link https://codex.wordpress.org/Plugin_API}
 *
 * @package WordPress
 * @subpackage Twenty_Fifteen
 * @since Twenty Fifteen 1.0
 */
 
/***** Functions for forking ******/


function output_xml_headers(){
   header('Access-Control-Allow-Origin: *');
   header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
   header('Access-Control-Max-Age: 1000');
   if(array_key_exists('HTTP_ACCESS_CONTROL_REQUEST_HEADERS', $_SERVER)) {
       header('Access-Control-Allow-Headers: '
              . $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']);
   } else {
       header('Access-Control-Allow-Headers: *');
   }

   if("OPTIONS" == $_SERVER['REQUEST_METHOD']) {
       exit(0);
   }

   // IE XDR doesn't pass Content-Type so we won't have the POST data under $_POST
   if (count($_POST)==0) {
           if (isset($HTTP_RAW_POST_DATA)) {
                   $data = explode('&', $HTTP_RAW_POST_DATA);
                   foreach ($data as $val) {
                           if (!empty($val)) {
                                   list($key, $value) = explode('=', $val);
                                   $_POST[$key] = urldecode($value);
                           }
                   }
           }
   }
   header('Cache-Control: no-cache, must-revalidate');
   header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
   header('Content-type: application/json');
}


/* 
   isJson is used by getCard operations to determine 
   if a site is giving back JSON in reply to fedwiki calls 
*/

function isJson($string) {
   json_decode($string);
   return (json_last_error() == JSON_ERROR_NONE);
}

function bareImgToMd($c) {
 return preg_replace('/!(https?:\\/\\/)([^\s]+)(?=\s|$)/Ui', "\n![]($1$2 \"From $2. Used under fair use unless noted otherwise.\")", $c);
}


/* 
   getCard fetches wiki JSON from other site and posts 
   to this site (assuming you have admin privileges on it).
   
*/
 
function getSetting($setting){
    global $wpdb;
    $settingPost = "Settings:: ".$setting;
    $val = $wpdb->get_var($wpdb->prepare("SELECT post_content FROM $wpdb->posts WHERE post_title = %s", $settingPost));
    return $val;
}

function getCard($site, $slug, $wpdb){

  $slug = sanitize_title($slug); // prevent injection hijinks 
  
  if ( ! current_user_can( 'edit_posts' ) || ! current_user_can( get_post_type_object( 'post' )->cap->create_posts ) ) {
      require_once( ABSPATH . '/wp-admin/admin.php' );
      wp_die( __( 'Cheatin&#8217; uh?' ), 403 ); 
    }
  require_once( ABSPATH . '/wp-admin/includes/image.php' ); // for image copies

  // Get JSON from specified page
  
  $source_url = 'http://'.$site.'/?fedwiki='.$slug;
  $json =  @file_get_contents($source_url);
  
  if (($json === FALSE) || (isJson($json) === FALSE)) return '';

  // Get title and content
 
  $json_arr = json_decode($json, true);
  $title = $json_arr['title'];
  
  if ($title == '') return '';
  
  $content_arr = $json_arr['story'];
  $content='';
  foreach ($content_arr as $itm){
    $content .= $itm['text']."\r\n\r\n";
  }

  // If post exists, update, if not create

  $postid = $wpdb->get_var("SELECT ID FROM $wpdb->posts WHERE post_name = '$slug'");
  if ($postid !='') {
    $my_post = array(
        'ID'           => $postid,
        'post_content'   => $content
    );
    wp_update_post( $my_post );
  } else {
   $my_post = array(
    'slug'           => $slug,
    'post_content'   => $content,
    'post_title'     => $title,
    'post_status'    => 'publish'
    );
   wp_insert_post( $my_post, true );
   $postid = $wpdb->get_var("SELECT ID FROM $wpdb->posts WHERE post_name = '$slug'");
  }

  // Nuke existing history, recreate history from JSON

  $history = $json_arr['journal'];
  delete_post_meta($postid, 'history');

  // find create and add it first
  foreach ($history as $history_itm){
    if ($history_itm['type']=='create'){
      
       $journal_format = '{
      "type": "create",
      "id": "%s",
      "site": "%s",
      "author": "%s",
      "date": %s,
      "item": {
        "title": "%s"
      }
}';
       $safe_title = str_replace('"','\\\"', $history_itm['item']['title']);
       $history_str = sprintf($journal_format, $history_itm["id"],$history_itm['site'],$history_itm['author'],$history_itm['date'],$safe_title);
       add_post_meta($postid,'history',$history_str);
    }
  }
  
  // Add the other stuff
   foreach ($history as $history_itm){
    if ($history_itm['type']=='fork'){
      
       $journal_format = '{
      "type": "fork",
      "id": "%s",
      "site": "%s",
      "author": "%s",
      "date": %s,
      "slug": "%s",
      "name": "%s",
      "chars": %s
}';
       $history_str = sprintf($journal_format, $history_itm["id"],$history_itm['site'],$history_itm['author'],$history_itm['date'],$history_itm['slug'],$history_itm['name'],$history_itm['chars']);
       add_post_meta($postid,'history',$history_str);
    }
  }
  
  return $title;
 }
 
function getMainUrl($u){
  preg_match("/http[s]*:\/\/(.*)\/[^\/]*\/$/", $u, $u_arr);
  return $u_arr[1];
}

function getSlugFromUrl($u){
  preg_match("/http:\/\/.*\/([^\/]*)\/$/", $u, $u_arr);
  return $u_arr[1];
}

function process_urls($c, $wpdb){
  preg_match_all("/\[\[(http[^ \]]*)]]/", $c, $urls);
  foreach ($urls[1] as $url){
    $this_title = getCard(getMainUrl($url),getSlugFromUrl($url),$wpdb);
    if ($this_title != '') {
      $c = str_replace($url,$this_title,$c);
    } else {
       $c = str_replace($url,"Could not retrieve: $url",$c);
    }
  }
  return $c;
}


function prior_history($my_id){
  $ret = '';
  $history = get_post_meta( $my_id, 'history' );
  $initial = 1;
   if (gettype($history)=='array') {
    foreach ($history as $history_itm){
      if ($initial){
         $initial = 0;
       } else {
         $ret.= ",\r\n";
       }
     $ret.= $history_itm;
    }
   }

   return $ret;
}



if ($_GET['clear'] == 1){
  $form_s = "";
  $form_t = "";
  $form_c = "";
} else {
  session_start();

  $form_s = stripslashes($_POST['s']);
  $form_t = stripslashes($_POST['t']);
  $form_c = stripslashes($_POST['c']);
  if ($form_s == ''){$form_s == $_GET['s'];} // get is last resort, passed by page refreshes
  if ($form_s != ''){$_SESSION['s'] = $form_s;}
  if ($form_t != ''){$_SESSION['t'] = $form_t;}
  if ($form_c != ''){$_SESSION['c'] = $form_c;}
}



/* Disable smart quotes, which interfere with markdown */

remove_filter('the_content', 'wptexturize');
remove_filter('the_content', 'wpautop');

/* Disable Visual Editor Markdown shortcuts (they interfere with our own processing) */

function disable_mce_wptextpattern( $opt ) {
    if ( isset( $opt['plugins'] ) && $opt['plugins'] ) {
        $opt['plugins'] = explode( ',', $opt['plugins'] );
        $opt['plugins'] = array_diff( $opt['plugins'] , array( 'wptextpattern' ) );
        $opt['plugins'] = implode( ',', $opt['plugins'] );
    }
    return $opt;
}

add_filter( 'tiny_mce_before_init', 'disable_mce_wptextpattern' );

/* Short codes for Peloton */

function wiki_links($match) {
    $text = $match[1];
    $slug = sanitize_title_with_dashes($text, '', 'save');
    $cardbox = $_GET['cardbox'];
    return '<a id="'.$slug.'" href="'.get_site_url().'/'.$slug.'/?cardbox='.$cardbox.'&t='.$text.'" onclick="document.location.href=this.href + \'&sites=\' + search_context; return false;">'.$text.'</a>';
}


function cite_links($match) {
    $text = $match[1];
    $slug = sanitize_title_with_dashes($text, '', 'save');
    return '<a style="border:none" href="/'.$slug.'/?sites=pinkmoon.wikity.net,rainystreets.wikity.net&t='.$text.'">&#176;</a>';
}



function ext_links($match){
   $protocol = $match[1];
   $link = $match[2];
   $text = $match[3];
   switch($text){
     case "cite":
       $ret = '<a style="border-bottom: 0px" href="'.$protocol.'://'.$link.'">&#176;</a>';
       break;
     case "fairuse":
       $ret = '(<a title="Used under Fair Use. Click through for photo or graphic credit." style="border-bottom: 0px" href="'.$protocol.'://'.$link.'">credit</a>)';
       break;
     case "ccby":
       $ret = '(<a title="Used under CC BY. Click through for photo or graphic credit." style="border-bottom: 0px" href="'.$protocol.'://'.$link.'">credit</a>)';
       break;
     case "article":
       $ret = '(<a title="External Link" style="border-bottom: 0px" href="'.$protocol.'://'.$link.'">article</a>)';
     default:
       $ret = '(<a href="'.$protocol.'://'.$link.'">'.$text.'</a>)';
   }
   return $ret;
}

function get_w($atts) {
     $a = shortcode_atts( array(
        'l' => 'Missing Link Title'
    ), $atts );

    return '<a href="/'.sanitize_title_with_dashes($a['l'], '', 'save').'/?sites=pinkmoon.wikity.net,rainystreets.wikity.net&t='.$a['l'].'">'.$a['l'].'</a>';
}


function get_attribution($atts) {
     $a = shortcode_atts( array(
        'site' => 'site',
        'email' => 'email',
        'date' => 'date',
        'slug' => 'slug',
        'name' => 'sitename'
    ), $atts );

    return '<a href="'.$a['site'].'/'.$a['slug'].'">'.$a['t'].'</a>';
}


function get_cite($atts) {
     $a = shortcode_atts( array(
        'l' => 'Missing Link URL',
        't' => ''
    ), $atts );

    return '<a class="genericond genericon genericon-dot" onMouseOver="this.style.color=\'black\'" onMouseOut="this.style.color=\'gainsboro\'" style="text-decoration:none; border-bottom: 0px; color:gainsboro" title="'.$a['t'].'" href="'.$a['l'].'"></a>';
}


function get_source($atts) {
     $a = shortcode_atts( array(
        'l' => 'Missing Link URL',
        't' => 'Source.'
    ), $atts );

    return '<a class="genericond genericon genericon-external" title="'.$a['t'].'" href="'.$a['l'].'"> </a>';
}

   
function cite_output($atts, $content){
	return "<cite>".trim($content)."</cite>";
}

add_shortcode('cite','cite_output');


function get_create($atts) {
     $a = shortcode_atts( array(
        'email' => '',
        'site' => '',
        'slug' => '',
        'date' => ''
    ), $atts );
    $size = 30;
    $grav_url = "http://www.gravatar.com/avatar/" . $a['email'] ;
    $time = strtotime($a['date']);
    $fixed = date('M j, Y @ G:i', $time);

    return '<span style="font-size:15px"><img style="padding: 5px" src="'.$grav_url.'?size=40"> Copied from <a href="http://'.$a['site'].'/'.$a['slug'].'">'.$a['site'].'</a> on '.$fixed.' under <a href="https://creativecommons.org/licenses/by-sa/2.0/">CC BY-SA</a></span><br>';
}


add_shortcode('create', 'get_create');
add_shortcode('copy', 'get_create');


function get_pagehistory($atts) {
    return '<h5>Page History</h5>';

}

add_shortcode('pagehistory', 'get_pagehistory');


/**
 * Set the content width based on the theme's design and stylesheet.
 *
 * @since Twenty Fifteen 1.0
 */
if ( ! isset( $content_width ) ) {
	$content_width = 660;
}

/**
 * Twenty Fifteen only works in WordPress 4.1 or later.
 */
if ( version_compare( $GLOBALS['wp_version'], '4.1-alpha', '<' ) ) {
	require get_template_directory() . '/inc/back-compat.php';
}

if ( ! function_exists( 'twentyfifteen_setup' ) ) :
/**
 * Sets up theme defaults and registers support for various WordPress features.
 *
 * Note that this function is hooked into the after_setup_theme hook, which
 * runs before the init hook. The init hook is too late for some features, such
 * as indicating support for post thumbnails.
 *
 * @since Twenty Fifteen 1.0
 */
function twentyfifteen_setup() {

	/*
	 * Make theme available for translation.
	 * Translations can be filed in the /languages/ directory.
	 * If you're building a theme based on twentyfifteen, use a find and replace
	 * to change 'twentyfifteen' to the name of your theme in all the template files
	 */
	load_theme_textdomain( 'twentyfifteen', get_template_directory() . '/languages' );

	// Add default posts and comments RSS feed links to head.
	add_theme_support( 'automatic-feed-links' );

	/*
	 * Let WordPress manage the document title.
	 * By adding theme support, we declare that this theme does not use a
	 * hard-coded <title> tag in the document head, and expect WordPress to
	 * provide it for us.
	 */
	add_theme_support( 'title-tag' );

	/*
	 * Enable support for Post Thumbnails on posts and pages.
	 *
	 * See: https://codex.wordpress.org/Function_Reference/add_theme_support#Post_Thumbnails
	 */
	add_theme_support( 'post-thumbnails' );
	set_post_thumbnail_size( 825, 510, true );

	// This theme uses wp_nav_menu() in two locations.
	register_nav_menus( array(
		'primary' => __( 'Primary Menu',      'twentyfifteen' ),
		'social'  => __( 'Social Links Menu', 'twentyfifteen' ),
	) );

	/*
	 * Switch default core markup for search form, comment form, and comments
	 * to output valid HTML5.
	 */
	add_theme_support( 'html5', array(
		'search-form', 'comment-form', 'comment-list', 'gallery', 'caption'
	) );

	/*
	 * Enable support for Post Formats.
	 *
	 * See: https://codex.wordpress.org/Post_Formats
	 */
	add_theme_support( 'post-formats', array(
		'aside', 'image', 'video', 'quote', 'link', 'gallery', 'status', 'audio', 'chat'
	) );

	$color_scheme  = twentyfifteen_get_color_scheme();
	$default_color = trim( $color_scheme[0], '#' );

	// Setup the WordPress core custom background feature.
	add_theme_support( 'custom-background', apply_filters( 'twentyfifteen_custom_background_args', array(
		'default-color'      => $default_color,
		'default-attachment' => 'fixed',
	) ) );

	/*
	 * This theme styles the visual editor to resemble the theme style,
	 * specifically font, colors, icons, and column width.
	 */
	add_editor_style( array( 'css/editor-style.css', 'genericons/genericons.css', twentyfifteen_fonts_url() ) );
}
endif; // twentyfifteen_setup
add_action( 'after_setup_theme', 'twentyfifteen_setup' );

/**
 * Register widget area.
 *
 * @since Twenty Fifteen 1.0
 *
 * @link https://codex.wordpress.org/Function_Reference/register_sidebar
 */
function twentyfifteen_widgets_init() {
	register_sidebar( array(
		'name'          => __( 'Widget Area', 'twentyfifteen' ),
		'id'            => 'sidebar-1',
		'description'   => __( 'Add widgets here to appear in your sidebar.', 'twentyfifteen' ),
		'before_widget' => '<aside id="%1$s" class="widget %2$s">',
		'after_widget'  => '</aside>',
		'before_title'  => '<h2 class="widget-title">',
		'after_title'   => '</h2>',
	) );
}
add_action( 'widgets_init', 'twentyfifteen_widgets_init' );

if ( ! function_exists( 'twentyfifteen_fonts_url' ) ) :
/**
 * Register Google fonts for Twenty Fifteen.
 *
 * @since Twenty Fifteen 1.0
 *
 * @return string Google fonts URL for the theme.
 */
function twentyfifteen_fonts_url() {
	$fonts_url = '';
	$fonts     = array();
	$subsets   = 'latin,latin-ext';

	/*
	 * Translators: If there are characters in your language that are not supported
	 * by Noto Sans, translate this to 'off'. Do not translate into your own language.
	 */
	if ( 'off' !== _x( 'on', 'Noto Sans font: on or off', 'twentyfifteen' ) ) {
		$fonts[] = 'Noto Sans:400italic,700italic,400,700';
	}

	/*
	 * Translators: If there are characters in your language that are not supported
	 * by Noto Serif, translate this to 'off'. Do not translate into your own language.
	 */
	if ( 'off' !== _x( 'on', 'Noto Serif font: on or off', 'twentyfifteen' ) ) {
		$fonts[] = 'Noto Serif:400italic,700italic,400,700';
	}

	/*
	 * Translators: If there are characters in your language that are not supported
	 * by Inconsolata, translate this to 'off'. Do not translate into your own language.
	 */
	if ( 'off' !== _x( 'on', 'Inconsolata font: on or off', 'twentyfifteen' ) ) {
		$fonts[] = 'Inconsolata:400,700';
	}

	/*
	 * Translators: To add an additional character subset specific to your language,
	 * translate this to 'greek', 'cyrillic', 'devanagari' or 'vietnamese'. Do not translate into your own language.
	 */
	$subset = _x( 'no-subset', 'Add new subset (greek, cyrillic, devanagari, vietnamese)', 'twentyfifteen' );

	if ( 'cyrillic' == $subset ) {
		$subsets .= ',cyrillic,cyrillic-ext';
	} elseif ( 'greek' == $subset ) {
		$subsets .= ',greek,greek-ext';
	} elseif ( 'devanagari' == $subset ) {
		$subsets .= ',devanagari';
	} elseif ( 'vietnamese' == $subset ) {
		$subsets .= ',vietnamese';
	}

	if ( $fonts ) {
		$fonts_url = add_query_arg( array(
			'family' => urlencode( implode( '|', $fonts ) ),
			'subset' => urlencode( $subsets ),
		), 'https://fonts.googleapis.com/css' );
	}

	return $fonts_url;
}
endif;

/**
 * JavaScript Detection.
 *
 * Adds a `js` class to the root `<html>` element when JavaScript is detected.
 *
 * @since Twenty Fifteen 1.1
 */
function twentyfifteen_javascript_detection() {
	echo "<script>(function(html){html.className = html.className.replace(/\bno-js\b/,'js')})(document.documentElement);</script>\n";
}
add_action( 'wp_head', 'twentyfifteen_javascript_detection', 0 );

/**
 * Enqueue scripts and styles.
 *
 * @since Twenty Fifteen 1.0
 */
function twentyfifteen_scripts() {
	// Add custom fonts, used in the main stylesheet.
	wp_enqueue_style( 'twentyfifteen-fonts', twentyfifteen_fonts_url(), array(), null );


	// Add Genericons, used in the main stylesheet.
	wp_enqueue_style( 'genericons', get_template_directory_uri() . '/genericons/genericons.css', array(), '3.2' );

	// Load our main stylesheet.
	wp_enqueue_style( 'twentyfifteen-style', get_stylesheet_uri() );

	// Load the Internet Explorer specific stylesheet.
	wp_enqueue_style( 'twentyfifteen-ie', get_template_directory_uri() . '/css/ie.css', array( 'twentyfifteen-style' ), '20141010' );
	wp_style_add_data( 'twentyfifteen-ie', 'conditional', 'lt IE 9' );

	// Load the Internet Explorer 7 specific stylesheet.
	wp_enqueue_style( 'twentyfifteen-ie7', get_template_directory_uri() . '/css/ie7.css', array( 'twentyfifteen-style' ), '20141010' );
	wp_style_add_data( 'twentyfifteen-ie7', 'conditional', 'lt IE 8' );

	wp_enqueue_script( 'twentyfifteen-skip-link-focus-fix', get_template_directory_uri() . '/js/skip-link-focus-fix.js', array(), '20141010', true );

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}

	if ( is_singular() && wp_attachment_is_image() ) {
		wp_enqueue_script( 'twentyfifteen-keyboard-image-navigation', get_template_directory_uri() . '/js/keyboard-image-navigation.js', array( 'jquery' ), '20141010' );
	}

	wp_enqueue_script( 'twentyfifteen-script', get_template_directory_uri() . '/js/functions.js', array( 'jquery' ), '20150330', true );
	wp_localize_script( 'twentyfifteen-script', 'screenReaderText', array(
		'expand'   => '<span class="screen-reader-text">' . __( 'expand child menu', 'twentyfifteen' ) . '</span>',
		'collapse' => '<span class="screen-reader-text">' . __( 'collapse child menu', 'twentyfifteen' ) . '</span>',
	) );
}
add_action( 'wp_enqueue_scripts', 'twentyfifteen_scripts' );

/**
 * Add featured image as background image to post navigation elements.
 *
 * @since Twenty Fifteen 1.0
 *
 * @see wp_add_inline_style()
 */
function twentyfifteen_post_nav_background() {
	if ( ! is_single() ) {
		return;
	}

	$previous = ( is_attachment() ) ? get_post( get_post()->post_parent ) : get_adjacent_post( false, '', true );
	$next     = get_adjacent_post( false, '', false );
	$css      = '';

	if ( is_attachment() && 'attachment' == $previous->post_type ) {
		return;
	}

	if ( $previous &&  has_post_thumbnail( $previous->ID ) ) {
		$prevthumb = wp_get_attachment_image_src( get_post_thumbnail_id( $previous->ID ), 'post-thumbnail' );
		$css .= '
			.post-navigation .nav-previous { background-image: url(' . esc_url( $prevthumb[0] ) . '); }
			.post-navigation .nav-previous .post-title, .post-navigation .nav-previous a:hover .post-title, .post-navigation .nav-previous .meta-nav { color: #fff; }
			.post-navigation .nav-previous a:before { background-color: rgba(0, 0, 0, 0.4); }
		';
	}

	if ( $next && has_post_thumbnail( $next->ID ) ) {
		$nextthumb = wp_get_attachment_image_src( get_post_thumbnail_id( $next->ID ), 'post-thumbnail' );
		$css .= '
			.post-navigation .nav-next { background-image: url(' . esc_url( $nextthumb[0] ) . '); border-top: 0; }
			.post-navigation .nav-next .post-title, .post-navigation .nav-next a:hover .post-title, .post-navigation .nav-next .meta-nav { color: #fff; }
			.post-navigation .nav-next a:before { background-color: rgba(0, 0, 0, 0.4); }
		';
	}

	wp_add_inline_style( 'twentyfifteen-style', $css );
}
add_action( 'wp_enqueue_scripts', 'twentyfifteen_post_nav_background' );

/**
 * Display descriptions in main navigation.
 *
 * @since Twenty Fifteen 1.0
 *
 * @param string  $item_output The menu item output.
 * @param WP_Post $item        Menu item object.
 * @param int     $depth       Depth of the menu.
 * @param array   $args        wp_nav_menu() arguments.
 * @return string Menu item with possible description.
 */
function twentyfifteen_nav_description( $item_output, $item, $depth, $args ) {
	if ( 'primary' == $args->theme_location && $item->description ) {
		$item_output = str_replace( $args->link_after . '</a>', '<div class="menu-item-description">' . $item->description . '</div>' . $args->link_after . '</a>', $item_output );
	}

	return $item_output;
}
add_filter( 'walker_nav_menu_start_el', 'twentyfifteen_nav_description', 10, 4 );

/**
 * Add a `screen-reader-text` class to the search form's submit button.
 *
 * @since Twenty Fifteen 1.0
 *
 * @param string $html Search form HTML.
 * @return string Modified search form HTML.
 */
function twentyfifteen_search_form_modify( $html ) {
	return str_replace( 'class="search-submit"', 'class="search-submit screen-reader-text"', $html );
}
add_filter( 'get_search_form', 'twentyfifteen_search_form_modify' );

/**
 * Implement the Custom Header feature.
 *
 * @since Twenty Fifteen 1.0
 */
require get_template_directory() . '/inc/custom-header.php';

/**
 * Custom template tags for this theme.
 *
 * @since Twenty Fifteen 1.0
 */
require get_template_directory() . '/inc/template-tags.php';

/**
 * Customizer additions.
 *
 * @since Twenty Fifteen 1.0
 */
require get_template_directory() . '/inc/customizer.php';

class WP_Query_Multisite extends WP_Query{
	
	var $args;
	
	function __construct( $args = array() ) {
		$this->args = $args;
		$this->parse_multisite_args();
		$this->add_filters();
		$this->query($args);			  
		$this->remove_filters();
	}
	
	function parse_multisite_args() {
		global $wpdb;
		
		$site_IDs = $wpdb->get_col( "select blog_id from $wpdb->blogs" );
		if ( isset( $this->args['sites']['sites__not_in'] ) )
			foreach($site_IDs as $key => $site_ID )
				if (in_array($site_ID, $this->args['sites']['sites__not_in']) ) unset($site_IDs[$key]);
		
		if ( isset( $this->args['sites']['sites__in'] ) )
			foreach($site_IDs as $key => $site_ID )
				if ( ! in_array($site_ID, $this->args['sites']['sites__in']) ) 
					unset($site_IDs[$key]);
		
		$site_IDs = array_values($site_IDs);
		$this->sites_to_query = $site_IDs;
	}
	function add_filters() {
		
			add_filter('posts_request', array(&$this, 'create_and_unionize_select_statements') );
			add_filter('posts_fields', array(&$this, 'add_site_ID_to_posts_fields') );
			add_action('the_post', array(&$this, 'switch_to_blog_while_in_loop'));
			add_action('loop_end', array(&$this, 'restore_current_blog_after_loop'));
			
	}
	function remove_filters() {
			remove_filter('posts_request', array(&$this, 'create_and_unionize_select_statements') );
			remove_filter('posts_fields', array(&$this, 'add_site_ID_to_posts_fields') );
	}
	function create_and_unionize_select_statements( $sql ) {
		global $wpdb;
		$root_site_db_prefix = $wpdb->prefix;
		
		$page = isset( $this->args['paged'] ) ? $this->args['paged'] : 1;
		$posts_per_page = isset( $this->args['posts_per_page'] ) ? $this->args['posts_per_page'] : 10;
		$s = ( isset( $this->args['s'] ) ) ? $this->args['s'] : false;
		foreach ($this->sites_to_query as $key => $site_ID) :
			switch_to_blog( $site_ID );
			$new_sql_select = str_replace($root_site_db_prefix, $wpdb->prefix, $sql);
			$new_sql_select = preg_replace("/ LIMIT ([0-9]+), ".$posts_per_page."/", "", $new_sql_select);
			$new_sql_select = str_replace("SQL_CALC_FOUND_ROWS ", "", $new_sql_select);
			$new_sql_select = str_replace("# AS site_ID", "'$site_ID' AS site_ID", $new_sql_select);
			$new_sql_select = preg_replace( '/ORDER BY ([A-Za-z0-9_.]+)/', "", $new_sql_select);
			$new_sql_select = str_replace(array("DESC", "ASC"), "", $new_sql_select);
			if( $s ){
				$new_sql_select = str_replace("LIKE '%{$s}%' , wp_posts.post_date", "", $new_sql_select); //main site id
				$new_sql_select = str_replace("LIKE '%{$s}%' , wp_{$site_ID}_posts.post_date", "", $new_sql_select);  // all other sites
			}
			
			$new_sql_selects[] = $new_sql_select;
			restore_current_blog();
		endforeach;
		if ( $posts_per_page > 0 ) {
			$skip = ( $page * $posts_per_page ) - $posts_per_page;
			$limit = "LIMIT $skip, $posts_per_page";
		} else {
            $limit = '';
        }
		$orderby = "tables.post_date DESC";
		$new_sql = "SELECT SQL_CALC_FOUND_ROWS tables.* FROM ( " . implode(" UNION ", $new_sql_selects) . ") tables ORDER BY $orderby " . $limit;
		return $new_sql;
	}
	
	function add_site_ID_to_posts_fields( $sql ) {
		$sql_statements[] = $sql;
		$sql_statements[] = "# AS site_ID";
		return implode(', ', $sql_statements);
	}
	
	function switch_to_blog_while_in_loop( $post ) {
		global $blog_id;
		if($post->site_ID && $blog_id != $post->site_ID )
			switch_to_blog($post->site_ID);
		else
			restore_current_blog();
	}
	function restore_current_blog_after_loop() {
		restore_current_blog();
	}
}


function output_sitemap($format, $name_q){
    $wpb_all_query = new WP_Query(array('post_type'=>'post', 'post_status'=>'publish', 'posts_per_page'=>-1));
    echo '[
      ';
    $idx = 0;
    $map_arr = array();
    while ( $wpb_all_query->have_posts() )  : $wpb_all_query->the_post();
      if (($name_q =='') || ($name_q == get_post( $post )->post_name)){
        switch($format){
          case 'short':
            $map_arr[$idx] = '
            {
               "slug": '.json_encode(get_post( $post )->post_name).',
               "title": '.json_encode(get_the_title()).',
               "date":  '.get_the_modified_date('U').',
               "synopsis": '.json_encode(substr(strip_tags(get_the_content()),0, 266)).'
             }
            ';
            break;
          case 'long':
            $map_arr[$idx] = '
            {
               "slug": '.json_encode(get_post( $post )->post_name).',
               "title": '.json_encode(get_the_title()).',
               "date":  '.get_the_modified_date('U').',
               "synopsis": '.json_encode(get_the_content()).'
             }
            ';
            break;
          case 'titles':
            $map_arr[$idx] = '
            {
               "slug": '.json_encode(get_post( $post )->post_name).',
               "title": '.json_encode(get_the_title()).',
               "date":  '.get_the_modified_date('U').'
             }
            ';
            break;
          }
        $idx = $idx + 1;
        // End the loop.
      }
    endwhile;
    echo join(',',$map_arr);
    echo '
    ]';
    die('');
}


function do_quick_update($wpdb){
  if ( ! current_user_can( 'edit_posts' ) || ! current_user_can( get_post_type_object( 'post' )->cap->create_posts ) )
    require_once( ABSPATH . '/wp-admin/admin.php' );
  require_once( ABSPATH . '/wp-admin/includes/image.php' );
  $title = $_POST['title'];
  $title = preg_replace("/:: */", ":: ", $title);
  $content = $_POST['content'];
  /* Replace simple tags with Markdown */
  $content = preg_replace("/\t/i", " ", $content); // replace tab
  $content = preg_replace("/<[\/]*em>/i", "_", $content);
  $content = preg_replace("/<[\/]*strong>/i", "_", $content);
  $content = preg_replace("/<[\/]*b>/i", "_", $content);
  $content = preg_replace("/<[\/]*i>/i", "_", $content);
  $content = preg_replace("/<[\/]*i>/i", "_", $content);
  $content = preg_replace("/[ ]*<li>/i", "* ", $content);
  $content = preg_replace("/<\/li>/i", "", $content);
  $content = preg_replace("/<[u|o]l>/i", "\n", $content);
  $content = preg_replace("/<\/[u|o]l>/i", "\n ", $content);
  $content = strip_invisible_tags( $content); 
  $slug = sanitize_title_with_dashes( trim($title), $unused, $context = 'save' );
  $postid = $wpdb->get_var("SELECT ID FROM $wpdb->posts WHERE post_name = '$slug'");
  if ($postid) {
    $old_post = get_post($postid);
    $old_content = $old_post->post_content;
  } else {
    $old_content = '';
  }
  
  
  // Add new content to top: used in "Add to Path"
  
  if ($_POST['mergetype'] == "addtop"){

    $content = $content."\n".$old_content;
  }
  
  // ACTIONS: Read first line, look for actions
  
  $content_lines = explode("\n",$content);
  
  if (rtrim($content_lines[0]) == "ACTION:: DELETE"){
   wp_delete_post( $postid, 1 );
   $_SESSION['s'] = '';
   $_SESSION['t'] = '';
   $_SESSION['c'] = '';
   header("refresh: 0; url=".get_site_url()."?s=".$_SESSION['s']);
   die("");
  }
  
  if (rtrim($content_lines[0]) == "ACTION:: DELETE HISTORY"){
   delete_post_meta( $postid, "history" );
   $_SESSION['s'] = '';
   $_SESSION['t'] = '';
   $_SESSION['c'] = '';
   header("refresh: 0; url=".get_site_url()."?s=".$_SESSION['s']);
   die("");
  }
  
  // Process shortcuts
  
  $content = process_urls($content, $wpdb);
  $content = bareImgToMd($content);
  $content = str_replace("[=](http","[source](http",$content);
  $content = str_replace("[-](http","[link](http",$content);



  /* If slug exists update page and redirect */

  if ($postid !='') {

    $my_post = array(
        'ID'           => $postid,
        'post_content'   => $content
    );
    wp_update_post( $my_post );
  } elseif ($title != '') {

   $my_post = array(
    'slug'           => $slug,
    'post_content'   => $content,
    'post_title'     => $title,
    'post_status'    => 'publish'
    );
   wp_insert_post( $my_post, true );
  } else {
   $my_post = array(
    'slug'           => $slug,
    'post_content'   => $content,
    'post_title'     => "Untitled: ".uniqid(),
    'post_status'    => 'publish'
    );
   wp_insert_post( $my_post, true );
  }
  
  $_SESSION['s'] = '';
  
  if ($_POST['after']=='close') : ?>
    <script>window.close();</script>
  <?php else:
   header("refresh: 0; url=".get_site_url()."?s=".$_SESSION['s']);
   // destroy the session
   $_SESSION['s'] = '';
   $_SESSION['t'] = '';
   $_SESSION['c'] = '';
   die('');
  endif;
  }
  
  
  function createSynopsis($c){
      $res = str_replace("\r\n","\n",$c);
      $res = str_replace("\n\n","\n",$res);
      $res = str_replace("|","",$res);
      $res = str_replace("--","",$res);
      $res = str_replace("###","<br>",$res);
      $res = preg_replace("/!?\[[^\]]*]\([^\)]*\)/","",$res);
      $res = trim(wp_strip_all_tags($res));
      $res = substr($res,0,370).'...';
      return $res;
  }
  
  
  function formatSpecialLinks($c){
     $c = str_replace('[cite]','[<sup>&deg;</sup>]',$c);
     $c = str_replace('[link]','[<small>(Link)</small>]',$c);
     $c = str_replace('[site]','[<small>(Site)</small>]',$c);
     $c = str_replace('[article]','[<small>(Article)</small>]',$c);
     $c = str_replace('[download]','[<small>(Download)</small>]',$c);
     $c = str_replace('[source]','[<small>(Source)</small>]',$c);
     $c = str_replace("\n&gt;","\n>",$c);
     return $c;
  }



function strip_invisible_tags( $text )
{
    $text = preg_replace(
        array(
          // Remove invisible content
            '@<head[^>]*?>.*?</head>@siu',
            '@<style[^>]*?>.*?</style>@siu',
            '@<script[^>]*?.*?</script>@siu',
            '@<object[^>]*?.*?</object>@siu',
            '@<embed[^>]*?.*?</embed>@siu',
            '@<applet[^>]*?.*?</applet>@siu',
            '@<noframes[^>]*?.*?</noframes>@siu',
            '@<noscript[^>]*?.*?</noscript>@siu',
            '@<noembed[^>]*?.*?</noembed>@siu',
        ),
        array(
            ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ',
        ),
        $text );
    return $text;
}
  
function reset_permalinks() {
    global $wp_rewrite;
    $wp_rewrite->set_permalink_structure( '/%postname%/' );
}
add_action( 'after_setup_theme', 'reset_permalinks' );
flush_rewrite_rules();


// delay feed update after posting

function publish_later_on_feed($where) {
	global $wpdb;
	if (is_feed()) {
		// timestamp in WP-format
		$now = gmdate('Y-m-d H:i:s');

		// value for wait; + device
		$wait = '5'; // integer

		// http://dev.mysql.com/doc/refman/5.0/en/date-and-time-functions.html#function_timestampdiff
		$device = 'DAY'; // MINUTE, HOUR, DAY, WEEK, MONTH, YEAR

		// add SQL-sytax to default $where
		$where .= " AND TIMESTAMPDIFF($device, $wpdb->posts.post_date_gmt, '$now') > $wait ";
	}
	return $where;
}

add_filter('posts_where', 'publish_later_on_feed');

function write_initial_pages(){
  
  /*** Settings Page ***/
   global $wpdb;
   $sql =  $wpdb->prepare("SELECT post_content FROM $wpdb->posts WHERE post_name = %s", "settings-publishing");
   $publish_settings = $wpdb->get_var($sql);
    if (strlen($publish_settings) == 0):
      $publish_settings = "These settings are used by Wikity to determine privacy (openness) and publishing schedule. \n\n";
      $publish_settings .= "Please note that putting \"Open\" to \"No\" is an experimental feature, providing \"good enough\" privacy but not great privacy.\n\n";
      $publish_settings.= "----Settings----\n\n";
      $publish_settings.= "OPEN: Yes\n";
      $publish_settings.= "RSS DELAY: 5 days";
       $my_post = array(
        'slug'           => "settings-publishing",
        'post_content'   => $publish_settings,
        'post_title'     => "Settings:: Publishing",
        'post_status'    => 'publish'
        );
      wp_insert_post( $my_post, true );
    endif;
    
    
    
   $sql =  $wpdb->prepare("SELECT post_content FROM $wpdb->posts WHERE post_name = %s", "help-getting-started-day-one");
   $publish_instructions = $wpdb->get_var($sql);
    if (strlen($publish_instructions) == 0):
      $publish_instructions = "Most people find that using Wikity to bookmark is a good place to start. The following video shows how you can bookmark with Wikity. \n\n";
      $publish_instructions .= "https://www.youtube.com/watch?v=66IGbiATzsY \n\n";
      $publish_instructions.= "Note that in the video the bookmark says 'Bkmrk' but in recent versions says 'Wik-it'. The editor has also been upgraded\n\n";
       $my_post = array(
        'slug'           => "help-getting-started-day-one",
        'post_content'   => $publish_instructions,
        'post_title'     => "Help:: Getting Started / Day One",
        'post_status'    => 'publish'
        );
      wp_insert_post( $my_post, true );
    endif;
    
    
}

add_filter('after_setup_theme', 'write_initial_pages'); 

function check_feed_permissions() {
  global $wpdb;
  $sql =  $wpdb->prepare("SELECT post_content FROM $wpdb->posts WHERE post_name = %s", "settings-publishing");
  $publish_settings = $wpdb->get_var($sql);
  if (strpos($publish_settings,"OPEN: No")):
    wp_die( __('No feed available, please visit our <a href="'. get_bloginfo('url') .'">homepage</a>!') );
  endif;
}

add_action('do_feed', 'check_feed_permissions', 1);
add_action('do_feed_rdf', 'check_feed_permissions', 1);
add_action('do_feed_rss', 'check_feed_permissions', 1);
add_action('do_feed_rss2', 'check_feed_permissions', 1);
add_action('do_feed_atom', 'check_feed_permissions', 1);
add_action('do_feed_rss2_comments', 'check_feed_permissions', 1);
add_action('do_feed_atom_comments', 'check_feed_permissions', 1);