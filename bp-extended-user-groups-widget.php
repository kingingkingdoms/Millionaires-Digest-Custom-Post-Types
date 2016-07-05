<?php

/* Plugin Name: BuddyPress Extended User Groups Widget
 * Plugin URI: https://buddydev.com/plugins/bp-extended-user-groups-widget/
 * Version: 1.0.3
 * Description: Flexible group listing for BuddyPress user groups
 * Author: Ravi Sharma, BuddyDev
 * Author URI: https://buddydev.com/
 * License: GPL
 *
 * */
Class BP_Extended_User_Groups_Widget_Helper {
    
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

        add_action( 'bp_loaded', array( $this, 'load' ) );
        add_action( 'bp_widgets_init', array( $this, 'register_widget' ), 10 );
        add_action( 'bp_init', array( $this, 'load_text_domain' ) );

    }

    /**
     * load widget class
     */
    public function load() {

        require_once $this->path . 'class-bp-extended-user-groups-widget.php';

    }

	/**
     * load language text domain
     */
    public function load_text_domain() {

        load_plugin_textdomain( 'bp-extended-user-groups-widget', FALSE, plugin_basename( dirname( __FILE__ ) ) . '/languages' );

    }

    public function register_widget() {

        register_widget( 'BP_Extended_User_Groups_Widget' );

    }

}
BP_Extended_User_Groups_Widget_Helper::get_instance();




