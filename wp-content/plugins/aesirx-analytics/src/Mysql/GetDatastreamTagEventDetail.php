<?php

if ( ! defined( 'ABSPATH' ) ) exit;
use AesirxAnalytics\AesirxAnalyticsMysqlHelper;

Class AesirX_Analytics_Get_Datastream_Tag_Event_Detail extends AesirxAnalyticsMysqlHelper
{
    function aesirx_analytics_mysql_execute($params = [])
    {
         global $wpdb;

        $domain = $params[2] ?? '';
        $id = $params[3] ?? '';

        if (empty($domain) || empty($id)) {
            return new WP_Error(
                'validation_error',
                'Missing domain or id',
                ['status' => 400]
            );
        }

        $table = $wpdb->prefix . 'analytics_tag_event';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $row = $wpdb->get_row(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT * FROM $table WHERE id = %s AND domain = %s LIMIT 1",
                $id,
                $domain
            ),
            ARRAY_A
        );

        if (!$row) {
            return new WP_Error(
                'not_found',
                'Tag Event not found',
                ['status' => 404]
            );
        }

        return parent::aesirx_analytics_format_response_tag_event($row);
    }
}
