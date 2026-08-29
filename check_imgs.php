<?php
require_once('wp-load.php');
global $wpdb;
$table = $wpdb->prefix . 'bookly_services';
$results = $wpdb->get_results("SELECT id, title, img FROM $table WHERE img != '' LIMIT 5", ARRAY_A);
print_r($results);
