<?php
if ( ! defined( 'ABSPATH' ) ) exit;
global $wpdb;

$aesirx_analytics_pro_sql = [];

// Create analytics_tag_event table
$aesirx_analytics_pro_sql[] = "
    CREATE TABLE `{$wpdb->prefix}analytics_tag_event` (
    `id` char(36) NOT NULL,
    `event_name` text NOT NULL,
    `domain` varchar(255) NOT NULL,
    `publish` tinyint(1) NOT NULL DEFAULT 0,
    `metric_value` int DEFAULT NULL,
    `engagement_value` int DEFAULT NULL,
    `is_generated` tinyint(1) NOT NULL DEFAULT 0,
    `created_at` datetime NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_domain` (`domain`),
    KEY `idx_event_name` (`event_name`(191))
) ENGINE=InnoDB;";
