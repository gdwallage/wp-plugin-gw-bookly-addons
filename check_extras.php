<?php
require_once('wp-load.php');
global $wpdb;
$tables = $wpdb->get_col("SHOW TABLES LIKE '%bookly_service_extras%'");
print_r($tables);
if (!empty($tables)) {
    $table = $tables[0];
    $results = $wpdb->get_results("SELECT * FROM $table LIMIT 5", ARRAY_A);
    print_r($results);
}
