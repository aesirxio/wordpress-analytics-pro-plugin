<?php

use AesirxAnalytics\AesirxAnalyticsMysqlHelper;

class AesirX_Analytics_Get_Unique_UTM_Links extends AesirxAnalyticsMysqlHelper
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
        $where[] = "a.id IS NOT NULL";
        $where[] = "e.url IS NOT NULL";
        $where[] = "e.url != ''";

        // --------------------------------------------------
        // DOMAIN FILTER
        // --------------------------------------------------
        if (!empty($params['filter']['domain'])) {
            $domains = (array) $params['filter']['domain'];
            $placeholders = implode(',', array_fill(0, count($domains), '%s'));

            $where[] = "
                SUBSTRING_INDEX(
                    SUBSTRING_INDEX(
                        SUBSTRING_INDEX(
                            REPLACE(e.url, '&amp;', '&'),
                            '?',
                            1
                        ),
                        '/',
                        3
                    ),
                    '/',
                    -1
                ) IN ($placeholders)
            ";

            foreach ($domains as $domain) {
                $bind[] = $domain;
            }
        }

        // --------------------------------------------------
        // SEARCH FILTER
        // --------------------------------------------------
        if (!empty($params['filter']['search'][0])) {
            $search = $params['filter']['search'][0];

            $where[] = "
                LOWER(
                    REPLACE(
                        REPLACE(e.url, '&amp;', '&'),
                        '%%2F',
                        '/'
                    )
                ) LIKE %s
            ";

            $bind[] = '%' . strtolower($search) . '%';
        }

        $where_sql = implode(' AND ', $where);

        // --------------------------------------------------
        // TOTAL QUERY
        // --------------------------------------------------
       $total_sql = "
            SELECT COUNT(*) AS total
            FROM (
                SELECT
                    SUBSTRING_INDEX(
                        REPLACE(e.url, '&amp;', '&'),
                        '?',
                        -1
                    ) AS link
                FROM {$wpdb->prefix}analytics_events e
                LEFT JOIN {$wpdb->prefix}analytics_event_attributes a
                    ON a.event_uuid = e.uuid
                WHERE {$where_sql}
                GROUP BY link
            ) t
            LEFT JOIN {$wpdb->prefix}analytics_utm u
                ON u.link = t.link
            WHERE u.id IS NULL
        ";

        // --------------------------------------------------
        // MAIN QUERY
        // --------------------------------------------------
        $sql = "
            SELECT
                MIN(REPLACE(e.url, '&amp;', '&')) AS link
            FROM {$wpdb->prefix}analytics_events e
            LEFT JOIN {$wpdb->prefix}analytics_event_attributes a
                ON a.event_uuid = e.uuid
            LEFT JOIN {$wpdb->prefix}analytics_utm u
                ON u.link = SUBSTRING_INDEX(
                    REPLACE(e.url, '&amp;', '&'),
                    '?',
                    -1
                )
            WHERE {$where_sql}
            AND u.id IS NULL
            GROUP BY
                SUBSTRING_INDEX(
                    REPLACE(e.url, '&amp;', '&'),
                    '?',
                    -1
                )
        ";
        // Sorting
        $sort = self::aesirx_analytics_add_sort($params, ['link'], 'link');
        if ($sort) {
            $sql .= ' ORDER BY ' . implode(', ', $sort);
        }
        // --------------------------------------------------
        // Execute
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
                fn ($r) => ['link' => $r['link']],
                $list_response['collection'] ?? []
            ),
            'page'           => $list_response['page'],
            'page_size'      => $list_response['page_size'],
            'total_pages'    => $list_response['total_pages'],
            'total_elements' => $list_response['total_elements'],
        ];
    }
}