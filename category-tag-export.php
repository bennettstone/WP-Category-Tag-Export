<?php
/*
Plugin Name: Tag/Category Export Utility
Description: Simple category and tag export utility that allows data to be exported easily as CSV files.  Export includes name, post count, url, and slug.
Version: 1.01
Author: Bennett Stone
Author URI: http://bennettstone.com/
*/

/*******************************
 Plugin globals
********************************/
global $version;
$version = 1.01;
global $cte_location;
$cte_location = plugins_url( '' , __FILE__ );
global $file_loc;
$file_loc_base = wp_upload_dir();
$file_loc = $file_loc_base['basedir'];


/*******************************
 Run activation hooks
********************************/
register_activation_hook( __FILE__, 'category_dl_page_activation' );
function category_dl_page_activation() 
{
    global $wpdb;
    global $version;
    update_option( "category_dl_page_vs", $version );
}


/*******************************
 Add admin menu page
********************************/
add_action( 'admin_menu', 'category_dl_page_settings' );
function category_dl_page_settings()
{
    add_submenu_page( 'tools.php', 'Cat/Tag Export', 'Cat/Tag Export', 'manage_options', 'export-categories-tags', 'display_cat_dl_options' );
}


/*******************************
 Force file download
********************************/
function cat_tag_download( $file )
{
    global $file_loc;
    
	$download = $file_loc . '/' . $file;
	if( file_exists( $download ) )
	{
		header( "Content-type: text/csv" );
		header( "Content-disposition: attachment; filename=$file" ); 
		readfile( $download );
		exit;
	}
	else
	{
		echo "The file \"$download\" cannot be found.";
	}
}


/*******************************
 Create Tag file
********************************/
function cat_dl_tag_create()
{
    global $file_loc;
    
    $filename = 'tag-export-' . date( 'Y-m-d' ) . '-' . time() . '.csv';
    $file = $file_loc . '/' . $filename;
    $fp = fopen( $file, "w" );
    $tags = get_tags();
    fputcsv( $fp, array( 'Name','Slug','URL','Number of Entries' ) );
    foreach( $tags as $tag )
    {
    	$tag_link = get_tag_link( $tag->term_id );
    	fputcsv( $fp, array( $tag->name, $tag->slug, $tag_link, $tag->count ) );
    }
    fclose( $fp );

    $files = get_option( 'category_tag_dls_tag' );
    $files[] = $filename;
    $update_list = update_option( 'category_tag_dls_tag', $files );
}


/*******************************
 Create Categories File
********************************/
function cat_dl_cat_create()
{
    global $file_loc;
    
    $filename = 'category-export-' . date( 'Y-m-d' ) . '-' . time() . '.csv';
    $file = $file_loc . '/' . $filename;
    $fp = fopen( $file, "w" );
    $categories = get_categories();
    fputcsv( $fp, array( 'Name','Slug','URL','Number of Entries' ) );
    foreach( $categories as $cat )
    {
    	$cat_link = get_category_link( $cat->cat_ID );
    	fputcsv( $fp, array( $cat->name, $cat->slug, $cat_link, $cat->category_count ) );
    }
    fclose( $fp );

    $files = get_option( 'category_tag_dls_cat' );
    $files[] = $filename;
    $update_list = update_option( 'category_tag_dls_cat', $files );
}


