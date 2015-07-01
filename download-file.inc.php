<?php
/**
 * download-file.inc.php
 *
 * @package Tag/Category Export Utility
 */
 
if (!function_exists('add_action')) {
	$wp_root = '../../..';
	if (file_exists($wp_root.'/wp-load.php')) {
		require_once($wp_root.'/wp-load.php');
	} else {
		require_once($wp_root.'/wp-config.php');
	}
}

if( isset($_GET['file']) )
{
	cat_tag_download( $_GET['file'] );
	exit();
}
else
{
    exit();
}