<?php
if ( ! defined( 'ABSPATH' ) ) exit;
global $wpdb;

$aesirx_analytics_pro_sql = [];

// Prepare the SQL query to change the column type and default value
$aesirx_analytics_pro_sql[] = "ALTER TABLE `{$wpdb->prefix}analytics_wallet` CHANGE `nonce` `nonce` VARCHAR(255) NULL DEFAULT NULL;";
