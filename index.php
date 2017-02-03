<?php

/**
 * The main template file
 *
 * This is the most generic template file in a WordPress theme
 * and one of the two required files for a theme (the other being style.css).
 * It is used to display a page when nothing more specific matches a query.
 * e.g., it puts together the home page when no home.php file exists.
 *
 * Learn more: {@link https://codex.wordpress.org/Template_Hierarchy}
 *
 * @package WordPress
 * @subpackage Twenty_Fifteen
 * @since Twenty Fifteen 1.0

 **/

/**

Index handles the following fedwiki calls:

* Quick updates from the catalog view
* Popups from the Wik-it plugin
* Forks from other sites to this one
* Providing JSON to other sites that want to fork (API)
* Providing "sitemaps" that specify the contents of the server (API)

**/

if ( ! current_user_can( 'edit_posts' )):
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
    if (strpos($publish_settings,"OPEN: No")):
      require_once( ABSPATH . '/wp-admin/admin.php' );
    endif;

endif;

/**** QUICK UPDATES ****/

if (strlen($_POST['content']) > 0){
  do_quick_update($wpdb); /* updates page text from the catalog view */
}


/* INDEX API: CONTENT

Plain text for single card, e.g.
http://rainystreets.wikity.cc/?action=textcontent&slug=netflix-is-shrinking
Not JSON, no history returned, no title, pure untransformed source.

*/

if (strlen($_GET['action']) > 0 && $_GET['action'] == 'plain'){
  $content= $_GET['slug'];
  $sql =  $wpdb->prepare("SELECT post_content FROM $wpdb->posts WHERE post_name = %s", array($content));
  $post_content = $wpdb->get_var($sql);
  echo $post_content;
  die("");
}


if (strlen($_GET['action']) > 0 && $_GET['action'] == 'json'){
  $content= $_GET['slug'];
  $sql =  $wpdb->prepare("SELECT post_content FROM $wpdb->posts WHERE post_name = %s", array($content));
  $post_content = $wpdb->get_var($sql);
  $json_marker = "----json----";
  $pos = strpos($post_content, $json_marker);
  output_xml_headers();
  echo strpbrk(substr($post_content, $pos + strlen($json_marker)),"{[");
  die("");
}

/* INDEX API: SITEMAP

Returns inventory of site cards in JSON format.
Levels of detail:

short: basic card info, plus first 266 characters.
long: basic card info and full text
titles: titles, slugs, create date

*/

if (strlen($_GET['action']) && $_GET['action'] == 'siteslist'){
    $blog_list = get_blog_list( 0, 'all' );
    foreach ($blog_list AS $blog) {
      echo $blog['domain'].$blog['path'].'
';
    }
    die("");
}


if (strlen($_GET['action']) > 0 && $_GET['action'] == 'sitemap'){
    output_sitemap($_GET['detail'],$_GET['name_q']);
    die("");
}



/* INDEX API: FEDWIKI (GIVE CARD)

Name is that way for historical reasons, sorry.
Gets JSON in fedwiki format for given page.
Returns full JSON record for card: Title, "Story", "Journal", etc.
Adds a fork record to history
Form: http://rainystreets.wikity.cc/?fedwiki=netflix-is-shrinking

*/

