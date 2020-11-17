<?php

namespace WPMultisiteMoveContent;

class Migrated_Content {

	public $post_obj;

	private $post_id;

	private $post_type;

    public $taxonomy_data;

    public $meta_data;

	public $media_data;
	
	public $related_acf_posts;
	
	private $target_site_id;

    public function __construct( $post_obj, $target_site_id ) {
		$this->post_obj		   = $post_obj;
		$this->post_id		   = $post_obj['ID'];
		$this->post_type	   = $post_obj['post_type'];
		$this->target_site_id  = $target_site_id;
		$this->original_site_id  = $original_site_id;

		write_log($this);
	}

	public function __toString()
    {
        return "Post ID: " . $this->post_id . " Post Type: " . $this->post_type;
	}
	
	public function getMetaData() {
		$this->meta_data = get_post_custom( $this->post_id );

		// write_log("getMetaData");
		// write_log($this->meta_data);
		// write_log("/getMetaData");
	}

	public function getTaxonomyData() {
		$post_terms = array();
        
        // Get all taxonomies and set them up to transfer
        $taxonomies = get_object_taxonomies( $this->post_type );
        foreach ( $taxonomies as $taxonomy ) {
            $taxonomy_terms = wp_get_object_terms( $this->post_id, $taxonomy );
    
            // If the Taxonomy has terms, store it.
            if($taxonomy_terms) {
                $post_terms[$taxonomy] = $taxonomy_terms;
            }
		}
		
		$this->taxonomy_data = array(
			'taxonomies' => $taxonomies,
			'terms' 	 => $post_terms,
		);

		// write_log("getTaxonomyData");
		// write_log($this->taxonomy_data);
		// write_log("/getTaxonomyData");
	}

	public function wpmlDuplicatePosts() {
		$post_id = $this->post_id;
		if ( $post_id ) {
			do_action( 'wpml_make_post_duplicates', $post_id );
		}
	}

	public function setTaxonomyData( $new_taxonomy_data ) {
		$this->taxonomy_data = $new_taxonomy_data;

		// Add Taxonomies to New Post
		$target_post_terms = array();
		$taxonomies = $this->taxonomy_data['taxonomies'];

		foreach ( $taxonomies as $taxonomy ) {
			// Does the Taxonomy Contain the Selected Terms?
			// Loop through the terms from the source post and check them on the target blog
			$terms = $this->taxonomy_data['terms'];

			if ( ! empty( $terms[$taxonomy] ) ) {
				foreach( $terms[$taxonomy] as $post_term ) {
					if( ! term_exists( $post_term->slug, $taxonomy ) ) {
						wp_insert_term($post_term->name, $taxonomy, array( 'slug' => $post_term->slug ) );
					}
	
					// Find the Taxonomy ID on the target site and pass that to wp_set_object_terms
					$post_term_target_id = get_term_by( 'slug', $post_term->slug, $taxonomy);
					$target_post_terms[] = $post_term_target_id->term_id;
				}
			}
	
			// Update the terms with the appropriate IDs
			wp_set_object_terms( $this->post_id, $target_post_terms, $taxonomy, false );
		}
	}

	public function setMetaData( $new_meta_data ) {
		$this->meta_data = $new_meta_data;

		// Set Meta Data on New Post
		if( $this->meta_data ) {
			foreach ( $this->meta_data as $key => $values) {
				// if you do not want weird redirects
				if( $key == '_wp_old_slug' ) {
					continue;
				}
	
				foreach ($values as $value) {
					add_post_meta( $this->post_id, $key, $value );
				}
			}
		}
	}
}