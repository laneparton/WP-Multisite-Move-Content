<?php

namespace WPMultisiteMoveContent;

use WPMultisiteMoveContent\Migrated_Content;

class Content_Migration {
    private $target_site_id;

    private $source_post_id;

    private $new_post_id;

    private $remove_source_post;

    public function __construct( $target_site_id, $source_post_id, $remove_source_post ) {
        $this->target_site_id = $target_site_id;
        $this->source_post_id = $source_post_id;
        $this->remove_source_post = $remove_source_post;

        write_log($this);
    }

    public function __toString()
    {
        return "Target Site ID: " . $this->target_site_id . " Source Post ID: " . $this->source_post_id . " Remove Source Post: " . $this->remove_source_post;
	}

    public function initMigration( $is_wpml_related_post, $original_site_id ) {
        $post = get_post( $this->source_post_id, ARRAY_A );

        $source_content = new Migrated_Content( $post, $this->target_site_id );

        // if (\function_exists('acf')) {
        //     write_log("ACF Activated");
        //     $source_content->getAcfFields();
        // }

        // Get the Taxonomy Data of Origin
        $source_content->getTaxonomyData();
        $source_content->getMetaData();

        // Create a New Post on Target Site and create the object for it
        $post['ID'] = '';
        switch_to_blog( $this->target_site_id );
        $inserted_post_id = wp_insert_post( $post ); // insert the post
        $this->new_post_id = $inserted_post_id;

        $inserted_post = get_post( $inserted_post_id, ARRAY_A );
    
        $migrated_content = new Migrated_Content( $inserted_post, $this->target_site_id );

        $migrated_content->setTaxonomyData( $source_content->taxonomy_data );
        $migrated_content->setMetaData( $source_content->meta_data );

        if ( ! $is_wpml_related_post ) {
            if ( function_exists('icl_object_id') ) {
                write_log("WPML - Duplicating Posts");
                $migrated_content->wpmlDuplicatePosts();
            }
        }
    
        restore_current_blog();
    
        if ( $this->remove_source_post ) {
            wp_delete_post( $this->source_post_id );
        }
    }

    public function getSourcePostId() {
        return $this->source_post_id;
    }

    public function getNewPostId() {
        return $this->new_post_id;
    }
}