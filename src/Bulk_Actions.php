<?php

namespace WPMultisiteMoveContent;

use WPMultisiteMoveContent\Content_Migration;

class Bulk_Actions {

    public function __construct() {
        add_action( 'wp_loaded', array( $this, 'register_bulk_actions' ) );
        add_action( 'admin_notices', array($this, 'mmc_bulk_multisite_notices' ) );
    }

    public function register_bulk_actions() {
        $args = array(
            'public'   => true,
            '_builtin' => false
        );
    
        $post_types = get_post_types($args);
    
        foreach( $post_types as $post_type ) {
            add_filter( 'bulk_actions-edit-' . $post_type, array( $this, 'mmc_bulk_actions' ) );
            add_filter( 'handle_bulk_actions-edit-' . $post_type, array( $this, 'mmc_bulk_action_handler' ), 10, 3 );
        }	
    }    

    public function mmc_bulk_actions( $bulk_array ) {
 
        if( $sites = get_sites( array(
           // 'site__in' => array( 1,2,3 )
           'site__not_in' => get_current_blog_id(), // excluding current blog
           'number' => 50,
       ))) {
           foreach( $sites as $site ) {
               $bulk_array['move_to_'.$site->blog_id] = 'Move to site: ' .$site->blogname . '';
               $bulk_array['duplicate_to_'.$site->blog_id] = 'Duplicate to site: ' .$site->blogname . '';
           }
       }
    
       return $bulk_array;
    
   }
    
   public function mmc_bulk_action_handler( $redirect, $doaction, $object_ids ) {
    
       // we need query args to display correct admin notices
       $redirect = remove_query_arg( array( 'mmc_posts_moved', 'mmc_siteid' ), $redirect );
    
        // our actions begin with "move_to_", so let's check if it is a target action
       if( strpos( $doaction, "move_to_" ) === 0 ) {
           $target_site_id = str_replace( "move_to_", "", $doaction );
           $original_site_id = get_current_blog_id();
    
           foreach ( $object_ids as $post_id ) {
                $move_content = new Content_Migration( $target_site_id, $post_id, true );
                $move_content->initMigration( false, $original_site_id );
           }
    
    
           $redirect = add_query_arg( array(
               'mmc_posts_moved' => count( $object_ids ),
               'mmc_siteid' => $target_site_id
           ), $redirect );
    
       } else if( strpos( $doaction, "duplicate_to_" ) === 0 ) {
           $target_site_id = str_replace( "duplicate_to_", "", $doaction );
    
           foreach ( $object_ids as $post_id ) {
                $move_content = new Content_Migration( $target_site_id, $post_id, false );
                $move_content->initMigration( false, $original_site_id );
           }
    
    
           $redirect = add_query_arg( array(
               'mmc_posts_duplicated' => count( $object_ids ),
               'mmc_siteid' => $target_site_id
           ), $redirect );
    
       }
    
       return $redirect;
    
   }
    
   public function mmc_bulk_multisite_notices() {
    
       if( ! empty( $_REQUEST['mmc_posts_moved'] ) ) {
    
           // because I want to add blog names to notices
           $blog = get_blog_details( $_REQUEST['mmc_siteid'] );
    
           // depending on ho much posts were changed, make the message different
           printf( '<div id="message" class="updated notice is-dismissible"><p>' .
               _n( '%d post has been moved to "%s".', '%d posts have been moved to "%s".', intval( $_REQUEST['mmc_posts_moved'] )
           ) . '</p></div>', intval( $_REQUEST['mmc_posts_moved'] ), $blog->blogname );
    
       } else if( ! empty( $_REQUEST['mmc_posts_duplicated'] ) ) {
    
           // because I want to add blog names to notices
           $blog = get_blog_details( $_REQUEST['mmc_siteid'] );
    
           // depending on ho much posts were changed, make the message different
           printf( '<div id="message" class="updated notice is-dismissible"><p>' .
               _n( '%d post has been duplicated to "%s".', '%d posts have been duplicated to "%s".', intval( $_REQUEST['mmc_posts_duplicated'] )
           ) . '</p></div>', intval( $_REQUEST['mmc_posts_duplicated'] ), $blog->blogname );
    
       }
    
   }
}