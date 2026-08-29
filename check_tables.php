<?php
require_once('wp-load.php');
global $wpdb;
$tables = $wpdb->get_col("SHOW TABLES LIKE '%gw_bookly_%'");
print_r($tables);
