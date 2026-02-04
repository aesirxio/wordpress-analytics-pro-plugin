<?php


use AesirxAnalytics\AesirxAnalyticsMysqlHelper;

Class AesirX_Analytics_Get_Datastream_Setting extends AesirxAnalyticsMysqlHelper
{
    function aesirx_analytics_mysql_execute()
    {
        $options = get_option('aesirx_analytics_pro_plugin_setting', []);
        
        return [
            'domain' => $options['datastream_domain'] ?? wp_parse_url( home_url(), PHP_URL_HOST ),
            'utm_currency' => $options['datastream_utm_currency'] ?? "USD",
            'realtime_sync' => $options['datastream_realtime_sync'] ?? "5",
        ];
    }
}
