<?php


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

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE domain = %s ORDER BY created_at DESC",
                $domain
            ),
            ARRAY_A
        );

        if (empty($rows)) {
            return new WP_Error(
                'not_found',
                'No UTM links found',
                ['status' => 404]
            );
        }

        $result = array_map(function ($row) {
            return parent::aesirx_analytics_format_response_utm($row);
        }, $rows);

        return $result;
    }
}
