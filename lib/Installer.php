<?php
namespace GW_BooklyAddons\Lib;

if ( ! defined( 'ABSPATH' ) ) exit;

class Installer
{
    public static function install()
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        // 1. Service to Page Links
        $table_links = $wpdb->prefix . 'gw_bookly_service_links';
        $sql_links = "CREATE TABLE $table_links (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            service_id int(11) NOT NULL,
            wp_page_id int(11) NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY service_id (service_id)
        ) $charset_collate;";
        dbDelta( $sql_links );

        // 2. Package Inclusions (Static Definition)
        $table_inclusions = $wpdb->prefix . 'gw_bookly_service_inclusions';
        $sql_inclusions = "CREATE TABLE $table_inclusions (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            parent_id int(11) NOT NULL,
            included_id int(11) NOT NULL,
            position int(11) DEFAULT 0,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        dbDelta( $sql_inclusions );

        // 3. Customer Entitlements (Dynamic Credit Bank)
        $table_credits = $wpdb->prefix . 'gw_bookly_customer_credits';
        $sql_credits = "CREATE TABLE $table_credits (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            customer_id int(11) NOT NULL,
            service_id int(11) NOT NULL,
            balance int(11) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        dbDelta( $sql_credits );
    }
}