if (strlen($_GET['fedwiki']) > 0){

  // headers_list

  output_xml_headers();


  $fedwiki = $_GET['fedwiki'];
  $sql =  $wpdb->prepare("SELECT post_title FROM $wpdb->posts WHERE post_name = %s", array($fedwiki));
  $post_title = $wpdb->get_var($sql);
  $post_title = str_replace('"', '\"', $post_title);
  echo '{"title": "'.$post_title.'",';

  $sql =  $wpdb->prepare("SELECT post_content FROM $wpdb->posts WHERE post_name = %s", array($fedwiki));
  $post_content = $wpdb->get_var($sql);
  $post_len = strlen($post_content);
  $post_content = str_replace('"', '\"', $post_content);
  $post_content = str_replace("\r", '\r', $post_content);
  $post_content = str_replace("\n", '\n', $post_content);

  // determine post type
  // types: Markdown, Group, Playlist
  $post_item_type = 'markdown';
  if (substr($post_title,0,7)=="Group::") {
    $post_item_type = 'roster';
  }


 echo '"story": [
    {
      "text": "'.$post_content.'",
      "id": "'.uniqid().'",
      "type": "'.$post_item_type.'"
    }], "journal": '."\r\n\r\n\r\n\r\n";


  $sql =  $wpdb->prepare("SELECT * FROM $wpdb->posts WHERE post_name = %s", array($fedwiki));
  $post_var = $wpdb->get_row($sql, ARRAY_A);
  $my_id = $post_var['ID'];
  $my_createdate =  $post_var['post_date_gmt'];
  $post_author = $post_var['post_author'];
  $post_title = str_replace('"','\"', $post_var['post_title']);
  $post_content = $post_var['post_content'];

  $prior_history = prior_history($my_id);

   if (strlen($prior_history)){
      $history_str = '['.$prior_history;
   } else {
      $history_str = '[{
      "type": "create",
      "id": "'.uniqid().'",
      "site": "'.get_site_url().'",
      "author": "'.md5(strtolower(trim(get_the_author_meta('user_email',$post_author)))).'",
      "date":'.(strtotime($my_createdate) * 1000).',
      "item": {
        "title": "'.$post_title.'"
      }
    }';
   }

  $history_str .=',
   {
      "type": "fork",
      "site": "'.get_site_url().'",
      "date": '.(intval(time()) * 1000).',
      "author": "'.md5(strtolower(trim(get_the_author_meta('user_email',$post_author)))).'",
      "name": "'.get_the_author_meta( 'display_name', $post_author).'",
      "slug": "'. $fedwiki.'",
      "chars": '. strlen($post_content).'
    }]}';

 echo $history_str;
  die('');
}



// GET CARD

if (strlen($_GET['slug']) > 0){
  $slug = $_GET['slug'];
  define('IFRAME_REQUEST' , true);
  /** WordPress Administration Bootstrap */
  getCard($_GET['site'], $_GET['slug'], $wpdb);

  /* redirect to updated page on your site */

  header("Location: ".get_site_url()."/".$slug);
}

/***** Original ******/

global $query_string;
query_posts($query_string . '&orderby=modified');
get_header();

?>


<div id="checkedUrlsDiv" style="margin-top: 2px; border: 2px solid black; box-shadow: 10px 10px 5px #888888; background-color: white; opacity:.95; position:fixed; z-index: 300; display:none; padding:10px">
  <form name="forkform" id="forkform" method="post" action="">
  <span style="font-size:8pt; font-weight:bold">CardBox Name: <input style="font-size:8pt; font-weight:bold" id="cardboxtitle" name="title" value="Recently Added"></span>
  <br><textarea style="font-size:8pt; font-weight:bold; display:none" name="content" id="checkedUrls"></textarea>
  <span style="font-size:8pt"><span id="numcards">0</span> cards selected.
  <br><span style="font-size:8pt">Send to http://</span><input name="forkto" id="forkto" type="text" onkeypress="$('#forkform').attr('action', 'http://' + $('#forkto').val());" style="font-size:8pt; font-weight:bold; width:200px" value="<?php 	echo str_replace("http://", "", get_site_url())?>">
  <input type="hidden" name="mergetype" value="addtop">
  <input type="submit" style="font-size:10px" value="Add to CardBox" onclick="$('#forkform').attr('action', 'http://' + $('#forkto').val()); $('#cardboxtitle').val('CardBox:: ' +  $('#cardboxtitle').val()); return true;">
  <input type="button" style="font-size:10px" value="Cancel" onclick="$('#checkedUrlsDiv').hide();">
  </form>

