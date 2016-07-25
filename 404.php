<?php
/**
 * The template for displaying 404 pages (not found)
 *
 * @package WordPress
 * @subpackage Twenty_Fifteen
 * @since Twenty Fifteen 1.0
 */


get_header(); ?>

	<div id="primary" class="content-area">
		<main id="main" class="site-main" role="main">

			<section class="error-404 not-found">
				<header class="page-header">
					<h1 class="page-title"><?php _e( 'Create or Add Page', 'twentyfifteen' ); ?></h1>
				</header><!-- .page-header -->

				<div class="page-content">
					<?php _e( 'This page is not yet on this site. If you are this site\'s curator, you should copy it from somewhere else, or write a new one!', 'twentyfifteen' ); ?>
<?php

$sites = $_GET['sites'];
$title = $_GET['t'];
$query_title =  urlencode($_GET['t']);
$slug = sanitize_title($query_title);
$found_one = 0;

?>
					
<br><h4>Make a new page:</h4>
<form id="newpage" name="newpage" action="/">
<input name="title" value="<?php echo $title; ?>">
<button form_id="newpage">Create New Page</button>
</form>
					
					
					
<br><h4>Copy page from other site:</h4>
					

<?php

echo "<em>Pages found on Wikity.cc named $title:</em><br><br>";

$string = file_get_contents("http://wikity.cc/?findbyname=$query_title");
$json_a = json_decode($string, true);

if (is_array($json_a)){
foreach ($json_a as $json_itm){ 
?>
  <img width="30" style="padding-right: 5px" src="http://www.gravatar.com/avatar/<?php echo $json_itm['author'] ?>&s=30">
    <a href="<? echo $json_itm['permalink']; ?>"><?php echo explode('/',$json_itm['permalink'])[2] ?></a><br>
    <small><?php echo date('d M y H:i', $json_itm['date'])?>, chars: <?php echo $json_itm['chars']?></small><br>

 
 
<?php
$found_on_wikity = 1;
}
}

if ($found_on_wikity==0)  echo "<p>No pages by this name found on Wikity.cc.</p>";

$sites_arr = explode(',',$sites);
$actual_link = 'http://'.$_SERVER[HTTP_HOST].$_SERVER[REQUEST_URI];
$link_arr = parse_url($actual_link);

echo "<br><em>Pages found in page history:</em><br><br>";

if (strlen($sites)){
  foreach ($sites_arr as $site) {
    $pageexists = get_headers ('http://'.$site.$link_arr['path']);
    if (strpos($pageexists[0], '404') > 0){
  
    } else {
      $found_one = 1;
      echo "Found on ";
      echo '<a href="http://'.$site.$link_arr['path'].'">';
      echo $site.'</a><br>';
    }
  }

if ($found_one == 0) {
    echo "<p>No pages by this name found on sites referenced in page history. You might be on your own for this one!</p>";
  }
} else {
  echo "<p>No other sites referenced in page history. If you get others to copy this, you will see their additions. </p>";
}
?>


					<?php /* get_search_form(); */ ?>
				</div><!-- .page-content -->
			</section><!-- .error-404 -->

		</main><!-- .site-main -->
	</div><!-- .content-area -->

<?php get_footer(); ?>