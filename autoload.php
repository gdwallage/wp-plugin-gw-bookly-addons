<?php namespace GW_BooklyAddons;

if ( ! defined( 'ABSPATH' ) ) exit;

// Robust, Case-Agnostic Autoloader for Linux compatibility
spl_autoload_register( function ( $class ) {
    $prefix = __NAMESPACE__ . '\\';
    if ( strpos( $class, $prefix ) === 0 ) {
        $path = str_replace( array( $prefix, '\\' ), array( '', '/' ), $class );
        $parts = explode( '/', $path );
        $filename = array_pop( $parts );
        
        $current_dir = __DIR__;
        foreach ( $parts as $part ) {
            $found = false;
            if ( is_dir( $current_dir ) ) {
                $items = scandir( $current_dir );
                foreach ( $items as $item ) {
                    if ( strtolower( $item ) === strtolower( $part ) ) {
                        $current_dir .= '/' . $item;
                        $found = true;
                        break;
                    }
                }
            }
            if ( ! $found ) return;
        }
        
        $file = $current_dir . '/' . $filename . '.php';
        if ( file_exists( $file ) ) {
            require_once $file;
        }
    }
} );
