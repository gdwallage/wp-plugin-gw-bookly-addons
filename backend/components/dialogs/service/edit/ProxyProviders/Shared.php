<?php
namespace GW_BooklyAddons\Backend\Components\Dialogs\Service\Edit\ProxyProviders;

if ( ! defined( 'ABSPATH' ) ) exit;

use Bookly\Backend\Components\Dialogs\Service\Edit\Proxy;
use GW_BooklyAddons\Lib\Plugin;
use Bookly\Lib as BooklyLib;

class Shared extends Proxy\Shared
{
    public static function init()
    {
        parent::init();
        // Asset enqueuing is now handled directly in main.php for robustness
    }

    /**
     * UNIFIED SAVE: This is called by Bookly core when the main "Save" button is clicked.
     * We look for our custom 'gw_wp_page_id' parameter.
     *
     * @inheritDoc
     */
    public static function updateService( array $alert, BooklyLib\Entities\Service $service, array $parameters )
    {
        if ( isset( $parameters['gw_wp_page_id'] ) ) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'gw_bookly_service_links';
            $page_id = (int) $parameters['gw_wp_page_id'];
            $service_id = $service->getId();

            if ( $page_id == 0 ) {
                $wpdb->delete( $table_name, array( 'service_id' => $service_id ), array( '%d' ) );
            } else {
                $wpdb->replace( 
                    $table_name, 
                    array( 'service_id' => $service_id, 'wp_page_id' => $page_id ), 
                    array( '%d', '%d' ) 
                );
            }
        }

        return $alert;
    }
}
