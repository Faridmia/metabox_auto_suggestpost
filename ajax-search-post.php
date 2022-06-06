<?php
/*
Plugin Name: Ajax Search Post
Plugin URI: https://github.com/Faridmia/disable-classic-editor-and-widget
Description: This plugin disables the Gutenberg Editor and Gutenberg widget block.
Version: 1.0.0
Author: Farid Mia
Author URI: https://profiles.wordpress.org/faridmia/
License: GPLv2 or later
Text Domain: disable-classic-editor-and-widget
 */
if ( !defined('ABSPATH' )) {
	exit;
}

define( 'DISABLE_CEAW', '1.0.0' );
define( 'DISABLE_CEAW_URL', plugin_dir_url(__FILE__));
define( 'DISABLE_CEAW_PLUGIN_ROOT', __FILE__ );
define( 'DISABLE_CEAW_PLUGIN_URL', plugins_url( '/', DISABLE_CEAW_PLUGIN_ROOT ) );
define( 'DISABLE_CEAW_PLUGIN_PATH', plugin_dir_path( DISABLE_CEAW_PLUGIN_ROOT ) );
define( 'DISABLE_CEAW_PLUGIN_BASE', plugin_basename( DISABLE_CEAW_PLUGIN_ROOT ) );


if( is_admin()) {

    add_filter( 'use_widgets_block_editor', '__return_false' );

    add_filter("use_block_editor_for_post_type", "disable_ceaw_classic_editor");

    function disable_ceaw_classic_editor()
    {
        return false;
    }
}

/**
 * Initialize the plugin
 *
 * @return void
 */
function ajax_search_init_plugin() {
    load_plugin_textdomain( 'ajax-search-post', false, basename( dirname( DISABLE_CEAW_PLUGIN_ROOT ) ) . '/languages' );

}

add_action( 'plugins_loaded', 'ajax_search_init_plugin');


add_action( 'admin_menu', 'rudr_metabox_for_select2' );
add_action( 'save_post', 'rudr_save_metaboxdata', 10, 2 );

/*
 * Add a metabox
 * I hope you're familiar with add_meta_box() function, so, nothing new for you here
 */
function rudr_metabox_for_select2() {
	add_meta_box( 'rudr_select2', 'My metabox for select2 testing', 'rudr_display_select2_metabox', 'post', 'normal', 'default' );
}
 
/*
 * Display the fields inside it
 */
function rudr_display_select2_metabox( $post_object ) {
	
	// do not forget about WP Nonces for security purposes
	
	// I decided to write all the metabox html into a variable and then echo it at the end
	$html = '';
	
	// always array because we have added [] to our <select> name attribute
	$appended_tags = get_post_meta( $post_object->ID, 'rudr_select2_tags',true );
	$appended_posts = get_post_meta( $post_object->ID, 'rudr_select2_posts',true );
	
	/*
	 * It will be just a multiple select for tags without AJAX search
	 * If no tags found - do not display anything
	 * hide_empty=0 means to show tags not attached to any posts
	 */
	if( $tags = get_terms( 'post_tag', 'hide_empty=0' ) ) {
		$html .= '<p><label for="rudr_select2_tags">Tags:</label><br /><select id="rudr_select2_tags" name="rudr_select2_tags[]" multiple="multiple" style="width:99%;max-width:25em;">';
		foreach( $tags as $tag ) {
			$selected = ( is_array( $appended_tags ) && in_array( $tag->term_id, $appended_tags ) ) ? ' selected="selected"' : '';
			$html .= '<option value="' . $tag->term_id . '"' . $selected . '>' . $tag->name . '</option>';
		}
		$html .= '<select></p>';
	}
	
	/*
	 * Select Posts with AJAX search
	 */
	$html .= '<p><label for="rudr_select2_posts">Posts:</label><br /><select id="rudr_select2_posts" name="rudr_select2_posts[]" multiple="multiple" style="width:99%;max-width:25em;">';
	
	if( $appended_posts ) {
		foreach( $appended_posts as $post_id ) {
			$title = get_the_title( $post_id );
			// if the post title is too long, truncate it and add "..." at the end
			$title = ( mb_strlen( $title ) > 50 ) ? mb_substr( $title, 0, 49 ) . '...' : $title;
			$html .=  '<option value="' . $post_id . '" selected="selected">' . $title . '</option>';
		}
	}
	$html .= '</select></p>';
	
	echo $html;
}


function rudr_save_metaboxdata( $post_id, $post ) {
	
	if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return $post_id;
 
	// if post type is different from our selected one, do nothing
	if ( $post->post_type == 'post' ) {
		if( isset( $_POST['rudr_select2_tags'] ) )
			update_post_meta( $post_id, 'rudr_select2_tags', $_POST['rudr_select2_tags'] );
		else
			delete_post_meta( $post_id, 'rudr_select2_tags' );
			
		if( isset( $_POST['rudr_select2_posts'] ) )
			update_post_meta( $post_id, 'rudr_select2_posts', $_POST['rudr_select2_posts'] );
		else
			delete_post_meta( $post_id, 'rudr_select2_posts' );
	}
	return $post_id;
}


add_action( 'admin_enqueue_scripts', 'rudr_select2_enqueue' );
function rudr_select2_enqueue(){

	wp_enqueue_style('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/css/select2.min.css' );
	wp_enqueue_script('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/js/select2.min.js', array('jquery') );
	
	// please create also an empty JS file in your theme directory and include it too
	wp_enqueue_script('mycustom-js', DISABLE_CEAW_URL . 'assets/mycustom.js', array( 'jquery', 'select2' ),true ); 
	
}


add_action( 'wp_ajax_mishagetposts', 'rudr_get_posts_ajax_callback' ); // wp_ajax_{action}
function rudr_get_posts_ajax_callback(){

	// we will pass post IDs and titles to this array
	$return = array();

	// you can use WP_Query, query_posts() or get_posts() here - it doesn't matter
	$search_results = new WP_Query( array( 
		's'=> $_GET['q'], // the search query
		'post_status' => 'publish', // if you don't want drafts to be returned
		'ignore_sticky_posts' => 1,
		'posts_per_page' => 50 // how much to show at once
	) );
	if( $search_results->have_posts() ) :
		while( $search_results->have_posts() ) : $search_results->the_post();	
			// shorten the title a little
			$title = ( mb_strlen( $search_results->post->post_title ) > 50 ) ? mb_substr( $search_results->post->post_title, 0, 49 ) . '...' : $search_results->post->post_title;
			$return[] = array( $search_results->post->ID, $title ); // array( Post ID, Post Title )
		endwhile;
	endif;
	echo json_encode( $return );
	die;
}




