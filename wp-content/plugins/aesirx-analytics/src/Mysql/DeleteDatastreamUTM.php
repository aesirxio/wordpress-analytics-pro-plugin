<?php

if ( ! defined( 'ABSPATH' ) ) exit;
use AesirxAnalytics\AesirxAnalyticsMysqlHelper;

Class AesirX_Analytics_Delete_Datastream_UTM extends AesirxAnalyticsMysqlHelper
{
    function aesirx_analytics_mysql_execute($params = [])
    {
        global $wpdb;

        $table = $wpdb->prefix . 'analytics_utm';

        $ids = $params['ids'] ?? [];

        if (!is_array($ids) || empty($ids)) {
            return new WP_Error(
                'validation_error',
                'Missing or empty ids',
                ['status' => 400]
            );
        }

        // Sanitize IDs
        $ids = array_values(array_filter(array_map('sanitize_text_field', $ids)));

        if (empty($ids)) {
            return new WP_Error(
                'validation_error',
                'Invalid ids',
                ['status' => 400]
            );
        }

        /**
         * Build placeholders (?, ?, ?)
         */
        $placeholders = implode(',', array_fill(0, count($ids), '%s'));

        // Delete rows
        $sql = "DELETE FROM $table WHERE id IN ($placeholders)";
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $wpdb->query(
            $wpdb->prepare($sql, $ids) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        );

        return 'Utms deleted successfully';
    }
}
