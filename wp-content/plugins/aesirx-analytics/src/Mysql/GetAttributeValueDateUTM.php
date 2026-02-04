<?php


use AesirxAnalytics\AesirxAnalyticsMysqlHelper;

Class AesirX_Analytics_Get_Attribute_Value_Date_UTM extends AesirxAnalyticsMysqlHelper
{
    function aesirx_analytics_mysql_execute($params = [])
    {
        global $wpdb;
        $where_clause = [];
        $bind = [];
     

        parent::aesirx_analytics_add_filters($params, $where_clause, $bind);
        parent::aesirx_analytics_add_attribute_filters($params, $where_clause, $bind);
        // 1. TOTAL (distinct attribute + date)
        // ------------------------------------------------------------------
        $total_sql =
            "SELECT COUNT(DISTINCT {$wpdb->prefix}analytics_event_attributes.name, DATE_FORMAT({$wpdb->prefix}analytics_events.start, '%%Y-%%m-%%d')) as total
            from `{$wpdb->prefix}analytics_event_attributes`
            left join `{$wpdb->prefix}analytics_events` on {$wpdb->prefix}analytics_event_attributes.event_uuid = {$wpdb->prefix}analytics_events.uuid
            left join `{$wpdb->prefix}analytics_visitors` on {$wpdb->prefix}analytics_visitors.uuid = {$wpdb->prefix}analytics_events.visitor_uuid
            WHERE " . implode(" AND ", $where_clause);

        // ------------------------------------------------------------------
        // 2. BASE LIST (attribute + date)
        // ------------------------------------------------------------------
        $sql =
            "SELECT
                {$wpdb->prefix}analytics_event_attributes.name,
                DATE_FORMAT({$wpdb->prefix}analytics_events.start, '%%Y-%%m-%%d') as date
            FROM {$wpdb->prefix}analytics_event_attributes
            LEFT JOIN {$wpdb->prefix}analytics_events ON {$wpdb->prefix}analytics_event_attributes.event_uuid = {$wpdb->prefix}analytics_events.uuid
            LEFT JOIN {$wpdb->prefix}analytics_visitors ON {$wpdb->prefix}analytics_visitors.uuid = {$wpdb->prefix}analytics_events.visitor_uuid 
            WHERE " . implode(" AND ", $where_clause) .
            " GROUP BY {$wpdb->prefix}analytics_event_attributes.name, date";

        $sort = self::aesirx_analytics_add_sort($params, ['name','date'], 'date');
        if ($sort) {
            $sql .= ' ORDER BY ' . implode(', ', $sort);
        }

        $list_response = parent::aesirx_analytics_get_list(
            $sql,
            $total_sql,
            $params,
            [],
            $bind
        );

        if (is_wp_error($list_response)) {
            return $list_response;
        }

        $collection = [];
        $list = $list_response['collection'];

        if (!$list) {
            return array_merge($list_response, ['collection' => []]);
        }

        // ------------------------------------------------------------------
        // 3. Fetch UTM aggregated values
        // ------------------------------------------------------------------
        $names = array_unique(array_column($list, 'name'));
        $placeholders = implode(',', array_fill(0, count($names), '%s'));

        $utm_sql = "
            SELECT
                DATE_FORMAT(e.start,'%%Y-%%m-%%d') AS date,
                a.name,
                a.value AS utm_campaign,

                u.campaign_label,
                COUNT(
                    CASE
                        WHEN e.event_name = u.value_type THEN 1
                        ELSE NULL
                    END
                ) AS count,

                COUNT(a.id) AS count_engagement,

                IFNULL(u.value,0) AS value,
                IFNULL(u.engagement_weight,0) AS engagement_weight

            FROM {$wpdb->prefix}analytics_event_attributes a
            LEFT JOIN {$wpdb->prefix}analytics_events e ON a.event_uuid = e.uuid
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
            GROUP BY date, a.name, a.value
            HAVING campaign_label IS NOT NULL
        ";

        $rows = $wpdb->get_results(
            $wpdb->prepare($utm_sql, $names),
            ARRAY_A
        );

        // ------------------------------------------------------------------
        // 4. Hash by date + name
        // ------------------------------------------------------------------
        $hash = [];

        foreach ($rows as $r) {
            $key = $r['date'] . '||' . $r['name'];

            $hash[$key][] = (object)[
                'utm_campaign'     => $r['utm_campaign'],
                'campaign_label'   => $r['campaign_label'],
                'count'            => (int)$r['count'],
                'count_engagement' => (int)$r['count_engagement'],
                'value'            => (int)$r['value'],
                'total_value'      => (int)$r['count'] * (int)$r['value'],
                'engagement_weight'=> (int)$r['engagement_weight'],
                'engagement_score' => (int)$r['count_engagement'] * (int)$r['engagement_weight'],
            ];
        }

        // ------------------------------------------------------------------
        // 5. Final merge (keep empty values like Rust)
        // ------------------------------------------------------------------
        foreach ($list as $row) {
            $key = $row['date'] . '||' . $row['name'];

            $collection[] = (object)[
                'date'   => $row['date'],
                'name'   => $row['name'],
                'values' => $hash[$key] ?? []
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
