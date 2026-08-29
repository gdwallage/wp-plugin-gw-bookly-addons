<?php
namespace GW_BooklyAddons\Lib;

if ( ! defined( 'ABSPATH' ) ) exit;

class Utils
{
    /**
     * HELPER: Get the Bookly Service ID for a given WordPress Page ID
     */
    public static function getServiceIdForPage( $page_id )
    {
        global $wpdb;
        if ( empty( $page_id ) ) return false;

        // 1. Try official link
        $official_table = $wpdb->prefix . 'gw_bookly_service_links';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$official_table'" ) == $official_table ) {
            $service_id = $wpdb->get_var( $wpdb->prepare( "SELECT service_id FROM $official_table WHERE wp_page_id = %d", $page_id ) );
            if ( $service_id ) return $service_id;
        }

        // 2. Fallback to legacy meta
        return get_post_meta( $page_id, '_gary_bookly_id', true );
    }

    /**
     * HELPER: Get Bookly Service Data
     */
    public static function getBooklyServiceData( $service_id )
    {
        if ( empty( $service_id ) ) return false;
        global $wpdb;
        $table = $wpdb->prefix . 'bookly_services';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) != $table ) return false;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $service_id ), ARRAY_A );
    }

    /**
     * HELPER: Get the WordPress Page ID for a given Bookly Service ID
     */
    public static function getPageIdForService( $service_id )
    {
        global $wpdb;
        if ( empty( $service_id ) ) return false;

        // 1. Try official link
        $official_table = $wpdb->prefix . 'gw_bookly_service_links';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$official_table'" ) == $official_table ) {
            $page_id = $wpdb->get_var( $wpdb->prepare( "SELECT wp_page_id FROM $official_table WHERE service_id = %d", $service_id ) );
            if ( $page_id ) return $page_id;
        }

        // 2. Fallback to legacy meta
        return $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_gary_bookly_id' AND meta_value = %s LIMIT 1", $service_id ) );
    }

    /**
     * HELPER: Get sub-service summary
     */
    public static function getSubServiceSummary( $id, $is_post_id = true )
    {
        global $wpdb;

        if ( $is_post_id ) {
            $post_id = (int) $id;
            $bookly_id = self::getServiceIdForPage( $post_id );
        } else {
            $bookly_id = (int) $id;
            $post_id = self::getPageIdForService( $bookly_id );
        }

        $bookly_data = self::getBooklyServiceData( $bookly_id );
        $parent_price = $bookly_data ? (float) $bookly_data['price'] : 0;
        
        $inclusions = array();
        $inc_titles = array();
        $inc_total_val = 0;
        $inc_total_duration = 0;

        $table_sub_services = $wpdb->prefix . 'bookly_sub_services';
        $table_services = $wpdb->prefix . 'bookly_services';
        $inclusions_table = $wpdb->prefix . 'gw_bookly_service_inclusions';

        $processed_ids = array();
        
        // 1. Check Custom GW Inclusions
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$inclusions_table'" ) == $inclusions_table ) {
            $custom_inclusions = $wpdb->get_results( $wpdb->prepare( "SELECT included_id FROM $inclusions_table WHERE parent_id = %d ORDER BY position ASC", $bookly_id ) );
            foreach ( $custom_inclusions as $inc ) {
                $sub_data = self::getBooklyServiceData( $inc->included_id );
                if ( $sub_data && ! in_array( $inc->included_id, $processed_ids ) ) {
                    $processed_ids[] = $inc->included_id;
                    $clean_title = self::cleanServiceName( $sub_data['title'] );
                    $sub_data['clean_title'] = $clean_title;
                    
                    $sub_wp_id = self::getPageIdForService( $inc->included_id );
                    $sub_data['permalink'] = $sub_wp_id ? get_permalink( $sub_wp_id ) : '';
                    
                    // Resolve Thumbnail
                    $thumb = $sub_wp_id ? get_the_post_thumbnail_url( $sub_wp_id, 'gw-service-icon' ) : '';
                    if ( ! $thumb && ! empty( $sub_data['img'] ) ) {
                        $upload_dir = wp_upload_dir();
                        $thumb = $upload_dir['baseurl'] . '/bookly/' . $sub_data['img'];
                    }
                    $sub_data['thumbnail'] = $thumb;
                    
                    $inc_titles[] = $clean_title;
                    $inc_total_val += (float) $sub_data['price'];
                    $inc_total_duration += (int) $sub_data['duration'];
                    $inclusions[] = $sub_data;
                }
            }
        }

        // 2. Native Bookly Compound
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_sub_services'" ) == $table_sub_services ) {
            $sub_relations = $wpdb->get_results( $wpdb->prepare( "SELECT sub_service_id FROM $table_sub_services WHERE service_id = %d ORDER BY position ASC", $bookly_id ) );
            foreach ( $sub_relations as $rel ) {
                if ( in_array( $rel->sub_service_id, $processed_ids ) ) continue;
                
                $sub_data = self::getBooklyServiceData( $rel->sub_service_id );
                if ( $sub_data ) {
                    $processed_ids[] = $rel->sub_service_id;
                    $clean_title = self::cleanServiceName( $sub_data['title'] );
                    $sub_data['clean_title'] = $clean_title;
                    
                    $sub_wp_id = self::getPageIdForService( $rel->sub_service_id );
                    $sub_data['permalink'] = $sub_wp_id ? get_permalink( $sub_wp_id ) : '';
                    
                    // Resolve Thumbnail
                    $thumb = $sub_wp_id ? get_the_post_thumbnail_url( $sub_wp_id, 'gw-service-icon' ) : '';
                    if ( ! $thumb && ! empty( $sub_data['img'] ) ) {
                        $upload_dir = wp_upload_dir();
                        $thumb = $upload_dir['baseurl'] . '/bookly/' . $sub_data['img'];
                    }
                    $sub_data['thumbnail'] = $thumb;
                    
                    $inc_titles[] = $clean_title;
                    $inc_total_val += (float) $sub_data['price'];
                    $inc_total_duration += (int) $sub_data['duration'];
                    $inclusions[] = $sub_data;
                }
            }
        }

        // 3. Bookly Service Extras (Add-ons)
        $extras_table = $wpdb->prefix . 'bookly_service_extras';
        if ( $bookly_id && $wpdb->get_var( "SHOW TABLES LIKE '$extras_table'" ) == $extras_table ) {
            $extras = $wpdb->get_results( $wpdb->prepare( "SELECT id, title, price, duration FROM $extras_table WHERE service_id = %d", $bookly_id ) );
            if ( $extras ) {
                foreach ( $extras as $extra ) {
                    $extra_id = 'extra-' . $extra->id;
                    if ( in_array( $extra_id, $processed_ids ) ) continue;
                    
                    $processed_ids[] = $extra_id;
                    $clean_title = self::cleanServiceName( $extra->title );
                    $inc_titles[] = $clean_title;
                    $inc_total_val += (float) $extra->price;
                    $inc_total_duration += (int) $extra->duration;
                    $inclusions[] = array(
                        'id' => $extra_id,
                        'title' => $extra->title,
                        'clean_title' => $clean_title,
                        'price' => (float) $extra->price,
                        'duration' => (int) $extra->duration,
                        'thumbnail' => '', 
                        'permalink' => '',
                        'info' => ''
                    );
                }
            }
        }

        $savings = 0;
        if ( $inc_total_val > $parent_price ) {
            $savings = $inc_total_val - $parent_price;
        }

        return array(
            'titles'         => $inc_titles,
            'total_value'    => $inc_total_val,
            'savings'        => $savings,
            'parent_price'   => $parent_price,
            'total_duration' => $inc_total_duration,
            'grid_items'     => $inclusions,
        );
    }

    public static function cleanServiceName( $name )
    {
        if ( empty( $name ) ) return $name;
        $name = preg_replace( '/^[A-Z0-9]+\s*-\s*/', '', $name );
        return ucwords( strtolower( $name ) );
    }

    public static function formatDuration( $seconds )
    {
        if ( empty( $seconds ) ) return '';
        if ( ! is_numeric( $seconds ) ) return trim( $seconds );
        
        $hours = round( (int) $seconds / 3600, 1 );
        if ( $hours <= 0 ) return '';
        return $hours . ' Hours';
    }
}
