<?php
/*
Plugin Name: bSuite Drop Folder Media Uploader
Plugin URI: http://maisonbisson.com/
Description: Allows photo and media uploads by dropping them into folders by FTP.
Version: alpha 1
Author: Casey Bisson
Author URI: http://maisonbisson.com/blog/
*/

$dropfolder = '/var/www/webdav/';

require_once( ABSPATH . '/wp-admin/includes/image.php' );

function wpdrop_create_attachment( $path , $parent_id = 0 )
{

	if ( $info = wp_check_filetype( $path ))
		$type = $info['type'];
	else
		return FALSE;

	$bits = file_get_contents( $path , FILE_BINARY );

	$slug = sanitize_file_name( basename( $path ));

	$ext = pathinfo( $path );
	$ext = $ext['extension'];

	$slug = "$slug.$ext";

	$file = wp_upload_bits( $slug, NULL, $bits );

	$url = $file['url'];
	$file = $file['file'];

	do_action( 'wp_create_file_in_uploads', $file );

	// Construct the attachment array
	$attachment = array(
		'post_title' => $slug,
		'post_content' => $slug,
		'post_status' => 'attachment',
		'post_parent' => $parent_id,
		'post_mime_type' => $type,
		'guid' => $url
	);

	// Save the data
	$postID = wp_insert_attachment( $attachment, $file );
	wp_update_attachment_metadata( $postID, wp_generate_attachment_metadata( $postID, $file ));
	
	return $postID;
}

function wpdrop_create_post( $title )
{
	// Construct the post array
	$post = array(
		'post_title' => $title,
		'post_content' => '[gallery]',
		'post_status' => 'publish',
	);

	// Save the data
	$postID = wp_insert_post( $post );
	
	return $postID;
}

function wpdrop_readdir( $path , $parent = 0 )
{
	if( ! is_dir( $path ))
		return FALSE;

	$path = rtrim( $path , '/' ) .'/';

	$items = (array) scandir( $path );
	natcasesort( $items );

//print_r( $items );

	if( count( $items ))
	{
		foreach( $items as $item )
		{
			if( preg_match( '/^\./' , $item ))
				continue;

			if( preg_match( '/^\-/' , $item ))
				continue;

			if( is_dir( $path . $item ))
			{
				wpdrop_readdir( $path . $item , $item );
				unlink( $path . $item .'/.DS_Store' );
				rmdir( $path . $item );
			}

			else if( is_file( $path . $item ))
			{
				if( ! is_numeric( $parent ) || ( $parent <> absint( $parent )))
					$parent = wpdrop_create_post( $parent );

				if( wpdrop_create_attachment( $path . $item , $parent ))
				{
					unlink( $path . $item );
					unlink( $path .'._'. $item ); // delete the damn ._* file that MacOS X creates for each real file
				}
			}
		}
	}
}
//wpdrop_readdir( $dropfolder );

function wpdrop_docron()
{
	global $dropfolder;

//error_log( 'doing cron'. $dropfolder );

	wpdrop_readdir( $dropfolder );
}
add_filter( 'bsuite_interval' , 'wpdrop_docron' );