</div>
	<div id="primary" class="content-area">
	  
		<main id="main" class="site-main" role="main">

<?php if ( is_home() or is_search() or is_category() ) : ?>


    <?php if ( current_user_can( 'edit_posts' ) || current_user_can( get_post_type_object( 'post' )->cap->create_posts ) ) : ?>
    

    <script src="//cdn.jsdelivr.net/simplemde/latest/simplemde.min.js"></script>

    
    <?php
    if (strlen($_GET['title'])) :
      $form_t = $_GET['title'];
    endif;
    
    if (strlen($_GET['selection'])) :
      $form_c = '> '.$_GET['selection'] . ' [source]('. $_GET['sourceurl'].')';
      $form_c = " \n\n".str_replace("\n","\n> ", $form_c);
      $form_c = str_replace('\"','"', $form_c);
      $form_c = str_replace("\\'","'", $form_c);
      $after = 'close';
    else:
      $after = '';
    endif;
    ?>
        <article id="post-edit" style="margin-top: 5px; width:655px" class="post-1544 post type-post status-publish format-standard hentry category-uncategorized">

      
      	<div class="entry-content">
      		  <form name="createform" id="createform" method="post">
      		    <input name="title" id="formtitle" style="width:85%; padding-left:5px; margin-bottom:5px" value="<?php echo $form_t; ?>" placeholder="Name of Concept or Data"><input type="submit" style="font-size:10px; float:right" value="Post"><br>
      		    <textarea name="content" id="formcontent" style="display:none; height:200px; font-size:small"><?php echo $form_c; ?></textarea>
      		    <script>
                var simplemde = new SimpleMDE({ element: $("#formcontent")[0] });
                simplemde.options.insertTexts.image= ["![](http://image_url", ")"];
              </script>

      		    <input type="hidden" name="action" value="update">
      		    <input name="after" type="hidden" value="<?php echo $after; ?>">

      		  </form>



      		  <!-- new -->            
<?php            
// jQuery
wp_enqueue_script('jquery');
// This will enqueue the Media Uploader script
wp_enqueue_media();
?>

<input style="font-size: 9px" type="hidden" name="image_url" id="image_url" class="regular-text">

<script type="text/javascript">
jQuery(document).ready(function($){
    $('.fa-picture-o').click(function(e) {
        e.preventDefault();
        var image = wp.media({ 
            title: 'Upload Image',
            // mutiple: true if you want to upload multiple files at once
            multiple: false
        }).open()
        .on('select', function(e){
            // This will return the selected image from the Media Uploader, the result is an object
            var uploaded_image = image.state().get('selection').first();
            // We convert uploaded_image to a JSON object to make accessing it easier
            // Output to the console uploaded_image
            console.log(uploaded_image);
            var image_url = uploaded_image.toJSON().url;
            // Let's assign the url value to the input field
            $('#image_url').val('![](' + image_url + ' "")');
            simplemde.value(simplemde.value().replace('(http://image_url)','(' + image_url + ' "")'));
        });
    });
});
</script>
<!-- end new -->
      </div></article>
     
    <?php elseif (strlen($_GET['sourceurl'])) : ?>
       You are not logged in. Log in, and Wik-it! again. <?php wp_loginout($_SERVER['REQUEST_URI']); ?> 
    <?php endif; ?>

	<?php endif; ?>


