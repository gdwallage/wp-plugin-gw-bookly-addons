<?php defined( 'ABSPATH' ) || exit; // Exit if accessed directly
/*
Plugin Name: GW Bookly Addons
Description: Custom Bookly extensions for Gary Wallage Photography.
Version: 1.19.3
Author: Gary Wallage
Text Domain: gw-bookly
*/

$addon = implode( DIRECTORY_SEPARATOR, array( str_replace( array( '/', '\\' ), DIRECTORY_SEPARATOR, WP_PLUGIN_DIR ), 'bookly-addon-pro', 'lib', 'addons', basename( __DIR__ ) ) );
if ( ! file_exists( $addon ) || $addon === __DIR__ ) {
    include_once __DIR__ . '/autoload.php';
    // Enqueue Assets for GW Addons
add_action( 'admin_enqueue_scripts', function() {
    if ( isset( $_GET['page'] ) && $_GET['page'] === 'bookly-services' ) {
        wp_enqueue_script( 
            'gw-addons-tab-js', 
            plugins_url( 'backend/components/dialogs/service/edit/resources/js/gw-addons-tab.js', __FILE__ ), 
            array( 'jquery' ), 
            '3000.23.0', 
            true 
        );
        
        wp_localize_script( 'gw-addons-tab-js', 'GW_BooklyAddons', array(
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'gw_bookly_addons' )
        ) );
    }
} );

// Initialize Addon
GW_BooklyAddons\Lib\Boot::up();
}
