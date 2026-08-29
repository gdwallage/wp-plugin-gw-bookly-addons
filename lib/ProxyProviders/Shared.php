<?php
namespace GW_BooklyAddons\Lib\ProxyProviders;

/**
 * File: Shared.php
 * Handles frontend and shared proxy hooks for Bookly.
 */

use Bookly\Lib as BooklyLib;
use Bookly\Lib\Proxy;

class Shared extends Proxy\Shared
{
    /**
     * @param BooklyLib\CartInfo $cart_info
     * @param BooklyLib\CartItem $item
     * @return BooklyLib\CartInfo
     */
    public static function prepareCartInfo( $cart_info, $item )
    {
        global $wpdb;
        $user_id = get_current_user_id();
        if ( !$user_id ) return $cart_info;

        $service_id = $item->getServiceId();
        $table_credits = $wpdb->prefix . 'gw_bookly_customer_credits';

        // Check for active credit
        $credit = $wpdb->get_row( $wpdb->prepare( 
            "SELECT id FROM $table_credits WHERE customer_id = %d AND service_id = %d AND balance > 0 LIMIT 1", 
            $user_id, 
            $service_id 
        ) );

        if ( $credit ) {
            // ZERO THE PRICE
            // We set subtotal for this item's context
            $cart_info->setSubtotal( 0 );
            $cart_info->setDeposit( 0 );
            
            // Note: Bookly's CartInfo will re-calculate total based on subtotal.
        }

        return $cart_info;
    }
}