/*******************************
 Create Posts File
********************************/
function cat_dl_posts_create()
{
    global $file_loc;
    
    $filename = 'posts-export-' . date( 'Y-m-d' ) . '-' . time() . '.csv';
    $file = $file_loc . '/' . $filename;
    $fp = fopen( $file, "w" );
    
    $header = array(
        'Name', 
        'URI', 
        'Published', 
        'Category', 
        'Tags'
    );
    fputcsv( $fp, $header );
    
    $query_params = array(
        'posts_per_page' => -1, 
        'orderby' => 'date', 
        'order' => 'DESC', 
        'post_type' => 'post', 
        'post_status' => 'publish'
    );
    query_posts( $query_params );

    // The Loop
    while ( have_posts() ) : the_post();

        setup_postdata( $post );
        $id = get_the_ID();
        $this_link = get_permalink();
        
        $cat_link = get_the_category();
        $cats = array();
        foreach( $cat_link as $c )
        {
            $cats[] = $c->cat_name; 
        }
        $cats = implode( ', ', $cats );
        
        $tags = get_the_tags();
        $taglist = array();
        foreach( $tags as $t )
        {
            $taglist[] = $t->name;
        }
        $taglist = implode( ', ', $taglist );
        
        $data = array(
            get_the_title(), 
            $this_link, 
            get_the_date(), 
            $cats, 
            $taglist
        );
        fputcsv( $fp, $data );
        
    endwhile;

    // Reset Query
    wp_reset_query();
    
 
    fclose( $fp );

    $files = get_option( 'category_tag_dls_post' );
    $files[] = $filename;
    $update_list = update_option( 'category_tag_dls_post', $files );
    
    /* Temporary redirect, wp_redirect experiencing headers already sent errors */
    echo "<meta http-equiv='refresh' content='0;url=tools.php?page=export-categories-tags&msg=4' />";
    exit;
}


/*******************************
 Allow file deletions
********************************/
function cat_dl_file_remove( $file )
{
    global $file_loc;
    $the_file = $file_loc . '/' . $file;
    if( file_exists( $the_file ) )
    {
        $list_tag = get_option( 'category_tag_dls_tag' );
        $list_cat = get_option( 'category_tag_dls_cat' );
        chmod( $the_file, 0777 );
        $success = unlink( $the_file );
        if( $success )
        {
            cat_dl_file_array_remove( $file );
            return true;
        }
    }
    else
    {
        return false;
    }
}


/*******************************
 Function to seek out file in option value and remove
********************************/
function cat_dl_file_array_remove( $file )
{
    $list_tag = get_option( 'category_tag_dls_tag' );
    $list_cat = get_option( 'category_tag_dls_cat' );

    if( !empty( $list_tag ) )
    {
        foreach( $list_tag as $tag_key => $tag_value )
        {
            if( $file === $tag_value )
            {
                unset( $list_tag[$tag_key] );
            }
        }   
    }
    if( !empty( $list_cat ) )
    {
        foreach( $list_cat as $cat_key => $cat_value )
        {
            if( $file === $cat_value )
            {
                unset( $list_cat[$cat_key] );
            }
        }   
    }
    
    update_option( 'category_tag_dls_tag', $list_tag );
    update_option( 'category_tag_dls_cat', $list_cat );
}


