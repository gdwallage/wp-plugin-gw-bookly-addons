<?php
require_once('wp-load.php');
global $wpdb;
$table = $wpdb->prefix . 'bookly_services';
$cols = $wpdb->get_results("DESCRIBE $table");
print_r($cols);
