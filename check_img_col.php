<?php
require_once('wp-load.php');
global $wpdb;
$table = $wpdb->prefix . 'bookly_services';
$col = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'img'");
echo empty($col) ? "NO_IMG" : "HAS_IMG";
