<?php


use AesirxAnalytics\AesirxAnalyticsMysqlHelper;

Class AesirX_Analytics_Get_Attribute_Value_UTM extends AesirxAnalyticsMysqlHelper
{
    function aesirx_analytics_mysql_execute($params = [])
    {
        global $wpdb;

        $where_clause = [];
        $bind = [];

        parent::aesirx_analytics_add_filters($params, $where_clause, $bind);
        parent::aesirx_analytics_add_attribute_filters($params, $where_clause, $bind);

        $total_sql =
            "SELECT COUNT(DISTINCT {$wpdb->prefix}analytics_event_attributes.name) as total
            from {$wpdb->prefix}analytics_event_attributes
            left join {$wpdb->prefix}analytics_events on {$wpdb->prefix}analytics_event_attributes.event_uuid = {$wpdb->prefix}analytics_events.uuid
            left join {$wpdb->prefix}analytics_visitors on {$wpdb->prefix}analytics_visitors.uuid = {$wpdb->prefix}analytics_events.visitor_uuid
            WHERE " . implode(" AND ", $where_clause);

        $sql =
            "SELECT DISTINCT {$wpdb->prefix}analytics_event_attributes.name
            from {$wpdb->prefix}analytics_event_attributes
            left join {$wpdb->prefix}analytics_events on {$wpdb->prefix}analytics_event_attributes.event_uuid = {$wpdb->prefix}analytics_events.uuid
            left join {$wpdb->prefix}analytics_visitors on {$wpdb->prefix}analytics_visitors.uuid = {$wpdb->prefix}analytics_events.visitor_uuid
            WHERE " . implode(" AND ", $where_clause);

        $sort = self::aesirx_analytics_add_sort($params, ["name"], "name");

        if (!empty($sort)) {
            $sql .= " ORDER BY " . implode(", ", $sort);
        }

        $list_response = parent::aesirx_analytics_get_list($sql, $total_sql, $params, [], $bind);

        if (is_wp_error($list_response)) {
            return $list_response;
        }

        $list = $list_response['collection'];

        if (!$list) {
            return array_merge($list_response, ['collection' => []]);
        }

         // --------------------------------------------------
        // Fetch UTM aggregated values (Rust pipeline core)
        // --------------------------------------------------
        $names = array_column($list, 'name');
        $placeholders = implode(',', array_fill(0, count($names), '%s'));

        $utm_sql = "
            SELECT
                a.name,
                a.value AS utm_campaign,

                u.campaign_label,
                u.utm_source,
                IFNULL(u.value, 0) AS value,
                IFNULL(u.engagement_weight, 0) AS engagement_weight,

                COUNT(
                    CASE
                        WHEN e.event_name = u.value_type THEN 1
                        ELSE NULL
                    END
                ) AS count,

                COUNT(DISTINCT e.visitor_uuid) AS number_of_visitors,
                COUNT(e.uuid) AS total_events

            FROM {$wpdb->prefix}analytics_event_attributes a
            LEFT JOIN {$wpdb->prefix}analytics_events e ON e.uuid = a.event_uuid
            LEFT JOIN {$wpdb->prefix}analytics_visitors v ON v.uuid = e.visitor_uuid

            LEFT JOIN {$wpdb->prefix}analytics_utm u
                ON u.id = (
                    SELECT uu.id
                    FROM {$wpdb->prefix}analytics_utm uu
                    WHERE uu.utm_campaign = a.value
                      AND uu.publish = 1
                    ORDER BY uu.id DESC
                    LIMIT 1
                )

            WHERE a.name IN ($placeholders)
            GROUP BY a.name, a.value
            HAVING campaign_label IS NOT NULL
        ";

        $rows = $wpdb->get_results(
            $wpdb->prepare($utm_sql, $names),
            ARRAY_A
        );

        // --------------------------------------------------
        // Group values by attribute name
        // --------------------------------------------------
        $hash = [];

        foreach ($rows as $r) {
            $hash[$r['name']][] = [
                'campaign_label'      => $r['campaign_label'],
                'utm_campaign'        => $r['utm_campaign'],
                'utm_source'          => $r['utm_source'],
                'count'               => (int)$r['count'],
                'number_of_visitors'  => (int)$r['number_of_visitors'],
                'value'               => (int)$r['value'],
                'total_value'         => (int)$r['value'] * (int)$r['count'],
                'engagement_weight'   => (int)$r['engagement_weight'],
                'engagement_score'    => (int)$r['engagement_weight'] * (int)$r['total_events'],
            ];
        }

        // --------------------------------------------------
        // Final response (same as Rust API)
        // --------------------------------------------------
        $collection = [];

        foreach ($list as $row) {
            $collection[] = [
                'name'   => $row['name'],
                'values' => $hash[$row['name']] ?? [],
            ];
        }

        return [
            'collection'     => $collection,
            'page'           => $list_response['page'],
            'page_size'      => $list_response['page_size'],
            'total_pages'    => $list_response['total_pages'],
            'total_elements' => $list_response['total_elements'],
        ];
    }
}
