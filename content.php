<?php
/**
 * The default template for displaying content
 *
 * Used for both single and index/archive/search.
 *
 * @package WordPress
 * @subpackage Twenty_Fifteen
 * @since Twenty Fifteen 1.0
 */
 ?>
 
<?php
 require_once('fed.php');
 if (isset($parsedown) == FALSE){
   $parsedown = new Parsedown_WP_Parser();
 }
 $parsedown->setUrlsLinked(false);
 $content = get_the_content();
 $synopsis = createSynopsis($content); //  Clean up for "catalog" display 
?>

<span></span> <!-- WARNING: removing this does weird things to layout. Perhaps a buried adjacency rule? --> 

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<?php
		// Post thumbnail.
		twentyfifteen_post_thumbnail();
	?>

	<header class="entry-header">
		<?php

	    $edit_link = get_edit_post_link();
	    $edit_str = " <a href=\"$edit_link\" style=\"font-size:25%\">[...]</a>";

			if ( is_single() ) :
				the_title( '<h1 class="entry-title">', $edit_str.'</h1>' );
			elseif (is_home() or is_search() or is_category()) :
			  ?>
			  <table class="title-layout"><tr><td  class="title-layout" width="90%">
			  <?php
				the_title( sprintf( '<h4 style="float:left"><a href="%s" rel="bookmark">', esc_url( get_permalink() ) ), '</a>'.$edit_str.'</h4>' );
				?></td><td  class="title-layout"><input type="checkbox" style="float:right" name="checkedCards" value="<?php echo get_permalink()?>" id="cardcheck<?php echo get_the_ID(); ?>" onclick="updateUrlList (this.value, this.checked)" name="cardurl" />
          </td></tr></table>
								<?php
			else :
				the_title( sprintf( '<h2 class="entry-title"><a href="%s" rel="bookmark">', esc_url( get_permalink() ) ), '</a>'.$edit_str.'</h2>' );
			endif;
		?>
	</header><!-- .entry-header -->

	<div class="entry-content" style="height:100%">
	  
<?php
if (is_home() or is_search() or is_category()) :
?>
  
<div id="synopsis<?php echo get_the_ID(); ?>" style="line-height: 100%; " >
<p><small onclick="openEditBox('<?php echo get_the_ID(); ?>', 0)"><?php echo $synopsis; ?></small>
</p>
<div style=" position: absolute; bottom: 10px;">

<input  type="submit" style="font-size:10px" onclick="openEditBox('<?php echo get_the_ID(); ?>', 1)" value="Quick Edit">&nbsp;
<input  type="submit" style="font-size:10px" onclick="sendToBigEditor('<?php echo get_the_ID(); ?>', 1)" value="Editor"></div>

<br></div>

  	<div style="display:none;  height:90%" id="entryedit<?php echo get_the_ID(); ?>" >
	    <form method="post" style="height:90%">
		    <input name="title" type="hidden" id="formtitle<?php echo get_the_ID();?>" value="<?php the_title(); ?>">
		    <textarea name="content" id="formcontent<?php echo get_the_ID();?>" style="height:85%; font-size:small"><?php echo get_the_content(); ?></textarea>
		    <input type="hidden" name="action" value="update">
        <input name="s" id="s" type="hidden" value="<?php echo $_POST['s']; ?>">
		    <input type="submit" style="font-size:10px" value="Update"> &nbsp;
		    <input type="submit" onclick="synopsis<?php echo get_the_ID(); ?>.style.display=''; entryedit<?php echo get_the_ID(); ?>.style.display='none';  return false;" style="font-size:10px" value="Cancel"> &nbsp; &nbsp; 
		  </form>
	</div>
	

  <?php

else:

/**** Format non-markdown elements first ****/

$marker = "----json----";
$pos = stripos($content, $marker); // check for json block
if ($pos):
  $before_marker = substr($content, 0, $pos);
  $after_marker = substr($content, $pos+strlen($marker));
  $json_url = site_url()."/?slug=".$post->post_name."&action=json";
  $before_marker = $before_marker." [JSON Link](".$json_url.")";
  $content = $before_marker."\n````\n".trim($after_marker)."\n````\n";
endif;

$marker = "----settings----";
$pos = stripos($content, $marker); // check for json block
if ($pos):
  $before_marker = substr($content, 0, $pos);
  $after_marker = substr($content, $pos+strlen($marker));
  $content = $before_marker."SETTINGS:\n````\n".trim($after_marker)."\n````\n";
endif;
  
 $content = formatSpecialLinks($content); // things like [cite], [source] etc.
 $content = $parsedown->text($content);
 $content = preg_replace("/\[#([^\]]*)]/", "<a name=\"$1\" class=\"anchor\"></a>", $content);
 $content = preg_replace("/(<a href=\"#[^\"]*\">)([0-9]*)<\/a>/i", "<sup>$1[$2]</a></sup>", $content);
 $content = preg_replace("/ *<p[^>]*>(http.*)<\/p>/", "$1", $content);
 
 // Tags, etc.
 
 $content = str_replace("{{",'<aside>', $content);
 $content = str_replace("}}",'</aside>', $content);
 
 $content = apply_filters( 'the_content', $content ); // filters applies after special links

 // Wiki links and ext links

 $content = preg_replace_callback('/\[\[(.*?)\]\]/', 'wiki_links', $content);
 $content = preg_replace_callback('/\(\((.*?)\)\)/', 'cite_links', $content);
 


 echo $content;


// History chiclets

$history_arr = get_post_meta(get_the_ID(),'history');

echo "<p>";

