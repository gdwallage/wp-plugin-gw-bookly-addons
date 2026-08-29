<?php
require_once('wp-load.php');
global $wpdb;
$table = $wpdb->prefix . 'bookly_services';
$results = $wpdb->get_results("SELECT id, title, price FROM $table WHERE title LIKE '%Consultation%'", ARRAY_A);
print_r($results);
