<?php

use AesirxAnalytics\AesirxAnalyticsMysqlHelper;

class AesirX_Analytics_Get_Unique_Event_Names extends AesirxAnalyticsMysqlHelper
{
    public function aesirx_analytics_mysql_execute($params = [])
    {
        global $wpdb;

        /**
         * --------------------------------------------------
         * Normalize params WITHOUT removing numeric keys
         * --------------------------------------------------
         */

        // Normalize search to array
        if (isset($params['filter']['search']) && !is_array($params['filter']['search'])) {
            $params['filter']['search'] = [$params['filter']['search']];
        }

        $where = [];
        $bind  = [];

        // --------------------------------------------------
        // Base constraints
        // --------------------------------------------------
        $where[] = "e.event_name IS NOT NULL";
        $where[] = "e.event_name != ''";
        $where[] = "e.event_type IS NOT NULL";
        $where[] = "e.event_type != ''";

        // --------------------------------------------------
        // JOIN visitor (domain)
        // --------------------------------------------------
        $from = "
            {$wpdb->prefix}analytics_events e
            LEFT JOIN {$wpdb->prefix}analytics_visitors v
                ON v.uuid = e.visitor_uuid
        ";

        // --------------------------------------------------
        // FILTER: domain
        // --------------------------------------------------
        if (!empty($params['filter']['domain'])) {
            $domains = (array) $params['filter']['domain'];
            $placeholders = implode(',', array_fill(0, count($domains), '%s'));

            $where[] = "v.domain IN ($placeholders)";
            foreach ($domains as $d) {
                $bind[] = $d;
            }
        }

        // --------------------------------------------------
        // FILTER NOT: event_name
        // --------------------------------------------------
        if (!empty($params['filter_not']['event_name'])) {
            $list = (array) $params['filter_not']['event_name'];
            $placeholders = implode(',', array_fill(0, count($list), '%s'));

            $where[] = "e.event_name NOT IN ($placeholders)";
            foreach ($list as $v) {
                $bind[] = $v;
            }
        }

        // --------------------------------------------------
        // FILTER: event_name
        // --------------------------------------------------
        if (!empty($params['filter']['event_name'])) {
            $list = (array) $params['filter']['event_name'];
            $placeholders = implode(',', array_fill(0, count($list), '%s'));

            $where[] = "e.event_name IN ($placeholders)";
            foreach ($list as $v) {
                $bind[] = $v;
            }
        }

        // --------------------------------------------------
        // FILTER: event_type
        // --------------------------------------------------
        if (!empty($params['filter']['event_type'])) {
            $list = (array) $params['filter']['event_type'];
            $placeholders = implode(',', array_fill(0, count($list), '%s'));

            $where[] = "e.event_type IN ($placeholders)";
            foreach ($list as $v) {
                $bind[] = $v;
            }
        }

        // --------------------------------------------------
        // SEARCH (event_name OR event_type)
        // --------------------------------------------------
        if (!empty($params['filter']['search'][0])) {
            $search = strtolower($params['filter']['search'][0]);

            $where[] = "
                (
                    LOWER(e.event_name) LIKE %s
                    OR LOWER(e.event_type) LIKE %s
                )
            ";

            $bind[] = '%' . $search . '%';
            $bind[] = '%' . $search . '%';
        }

        $where_sql = implode(' AND ', $where);

        // --------------------------------------------------
        // TOTAL QUERY (exclude tagged events)
        // --------------------------------------------------
        $total_sql = "
            SELECT COUNT(*) FROM (
                SELECT
                    e.event_name,
                    e.event_type
                FROM {$from}
                LEFT JOIN {$wpdb->prefix}analytics_tag_event t
                    ON t.event_name = e.event_name
                   AND t.domain = v.domain
                WHERE {$where_sql}
                  AND t.id IS NULL
                GROUP BY e.event_name, e.event_type
            ) x
        ";

        // --------------------------------------------------
        // MAIN QUERY
        // --------------------------------------------------
        $sql = "
            SELECT
                e.event_name,
                e.event_type
            FROM {$from}
            LEFT JOIN {$wpdb->prefix}analytics_tag_event t
                ON t.event_name = e.event_name
               AND t.domain = v.domain
            WHERE {$where_sql}
              AND t.id IS NULL
            GROUP BY
                e.event_name,
                e.event_type
        ";

        // --------------------------------------------------
        // SORT
        // --------------------------------------------------
        $sort = self::aesirx_analytics_add_sort(
            $params,
            ['event_name', 'event_type', 'domain'],
            'event_name'
        );

        if ($sort) {
            $sql .= ' ORDER BY ' . implode(', ', $sort);
        }

        // --------------------------------------------------
        // EXECUTE
        // --------------------------------------------------
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

        return [
            'collection'     => array_map(
                fn ($r) => [
                    'event_name' => $r['event_name'],
                    'event_type' => $r['event_type'],
                ],
                $list_response['collection'] ?? []
            ),
            'page'           => $list_response['page'],
            'page_size'      => $list_response['page_size'],
            'total_pages'    => $list_response['total_pages'],
            'total_elements' => $list_response['total_elements'],
        ];
    }
}