<?php if (strlen($_GET['sourceurl']) == 0) :  ?>
  
  <article id="post-stub" class="post-1544 post type-post status-publish format-standard hentry category-uncategorized" style="margin-top:1.2%;">
	<header class="entry-header">
		<h1 class="blogtitle"><a href="<?php echo esc_url( home_url( '/?clear=1' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a></h1></header>
		<!-- .entry-header -->
		<div class="entry-content">
		<p style="line-height: 100%">
	<?php
	  $description = get_bloginfo( 'description', 'display' );
	  if ( $description || is_customize_preview() ) : ?>
			<br><small><em><?php echo $description; ?></em></small><br>
	<?php endif; ?>
	<br><small>You can read your cards by clicking the title on the card. If you are logged in, edit them by clicking the small dot after the title (full editor) or clicking on the text of the card (quick editor).</small><br><br>
	<small>Additional options: <a href="/home">Home</a>,
	<a href="javascript:q=location.href;if(document.getSelection){d=document.getSelection();}else{d='';};p=document.title;void(open('<?php echo get_site_url() ?>/?sourceurl='+encodeURIComponent(q)+'&selection='+encodeURIComponent(d)+'&title=','Wikity','toolbar=no,width=700,height=500'));">Wik-it!</a>,
						<?php wp_loginout($_SERVER['REQUEST_URI']); ?>, <a href="?s=Settings::">Settings</a>, <a href="?s=Help::">How-to</a>, <a href="https://github.com/michaelarthurcaulfield/wikity-zero/archive/master.zip">Get Wikity</a><br>
						<form method="post" action="./">Search:
            <input name="s" id="s" onclick="$('#c').val($('#formcontent').val()); $('#t').val($('#formtitle').val());" value="<?php echo $form_s; ?>">
            <input name="c" id="c" type="hidden" value="">
            <input name="t" id="t" type="hidden" value="">
            <textarea name="pinnedcards" id="pinnedcards" style="display:none" value=""></textarea>
            </form></small></p>

            
            </div></article>
    <?php
   endif;
    ?>

		<?php if ( have_posts() ) : ?>

			<?php if ( is_home() && ! is_front_page() ) : ?>
				<header>
					<h1 class="page-title screen-reader-text"><?php single_post_title(); ?></h1>
				</header>
			<?php endif; ?>
			
			<?php if ((is_home() or is_search() or is_category())) : ?>
  <style>
  /* Styles inline like this suck, but all hell breaks loose when removed. If you can fix, please do */

    .hentry {padding-top: 1%;
      margin: 5px; width:320px; height:400px;
      margin-top: 1.2%;
      display:inline-block;
      vertical-align:top;
      font-family: Georgia, "Droid Serif", "Times New Roman";
      font-size: 18px;
      line-height: 22px;

    }
    .entry-content, .entry-header {padding-left: 3%; padding-right:3%; width:95%;}
    .entry-content {padding-bottom: 3%}
    .site-main {padding-top:1.6%;}
    .site-content {margin-left: 3.6%; width:100%;}
    #sidebar {display: none}
    body:before {width:0%;}
    h1.blogtitle {font-size:22px};

  </style>
  <?php  if (strlen($_GET['sourceurl']) != 0) : ?>
      <style>
        FOOTER {display:none}
        .nav-links {display:none}

      </style>
  <?php endif; ?>
<?php endif; ?>
    <?php
    
    if (strlen($_GET['sourceurl']) == 0) :
			// Start the loop.
			while ( have_posts() ) : the_post();

				/*
				 * Include the Post-Format-specific template for the content.
				 * If you want to override this in a child theme, then include a file
				 * called content-___.php (where ___ is the Post Format name) and that will be used instead.
				 */
				
				get_template_part( 'content', get_post_format() );

			// End the loop.
			endwhile;
			endif;
			?>
			
			<?php

			// Previous/next page navigation.
			the_posts_pagination( array(
				'prev_text'          => __( 'Previous page', 'twentyfifteen' ),
				'next_text'          => __( 'Next page', 'twentyfifteen' ),
				'before_page_number' => '<span class="meta-nav screen-reader-text">' . __( 'Page', 'twentyfifteen' ) . ' </span>',
			) );

		// If no content, include the "No posts found" template.
		else :
			get_template_part( 'content', 'none' );

		endif;
		?>

		</main><!-- .site-main -->
	</div><!-- .content-area -->

<?php get_footer(); ?>