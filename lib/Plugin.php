<?php
namespace GW_BooklyAddons\Lib;

if ( ! defined( 'ABSPATH' ) ) exit;

use Bookly\Lib as BooklyLib;
use GW_BooklyAddons\Backend;

class Plugin extends BooklyLib\Base\Plugin
{
    protected static $prefix = 'gw_bookly_addons';
    protected static $slug = 'gw-bookly-addons';
    protected static $root_namespace = 'GW_BooklyAddons';
    protected static $basename;
    protected static $text_domain;

    /**
     * Override update method to prevent errors.
     */
    protected static function update()
    {
        // Skip update checks for custom GW Addon
    }

    /**
     * Override update checker to prevent purchase code notices.
     */
    protected static function initUpdateChecker()
    {
        // No update checker needed for local addon
    }

    /**
     * Override purchase code to return a dummy value to avoid notices.
     */
    public static function getPurchaseCode( $blog_id = null )
    {
        return 'GW-ADDON-LOCAL';
    }

    protected static function init()
    {
        // Register Blocks
        \GW_BooklyAddons\Lib\Blocks::init();

        // Register Proxy Providers for Backend Dialogs
        \GW_BooklyAddons\Backend\Components\Dialogs\Service\Edit\ProxyProviders\Shared::init();
        
        // Register General Shared Proxy Providers (Frontend/Pricing)
        \GW_BooklyAddons\Lib\ProxyProviders\Shared::init();

        // Register Ajax
        \GW_BooklyAddons\Lib\Ajax::init();
    }

    protected static function registerAjax()
    {
        Backend\Modules\Services\Ajax::init();
    }
    
    public static function activate( $network_wide )
    {
        Installer::install();
    }
}
