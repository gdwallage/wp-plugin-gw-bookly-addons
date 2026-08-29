<?php
namespace GW_BooklyAddons\Lib;

if ( ! defined( 'ABSPATH' ) ) exit;

class Boot
{
    public static $plugin_title = 'GW Bookly Addons';
    public static $req_plugin_class = 'BooklyPro\Lib\Plugin';
    public static $req_version = '1.0';

    public static function up()
    {
        $main_file = self::mainFile();
        $plugin    = self::pluginClass();

        register_activation_hook( $main_file, function ( $network_wide ) use ( $plugin ) {
            $plugin::activate( $network_wide );
        } );
        
        register_deactivation_hook( $main_file, function ( $network_wide ) use ( $plugin ) {
            $plugin::deactivate( $network_wide );
        } );

        add_action( 'plugins_loaded', function () use ( $plugin ) {
            if ( class_exists( 'Bookly\Lib\Base\Plugin' ) ) {
                $plugin::run();
            }
        } );
    }

    public static function mainFile()
    {
        return dirname( __DIR__ ) . '/main.php';
    }

    public static function pluginClass()
    {
        return 'GW_BooklyAddons\Lib\Plugin';
    }
}
