<?php


use AesirxAnalytics\AesirxAnalyticsMysqlHelper;

Class AesirX_Analytics_Delete_Datastream_Tag_Event extends AesirxAnalyticsMysqlHelper
{
    function aesirx_analytics_mysql_execute($params = [])
    {
        global $wpdb;

        $table = $wpdb->prefix . 'analytics_tag_event';

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

        $wpdb->query(
            $wpdb->prepare($sql, $ids) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        );

        return 'Tag Events deleted successfully';
    }
}