$slug = sanitize_title_with_dashes(get_the_title( get_the_ID() ));

if (! empty($history_arr)){
$sites_mentioned = [];
foreach ($history_arr as $evt){
    $evt_arr = json_decode($evt);
    if (array_search($evt_arr->site, $sites_mentioned)==false){
      array_push($sites_mentioned, $evt_arr->site);
      switch ($evt_arr->type) {
        case 'create':
          $title_hover = "Created on ".$evt_arr->site;
        break;
        case 'fork':
          $title_hover = "Forked from ".$evt_arr->site;
        break;
      }
     /* Hack to preserve backward compatibility with earlier journal histories, which didn't include prefix */
       $source_site = $evt_arr->site;
      if (substr($source_site,0,4) != 'http'){
      	$source_site = 'http://'.$source_site;
      }
      echo '<div style="float: left"><a title="'.$title_hover.'" href="'.$source_site.'/'.$slug.'"><img width="30" style="padding-right: 5px" src="http://www.gravatar.com/avatar/'.$evt_arr->author.'&s=30"></a></div>';
    }
  }
  $sites_mentioned_str = join(',',$sites_mentioned);
  echo "<script>var search_context='$sites_mentioned_str'</script>";
}
echo "</p>";


// Page links
			wp_link_pages( array(
				'before'      => '<div class="page-links"><span class="page-links-title">' . __( 'Pages:', 'twentyfifteen' ) . '</span>',
				'after'       => '</div>',
				'link_before' => '<span>',
				'link_after'  => '</span>',
				'pagelink'    => '<span class="screen-reader-text">' . __( 'Page', 'twentyfifteen' ) . ' </span>%',
				'separator'   => '<span class="screen-reader-text">, </span>',
			) );
		?>
	</div><!-- .entry-content -->

	<?php
		// Author bio.
		if ( is_single() && get_the_author_meta( 'description' ) ) :
			get_template_part( 'author-bio' );
		endif;
	?>

	<?php

	if ($_SERVER['SERVER_NAME'] == "wikity.cc"){
	  $display_copy = 'none';
	} else {
	  $display_copy = '';
	}

  if (strlen($_GET['path']) > 0) {
            $path_name = $_GET['path'];
            $path_post = get_page_by_title('Path:: '.$path_name, OBJECT, 'post');
            preg_match_all('/\[\[(.*?)\]\]/',$path_post->post_content, $link_array);
            foreach ($link_array[1] as $key => $value) {
              if (sanitize_title_with_dashes($value) == sanitize_title_with_dashes(get_the_title())) {
                if ($key > 0) {
                  $previous_title = str_replace('*','', $link_array[1][$key-1]);
                } else {
                  $previous_title = "";
                }
                $current_title =$value;
                if ($key < count($link_array[1])) {
                  $next_title = str_replace('*','', $link_array[1][$key+1]);
                } else {
                  $next_title = "";
                }
              }
            }
        }


  ?>





  <nav class="navigation post-navigation" role="navigation">
      <h2 class="screen-reader-text">Post navigation</h2>
      <div class="nav-links">
        <?php if (strlen($next_title)) :?>
        <div class="nav-next"><a href="<?php echo get_site_url().'/'.sanitize_title_with_dashes($next_title)?>?path=<?php echo $_GET['path']?>&t=<?php echo $_GET['t']?>" rel="next"><span class="meta-nav" aria-hidden="true">NEXT</span> <span class="screen-reader-text">Next post:</span> <span class="post-title"><? echo $next_title ?></span></a></div>
      <?php endif; ?>
        <?php if (strlen($previous_title)) : ?>
          <div class="nav-previous"><a href="<?php echo get_site_url().'/'.sanitize_title_with_dashes($previous_title)?>?path=<?php echo $_GET['path']?>&t=<?php echo $_GET['t']?>" rel="prev"><span class="meta-nav" aria-hidden="true">PREVIOUS</span> <span class="screen-reader-text">Previous post:</span> <span class="post-title"><? echo $previous_title ?></span></a></div>
        <?php endif;?>

      </div>
  </nav>




	<footer class="entry-footer">
	        <div style="font-size:14px; display:	<?php echo $display_copy; ?>">
		Wikity users can copy this article to their own site for editing, annotation, or safekeeping. If you like this article, please help us out by copying and hosting it.<br><br>

		<form id="copythis<?php echo get_the_ID();?>" action="javascript:alert('You must enter a destination site')" autocomplete="on">
		Destination site (your site) <input id="destsite<?php echo get_the_ID();?>" name="destsite" style="font-size:14px" value="name.site.com" onBlur="document.getElementById('copythis<?php echo get_the_ID();?>').action = 'http://' + document.getElementById('destsite<?php echo get_the_ID();?>').value +'/';"><br>

		<!-- Annotation: -->
		<input type=hidden style="font-size:14px" name="annotation"><!-- used to be annotation box -->
		<input type="hidden" name="site" value="<?php echo str_replace('http://','',get_site_url()); ?>">
		<input type="hidden" name="slug" value="<?php global $post; echo $post->post_name; ?>">
		<input type="hidden" name="t" value="<?php echo get_the_title(); ?>">
		<!--<input type="checkbox" name="clear" value="1">-->
		<div align="right"><button onclick="document.getElementById('copythis').submit();">Copy</button></div>
		</form>
		</div>
		<?php twentyfifteen_entry_meta(); ?>
		<?php edit_post_link( __( 'Edit', 'twentyfifteen' ), '<span class="edit-link">', '</span>' ); ?><br>

	</footer><!-- .entry-footer -->
xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
<?php endif ?>

</article><!-- #post-## -->