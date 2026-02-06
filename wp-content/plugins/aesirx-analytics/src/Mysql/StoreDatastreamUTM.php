<?php

if ( ! defined( 'ABSPATH' ) ) exit;
use AesirxAnalytics\AesirxAnalyticsMysqlHelper;

Class AesirX_Analytics_Store_Datastream_UTM extends AesirxAnalyticsMysqlHelper
{
    function aesirx_analytics_mysql_execute($params = [])
    {
        global $wpdb;
         // 2️⃣ Validate link
        if (empty(trim($params['link'] ?? ''))) {
            return new WP_Error(
                'validation_error',
                "Missing or empty 'link' field",
                ['status' => 400]
            );
        }

        $table = $wpdb->prefix . 'analytics_utm';
        $now   = gmdate('Y-m-d H:i:s');
        $id    = $params['_id']['$oid'] ?? null;

        // 3️⃣ Update existing UTM
        if ($id) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $existing = $wpdb->get_row(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $wpdb->prepare("SELECT * FROM $table WHERE id = %s", $id),
                ARRAY_A
            );

            if (!$existing) {
                return new WP_Error(
                    'not_found',
                    'UTM not found',
                    ['status' => 404]
                );
            }

            $update_data = [];

            // Fields allowed to update
            $fields = [
                'link',
                'domain',
                'campaign_label',
                'utm_source',
                'utm_medium',
                'utm_campaign',
                'utm_id',
                'utm_term',
                'utm_content',
                'value',
                'value_type',
                'event_type',
                'engagement_weight',
                'is_generated',
            ];

            foreach ($fields as $field) {
                if (array_key_exists($field, $params)) {
                    $update_data[$field] = $params[$field];
                }
            }

            // Special handling for boolean
            if (array_key_exists('publish', $params)) {
                $update_data['publish'] = (int) !empty($params['publish']);
            }

            if (!empty($update_data)) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
                $wpdb->update(
                    $table,
                    $update_data,
                    ['id' => $id]
                );
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $row = $wpdb->get_row(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $wpdb->prepare("SELECT * FROM $table WHERE id = %s", $id),
                ARRAY_A
            );

            return parent::aesirx_analytics_format_response_utm($row);
        }
        
        // 4️⃣ Prevent duplicate link per domain
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $duplicate = $wpdb->get_var(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT COUNT(*) FROM $table WHERE domain = %s AND link = %s",
                $params['domain'],
                $params['link']
            )
        );

        if ($duplicate) {
            return new WP_Error(
                'validation_error',
                "Utm with this 'link' already exists",
                ['status' => 400]
            );
        }

         // 5️⃣ Insert new UTM
        $new_id = wp_generate_uuid4();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $wpdb->insert($table, [
            'id' => $new_id,
            'link' => $params['link'],
            'domain' => $params['domain'] ?? '',
            'publish' => !empty($params['publish']),
            'campaign_label' => $params['campaign_label'] ?? null,
            'utm_source' => $params['utm_source'] ?? null,
            'utm_medium' => $params['utm_medium'] ?? null,
            'utm_campaign' => $params['utm_campaign'] ?? null,
            'utm_id' => $params['utm_id'] ?? null,
            'utm_term' => $params['utm_term'] ?? null,
            'utm_content' => $params['utm_content'] ?? null,
            'value' => $params['value'] ?? null,
            'value_type' => $params['value_type'] ?? null,
            'event_type' => $params['event_type'] ?? null,
            'engagement_weight' => $params['engagement_weight'] ?? null,
            'is_generated' => !empty($params['is_generated']),
            'created_at' => $now,
        ]);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $row = $wpdb->get_row(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->prepare("SELECT * FROM $table WHERE id = %s", $new_id),
            ARRAY_A
        );

        return parent::aesirx_analytics_format_response_utm($row);
    }
}