/*******************************
 Plugin options page output
********************************/
function display_cat_dl_options()
{
    
    if( !current_user_can( 'manage_options' ) )
    {
        wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
    }
    global $wpdb;
    global $cte_location;
    global $dl_location;
    global $file_loc;
    
    
    /* Export post list to CSV */
    if( isset( $_POST['export-posts'] ) && $_POST['export-posts'] == 'yes' )
    {       
        cat_dl_posts_create();   
        exit;
    }
    
    /* Export tag list to CSV */
    if( isset( $_POST['export-tags'] ) && $_POST['export-tags'] == 'yes' )
    {       
        cat_dl_tag_create();   
        
        /* Temporary redirect, wp_redirect experiencing headers already sent errors */
        echo "<meta http-equiv='refresh' content='0;url=tools.php?page=export-categories-tags&msg=1' />";
        exit;
    }
    
    /* Export categories to CSV */
    if( isset( $_POST['export-categories'] ) && $_POST['export-categories'] == 'yes' )
    {       
        cat_dl_cat_create();   
        
        /* Temporary redirect, wp_redirect experiencing headers already sent errors */
        echo "<meta http-equiv='refresh' content='0;url=tools.php?page=export-categories-tags&msg=2' />";
        exit;
    }
    
    /* Delete file */
    if( !empty( $_GET['file_remove'] ) && $_GET['value'] == 'true' )
    {       
        $remove_file = cat_dl_file_remove( $_GET['file_remove'] );
        if( $remove_file )
        {            
           /* Temporary redirect, wp_redirect experiencing headers already sent errors */
            echo "<meta http-equiv='refresh' content='0;url=tools.php?page=export-categories-tags&msg=3' />";
            exit;  
        } 
    }
    ?>
    
    <div class="wrap">

         <h2>Export Categories/Tags to CSV</h2>
         
         <?php
         if( !empty( $_GET['msg'] ) )
         {
             if( $_GET['msg'] == '1' )
                 echo '<div id="message" class="updated highlight" style="padding: 10px;">Tag File created successfully!</div>'; 
            if( $_GET['msg'] == '2' )
                echo '<div id="message" class="updated highlight" style="padding: 10px;">Category File created successfully!</div>';  
            if( $_GET['msg'] == '3' )
                echo '<div id="message" class="updated highlight" style="padding: 10px;">File deleted successfully!</div>'; 
            if( $_GET['msg'] == '4' )
                echo '<div id="message" class="updated highlight" style="padding: 10px;">Posts file created successfully</div>';          
         }
         ?>

        <form id="" action="" method="post">
        <input type="hidden" name="export-tags" value="yes" />

        <input type="submit" class="button-primary" style="float:left;" name="" value="Create Tag CSV Download" />
        </form>

        <form id="" action="" method="post">
        <input type="hidden" name="export-categories" value="yes" />

        <input type="submit" class="button-primary" name="" value="Create Categories CSV Download" />
        </form>
        
        <form id="" action="" method="post">
        <input type="hidden" name="export-posts" value="yes" />

        <input type="submit" class="button-primary" name="" value="Create Posts CSV Download" />
        </form>

        <?php
        /**
         * Ouput list of tag files generated
         */
        $prev_tag = get_option( 'category_tag_dls_tag' );
        if( !empty( $prev_tag ) )
        {
            echo '<h3>Download Tag File Exports</h3>';
            foreach( $prev_tag as $tag_file => $tag_loc )
            {
                if( file_exists( $file_loc . '/' . $tag_loc ) )
                {
                    echo '<a href="' . $cte_location . '/download-file.inc.php?file=' . $tag_loc .'">'.$tag_loc.'</a>  (<a href="tools.php?page=export-categories-tags&file_remove=' . $tag_loc . '&value=true" onclick="return confirm(\'You sure you want to delete this file?\');">X</a>)<br />';   
                }
            }
        }
        
        /**
         * Output list of category files generated
         */
        $prev_cat = get_option( 'category_tag_dls_cat' );
        if( !empty( $prev_cat ) )
        {
            echo '<h3>Download Category File Exports</h3>';
            foreach( $prev_cat as $cat_file => $cat_loc )
            {
                if( file_exists( $file_loc . '/' . $cat_loc ) )
                {
                    echo '<a href="' . $cte_location . '/download-file.inc.php?file=' . $cat_loc .'">'.$cat_loc.'</a>  (<a href="tools.php?page=export-categories-tags&file_remove=' . $cat_loc . '&value=true" onclick="return confirm(\'You sure you want to delete this file?\');">X</a>)<br />';    
                }
            }
        }
        
        /**
         * Output list of posts files generated
         */
        $prev_posts = get_option( 'category_tag_dls_post' );
        if( !empty( $prev_posts ) )
        {
            echo '<h3>Download Post File Exports</h3>';
            foreach( $prev_posts as $cat_file => $cat_loc )
            {
                if( file_exists( $file_loc . '/' . $cat_loc ) )
                {
                    echo '<a href="' . $cte_location . '/download-file.inc.php?file=' . $cat_loc .'">'.$cat_loc.'</a>  (<a href="tools.php?page=export-categories-tags&file_remove=' . $cat_loc . '&value=true" onclick="return confirm(\'You sure you want to delete this file?\');">X</a>)<br />';    
                }
            }
        }
        ?>

        <p><i>Generated files are stored in the wp-content/uploads directory.</i></p>
    </div>
    
<?php 
}