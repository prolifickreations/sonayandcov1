<?php
/**
 * Twenty Twenty functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 * @link http://codex.wordpress.org/Child_Themes
 * 
 * @package WordPress
 * @subpackage Twenty_Twenty
 * @since Twenty Twenty 1.0
 */
// Enqueue scripts and styles
function twentytwenty_child_scripts(){
	wp_enqueue_style( 'twentytwenty-style', get_template_directory_uri() . '/style.css' );
	wp_enqueue_style( 'child-style', get_stylesheet_directory_uri() . '/style.css', array( 'twentytwenty-style' ));
}
add_action( 'wp_enqueue_scripts', 'twentytwenty_child_scripts' );