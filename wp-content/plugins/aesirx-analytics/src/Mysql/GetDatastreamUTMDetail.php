<?php


use AesirxAnalytics\AesirxAnalyticsMysqlHelper;

Class AesirX_Analytics_Get_Datastream_UTM_Detail extends AesirxAnalyticsMysqlHelper
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

        $table = $wpdb->prefix . 'analytics_utm';

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE id = %s AND domain = %s LIMIT 1",
                $id,
                $domain
            ),
            ARRAY_A
        );

        if (!$row) {
            return new WP_Error(
                'not_found',
                'UTM not found',
                ['status' => 404]
            );
        }

        return parent::aesirx_analytics_format_response_utm($row);
    }
}
