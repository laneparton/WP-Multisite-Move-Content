<?php

function mmc_move_content( $target_site_id, $post_id ) {
	// get the original post object as an array
	$post = get_post( $post_id, ARRAY_A );

	$post_terms = array();
	
	// Get all taxonomies and set them up to transfer
	$taxonomies = get_object_taxonomies( $post['post_type'] );
	foreach ( $taxonomies as $taxonomy ) {
		$taxonomy_terms = wp_get_object_terms( $post_id, $taxonomy );

		// If the Taxonomy has terms, store it.
		if($taxonomy_terms) {
			$post_terms[$taxonomy] = $taxonomy_terms;
		}
	}

	// write_log("Post Terms");
	// write_log($post_terms);

	// get all the post meta
	$meta = get_post_custom( $post_id );

	// empty ID field, to tell WordPress to create a new post, not update an existing one
	$post['ID'] = '';

	switch_to_blog( $target_site_id );

	// insert the post
	$inserted_post_id = wp_insert_post( $post ); // insert the post

	/*
	*
	* Setup Taxonomies 
	*
	*/
	$target_post_terms = array();

	foreach ( $taxonomies as $taxonomy ) {
		// Does the Taxonomy Exist?
		// Yes, it will because the theme exists.

		// Does the Taxonomy Contain the Selected Terms?
		// Loop through the terms from the source post and check them on the target blog
		if ( ! empty( $post_terms[$taxonomy] ) ) {
			foreach( $post_terms[$taxonomy] as $post_term ) {
				if( ! term_exists( $post_term->slug, $taxonomy ) ) {
					wp_insert_term($post_term->name, $taxonomy, array( 'slug' => $post_term->slug ) );
				}

				// Find the Taxonomy ID on the target site and pass that to wp_set_object_terms
				$post_term_target_id = get_term_by( 'slug', $post_term->slug, $taxonomy);
				$target_post_terms[] = $post_term_target_id->term_id;
			}
		}

		// Update the terms with the appropriate IDs
		wp_set_object_terms( $inserted_post_id, $target_post_terms, $taxonomy, false );
	}
	/*
	*
	* Media
	*
	*/

	/*
	*
	* ACF Fields & Misc. Post Meta
	*
	*/
	// add post meta
	foreach ( $meta as $key => $values) {
		// if you do not want weird redirects
		if( $key == '_wp_old_slug' ) {
			continue;
		}

		if ( $key == 'testing_other_fields_0_test_image') {
			write_log(wp_get_attachment_metadata($values[0]));
		}

		foreach ($values as $value) {
			add_post_meta( $inserted_post_id, $key, $value );
		}
	}

	restore_current_blog();

	// if you want to copy posts, comment this line
	wp_delete_post( $post_id );
}


if (!function_exists('write_log')) {

    function write_log($log) {
        if (true === WP_DEBUG) {
            if (is_array($log) || is_object($log)) {
                error_log(print_r($log, true));
            } else {
                error_log($log);
            }
        }
    }

}