<?php


use AesirxAnalytics\AesirxAnalyticsMysqlHelper;

Class AesirX_Analytics_Store_Datastream_Tag_Event extends AesirxAnalyticsMysqlHelper
{
    function aesirx_analytics_mysql_execute($params = [])
    {
        global $wpdb;
         // 2️⃣ Validate event_name
        if (empty(trim($params['event_name'] ?? ''))) {
            return new WP_Error(
                'validation_error',
                "Missing or empty 'event_name' field",
                ['status' => 400]
            );
        }

        $table = $wpdb->prefix . 'analytics_tag_event';
        $now   = gmdate('Y-m-d H:i:s');
        $id    = $params['_id']['$oid'] ?? null;

        // 3️⃣ Update existing Event Name
        if ($id) {
            $existing = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM $table WHERE id = %s", $id),
                ARRAY_A
            );

            if (!$existing) {
                return new WP_Error(
                    'not_found',
                    'Event not found',
                    ['status' => 404]
                );
            }

            $update_data = [];

            // Fields allowed to update
            $fields = [
                'event_name',
                'domain',
                'metric_value',
                'engagement_value',
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
                $wpdb->update(
                    $table,
                    $update_data,
                    ['id' => $id]
                );
            }

            $row = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM $table WHERE id = %s", $id),
                ARRAY_A
            );

            return parent::aesirx_analytics_format_response_tag_event($row);
        }
        
        // 4️⃣ Prevent duplicate event_name per domain
        $duplicate = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE domain = %s AND event_name = %s",
                $params['domain'],
                $params['event_name']
            )
        );

        if ($duplicate) {
            return new WP_Error(
                'validation_error',
                "Tag Event with this 'event' already exists",
                ['status' => 400]
            );
        }

         // 5️⃣ Insert new Tag Event
        $new_id = wp_generate_uuid4();

        $wpdb->insert($table, [
            'id' => $new_id,
            'event_name' => $params['event_name'],
            'domain' => $params['domain'] ?? '',
            'publish' => !empty($params['publish']),
            'metric_value' => $params['metric_value'] ?? null,
            'engagement_value' => $params['engagement_value'] ?? null,
            'is_generated' => !empty($params['is_generated']),
            'created_at' => $now,
        ]);

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE id = %s", $new_id),
            ARRAY_A
        );

        return parent::aesirx_analytics_format_response_tag_event($row);
    }
}
