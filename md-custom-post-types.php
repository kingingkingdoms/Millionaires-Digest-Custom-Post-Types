<?php

/* Plugin Name: Millionaire's Digest Custom Post Types
 * Version: 1.0.0
 * Description: Add custom post types through Jetpack specifically created for the Millionaire's Digest.
 * Author: K&L
 * Author URI: https://millionairedigest.com/
 * License: GPL
 *
 * */

// exit if file access directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Millionairesdigest_Cpt
 */
Class Millionairesdigest_Cpt {
    
    private static $instance;
    private $path;
    
    private function __construct() {

        $this->path = plugin_dir_path( __FILE__ );
        $this->setup();

    }
    
    public static function get_instance() {

        if ( ! isset( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;

    }
    
    private function setup() {

        add_action( 'plugins_loaded', array( $this, 'load' ) );
        add_action( 'init', array( $this, 'load_text_domain' ) );

    }

    /**
     * load widget class
     */
    public function load() {

        require_once $this->path . 'md-custom-post-type-article.php';
	require_once $this->path . 'md-custom-post-type-video.php';
	require_once $this->path . 'md-custom-post-type-photo.php';
	require_once $this->path . 'md-custom-post-type-music.php';
	require_once $this->path . 'md-custom-post-type-magazine-article.php';
	require_once $this->path . 'md-custom-post-type-company.php';

    }

}
Millionairesdigest_Cpt::get_instance();




