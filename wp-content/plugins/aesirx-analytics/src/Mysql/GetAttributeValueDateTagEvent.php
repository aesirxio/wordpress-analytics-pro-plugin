<?php

if ( ! defined( 'ABSPATH' ) ) exit;
use AesirxAnalytics\AesirxAnalyticsMysqlHelper;

Class AesirX_Analytics_Get_Attribute_Value_Date_Tag_Event extends AesirxAnalyticsMysqlHelper
{
    function aesirx_analytics_mysql_execute($params = [])
    {
        global $wpdb;
        $where_clause = [];
        $bind = [];
     

        parent::aesirx_analytics_add_filters($params, $where_clause, $bind);
        parent::aesirx_analytics_add_attribute_filters($params, $where_clause, $bind);
        // --------------------------------------------------
        // 1. TOTAL (distinct date)
        // --------------------------------------------------
        $total_sql = "
            SELECT COUNT(*) FROM (
                SELECT DATE_FORMAT({$wpdb->prefix}analytics_events.start, '%%Y-%%m-%%d') AS date
                FROM {$wpdb->prefix}analytics_events
                LEFT JOIN {$wpdb->prefix}analytics_visitors
                    ON {$wpdb->prefix}analytics_visitors.uuid = {$wpdb->prefix}analytics_events.visitor_uuid
                WHERE " . implode(' AND ', $where_clause) . "
                GROUP BY date
            ) t
        ";

        // --------------------------------------------------
        // 2. BASE LIST (one row per date)
        // --------------------------------------------------
        $sql = "
            SELECT
                DATE_FORMAT({$wpdb->prefix}analytics_events.start, '%%Y-%%m-%%d') AS date
            FROM {$wpdb->prefix}analytics_events
            LEFT JOIN {$wpdb->prefix}analytics_visitors
                ON {$wpdb->prefix}analytics_visitors.uuid = {$wpdb->prefix}analytics_events.visitor_uuid
            WHERE " . implode(' AND ', $where_clause) . "
            GROUP BY date
        ";

        $sort = self::aesirx_analytics_add_sort($params, ['date'], 'date');
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

        $list = $list_response['collection'];
        if (!$list) {
            return array_merge($list_response, ['collection' => []]);
        }

        // --------------------------------------------------
        // 3. TAG EVENT AGGREGATION (enrichment query)
        // --------------------------------------------------
        $tag_where = [];

        foreach ($where_clause as $w) {
            $w = str_replace(
                [
                    "{$wpdb->prefix}analytics_events.",
                    "{$wpdb->prefix}analytics_visitors.",
                    "#__analytics_events.",
                    "#__analytics_visitors.",
                ],
                [
                    "e.",
                    "v.",
                    "e.",
                    "v.",
                ],
                $w
            );

            $tag_where[] = $w;
        }

        $tag_sql = "
            SELECT
                DATE_FORMAT(e.start, '%%Y-%%m-%%d') AS date,
                e.event_name,

                COUNT(*) AS count,
                COUNT(*) AS count_engagement,

                IFNULL(t.metric_value, 0) AS metric_value,
                IFNULL(t.engagement_value, 0) AS engagement_value

            FROM {$wpdb->prefix}analytics_events e
            LEFT JOIN {$wpdb->prefix}analytics_visitors v
                ON v.uuid = e.visitor_uuid

            LEFT JOIN {$wpdb->prefix}analytics_tag_event t
                ON t.id = (
                    SELECT tt.id
                    FROM {$wpdb->prefix}analytics_tag_event tt
                    WHERE tt.event_name = e.event_name
                      AND tt.domain = v.domain
                      AND tt.publish = 1
                    ORDER BY tt.id DESC
                    LIMIT 1
                )

            WHERE " . implode(' AND ', $tag_where) . "
            GROUP BY date, e.event_name
            HAVING metric_value > 0 OR engagement_value > 0
        ";
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $rows = $wpdb->get_results(
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $wpdb->prepare( $tag_sql, $bind ),
            ARRAY_A
        );

        // --------------------------------------------------
        // 4. Hash by date
        // --------------------------------------------------
        $hash = [];

        foreach ($rows as $r) {
            $hash[$r['date']][] = (object)[
                'event_name'        => $r['event_name'],
                'count'             => (int) $r['count'],
                'count_engagement'  => (int) $r['count_engagement'],
                'metric_value'      => (int) $r['metric_value'],
                'total_value'       => (int) $r['count'] * (int) $r['metric_value'],
                'engagement_value'  => (int) $r['engagement_value'],
                'engagement_score'  => (int) $r['count_engagement'] * (int) $r['engagement_value'],
            ];
        }

        // --------------------------------------------------
        // 5. Final merge (preserve empty values)
        // --------------------------------------------------
        $collection = [];

        foreach ($list as $row) {
            $collection[] = (object)[
                'date'   => $row['date'],
                'values' => $hash[$row['date']] ?? []
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
