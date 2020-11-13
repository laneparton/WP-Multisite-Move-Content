<?php

namespace WPMultisiteMoveContent;

class Migrated_Content {

	public $post_obj;

	private $post_id;

	private $post_type;

    public $taxonomy_data;

    public $meta_data;

	public $media_data;
	
	public $related_translated_posts;

	public $related_acf_posts;
	
	private $original_site_id;

	private $target_site_id;

    public function __construct( $post_obj, $original_site_id, $target_site_id ) {
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

	public function getTranslatedPosts() {
		$languages = apply_filters( 'wpml_active_languages', NULL );

		// write_log("getTranslatedPosts/languages");
		// write_log($languages);
		// write_log("/getTranslatedPosts/languages");
 
		if ( !empty( $languages ) ) {
			foreach( $languages as $language ) {
				// write_log("getTranslatedPosts/Language");
				// write_log($language);
				// write_log("/getTranslatedPosts/Language");
				$language_code = $language['code'];

				$related_post_id = apply_filters( 'wpml_object_id', $this->post_id, $this->post_type, false, $language_code );

				if ( $related_post_id ) {
					$this->related_translated_posts[$language_code] = $related_post_id;
				}
			}

			write_log("getTranslatedPosts/Related");
			write_log($this->related_translated_posts);
			write_log("/getTranslatedPosts/Related");
		}
	}

	public function setupTranslatedPosts( $related_translations ) {

		write_log("setupTranslatedPosts/related_translations");
		write_log($related_translations);
		write_log("/setupTranslatedPosts/related_translations");

		//  Loop Through Related Translations
			// Migrate That Translated Post
			// initMigration - $original_site_id, $related_translation_id, $remove_source_post
				// $migrated_content->setupTranslatedPosts
				// Register New Translation List
				// It's getting past english, $language === 'en
				// but failing to migrate the rest of the content

		$new_related_translations = array();

		if ( count( $related_translations ) > 1 ) {		
			foreach ( $related_translations as $language => $related_translation_id ) {
				if ($language === 'en') {
					$new_related_translations[$language] = $this->post_id;
					continue;
				}
				write_log($related_translation_id);
				write_log($this->target_site_id);
				$new_related_translation_object = new Content_Migration( $this->target_site_id, $related_translation_id, true );
				$new_related_translation_object->initMigration( true, $this->original_site_id );

				$new_related_translations[$language] = $new_related_translation_object->getNewPostId();
			}
		}

		write_log("setupTranslatedPosts/new_related_translations");
		write_log($new_related_translations);
		write_log("/setupTranslatedPosts/new_related_translations");

		foreach( $new_related_translations as $language => $new_translation_id ) {
			// https://wpml.org/wpml-hook/wpml_element_type/
			$wpml_element_type = apply_filters( 'wpml_element_type', $this->post_type );
			
			// get the language info of the original post
			// https://wpml.org/wpml-hook/wpml_element_language_details/
			$get_language_args = array(
				'element_id' => $new_related_translations['en'],
				'element_type' => $this->post_type
			);

			write_log($get_language_args);
			$original_post_language_info = apply_filters( 'wpml_element_language_details', null, $get_language_args );
			
			write_log($original_post_language_info);
			$set_language_args = array(
				'element_id'    => $new_translation_id,
				'element_type'  => $wpml_element_type,
				'trid'   => $original_post_language_info->trid,
				'language_code'   => $language,
				'source_language_code' => $original_post_language_info->language_code
			);
			write_log($set_language_args);

			do_action( 'wpml_set_element_language_details', $set_language_args );
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