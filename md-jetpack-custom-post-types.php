<?php

/* Plugin Name: Millionaire's Digest Custom Post Types
 * Plugin URI: https://millionairedigest.com/
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
 * Class Millionairesdigest_Jetpack_Cpt
 */
Class Millionairesdigest_Jetpack_Cpt {
    
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

        require_once $this->path . 'md-custom-post-type-book.php';

    }

	/**
     * load language text domain
     */
    public function load_text_domain() {

        load_plugin_textdomain( 'md-jetpack-custom-post-types', FALSE, plugin_basename( dirname( __FILE__ ) ) . '/languages' );

    }

}
Millionairesdigest_Jetpack_Cpt::get_instance();




