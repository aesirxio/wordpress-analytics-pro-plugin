<?php
if ( ! defined( 'ABSPATH' ) ) exit;
global $wpdb;

$aesirx_analytics_pro_sql = [];

// Add a primary key to the id column of the analytics_conversion table
$aesirx_analytics_pro_sql[] = "ALTER TABLE `{$wpdb->prefix}analytics_conversion` ADD PRIMARY KEY(`id`);";