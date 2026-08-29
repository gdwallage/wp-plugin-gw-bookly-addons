<?php
namespace GW_BooklyAddons\Backend\Modules\Services\ProxyProviders;

if ( ! defined( 'ABSPATH' ) ) exit;

use Bookly\Backend\Modules\Services\Proxy;
use GW_BooklyAddons\Lib;

class Shared extends Proxy\Shared
{
    public static function init()
    {
        parent::init();
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueueAssetsForServices' ) );
    }

    public static function enqueueAssetsForServices()
    {
        // Only enqueue on the Bookly Services page
        if ( isset( $_GET['page'] ) && $_GET['page'] === 'bookly-services' ) {
            wp_enqueue_script( 'gw-addons-tab-js', plugins_url( 'resources/js/gw-addons-tab.js', Lib\Boot::mainFile() ), array( 'jquery' ), '1.0.0', true );
            
            // Localize for AJAX
            wp_localize_script( 'gw-addons-tab-js', 'GW_BooklyAddons', array(
                'ajaxurl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'gw_bookly_addons' )
            ) );
        }
    }
}
