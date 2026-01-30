<?php

global $wpdb;

$aesirx_analytics_pro_sql = [];

// Create analytics_utm table
$aesirx_analytics_pro_sql[] = "
    CREATE TABLE `{$wpdb->prefix}analytics_utm` (
    `id` char(36) NOT NULL,
    `link` text NOT NULL,
    `domain` varchar(255) NOT NULL,
    `publish` tinyint(1) NOT NULL DEFAULT 0,
    `campaign_label` varchar(255) DEFAULT NULL,
    `utm_source` varchar(100) DEFAULT NULL,
    `utm_medium` varchar(100) DEFAULT NULL,
    `utm_campaign` varchar(100) DEFAULT NULL,
    `utm_id` varchar(100) DEFAULT NULL,
    `utm_term` varchar(100) DEFAULT NULL,
    `utm_content` varchar(255) DEFAULT NULL,
    `value` int DEFAULT NULL,
    `value_type` varchar(50) DEFAULT NULL,
    `event_type` varchar(50) DEFAULT NULL,
    `engagement_weight` int DEFAULT NULL,
    `is_generated` tinyint(1) NOT NULL DEFAULT 0,
    `created_at` datetime NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_domain` (`domain`),
    KEY `idx_link` (`link`(191))
) ENGINE=InnoDB;";
