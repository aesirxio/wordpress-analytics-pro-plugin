<?php

use AesirxAnalytics\AesirxAnalyticsMysqlHelper;

Class AesirX_Analytics_Get_Live_Visitors_Total extends AesirxAnalyticsMysqlHelper
{
    function aesirx_analytics_mysql_execute($params = [])
    {
        global $wpdb;

        $realtime_sync_minutes = 5;

        if (!empty($params['filter']['domain'])) {
            $options = get_option('aesirx_analytics_pro_plugin_setting', []);
            if (!empty($options['datastream_realtime_sync'])) {
                $realtime_sync_minutes = max(
                    5,
                    (int) $options['datastream_realtime_sync']
                );
            }
        }
        $where_clause = [
            "#__analytics_flows.end >= NOW() - INTERVAL %d MINUTE",
            "#__analytics_visitors.device != 'bot'"
        ];
        $bind = [$realtime_sync_minutes];

        unset($params["filter"]["start"]);
        unset($params["filter"]["end"]);

        parent::aesirx_analytics_add_filters($params, $where_clause, $bind);

        $sql =
            "SELECT COUNT(DISTINCT `#__analytics_flows`.`visitor_uuid`) as total
            from `#__analytics_flows`
            left join `#__analytics_visitors` on `#__analytics_visitors`.`uuid` = `#__analytics_flows`.`visitor_uuid`
            WHERE " . implode(" AND ", $where_clause);

        $sql = str_replace("#__", $wpdb->prefix, $sql);

        // used placeholders and $wpdb->prepare() in variable $sql
        // doing direct database calls to custom tables
        $total = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare($sql, $bind) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        );
        
        return [
            "total" => $total
        ];
    }
}
