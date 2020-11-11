<?php

require_once('move-content.php');

add_filter( 'bulk_actions-edit-book', 'mmc_bulk_actions' );
 
function mmc_bulk_actions( $bulk_array ) {
 
 	if( $sites = get_sites( array(
		// 'site__in' => array( 1,2,3 )
		'site__not_in' => get_current_blog_id(), // excluding current blog
		'number' => 50,
	))) {
		foreach( $sites as $site ) {
			$bulk_array['move_to_'.$site->blog_id] = 'Move to site: ' .$site->blogname . '';
		}
	}
 
	return $bulk_array;
 
}

add_filter( 'handle_bulk_actions-edit-book', 'mmc_bulk_action_handler', 10, 3 );
 
function mmc_bulk_action_handler( $redirect, $doaction, $object_ids ) {
 
	// we need query args to display correct admin notices
	$redirect = remove_query_arg( array( 'mmc_posts_moved', 'mmc_siteid' ), $redirect );
 
 	// our actions begin with "move_to_", so let's check if it is a target action
	if( strpos( $doaction, "move_to_" ) === 0 ) {
		$target_site_id = str_replace( "move_to_", "", $doaction );
 
		foreach ( $object_ids as $post_id ) {
 
			mmc_move_content($target_site_id, $post_id);
 
		}
 
 
		$redirect = add_query_arg( array(
			'mmc_posts_moved' => count( $object_ids ),
			'mmc_siteid' => $target_site_id
		), $redirect );
 
	}
 
	return $redirect;
 
}

add_action( 'admin_notices', 'mmc_bulk_multisite_notices' );
 
function mmc_bulk_multisite_notices() {
 
	if( ! empty( $_REQUEST['mmc_posts_moved'] ) ) {
 
		// because I want to add blog names to notices
		$blog = get_blog_details( $_REQUEST['mmc_siteid'] );
 
		// depending on ho much posts were changed, make the message different
		printf( '<div id="message" class="updated notice is-dismissible"><p>' .
			_n( '%d post has been moved to "%s".', '%d posts have been moved to "%s".', intval( $_REQUEST['mmc_posts_moved'] )
		) . '</p></div>', intval( $_REQUEST['mmc_posts_moved'] ), $blog->blogname );
 
	}
 
}