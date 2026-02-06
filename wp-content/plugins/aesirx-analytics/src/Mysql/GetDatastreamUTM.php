<?php

if ( ! defined( 'ABSPATH' ) ) exit;
use AesirxAnalytics\AesirxAnalyticsMysqlHelper;

Class AesirX_Analytics_Get_Datastream_UTM extends AesirxAnalyticsMysqlHelper
{
    function aesirx_analytics_mysql_execute($params = [])
    {
         global $wpdb;

        $domain = $params[2] ?? '';

        if (empty($domain)) {
            return new WP_Error(
                'validation_error',
                'Missing domain',
                ['status' => 400]
            );
        }

        $table = $wpdb->prefix . 'analytics_utm';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT * FROM $table WHERE domain = %s ORDER BY created_at DESC",
                $domain
            ),
            ARRAY_A
        );

        if (empty($rows)) {
            return [];
        }

        $result = array_map(function ($row) {
            return parent::aesirx_analytics_format_response_utm($row);
        }, $rows);

        return $result;
    }
}
