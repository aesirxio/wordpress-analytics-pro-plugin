<?php

if ( ! defined( 'ABSPATH' ) ) exit;
use AesirxAnalytics\AesirxAnalyticsMysqlHelper;

Class AesirX_Analytics_Store_Datastream_Setting extends AesirxAnalyticsMysqlHelper
{
    function aesirx_analytics_mysql_execute($params = [])
    {
        $response = [];
        $options = get_option('aesirx_analytics_pro_plugin_setting', []);
        foreach ($params as $key => $value) {
            if (is_string($key)) {
                $new_value = sanitize_text_field($value);
                $options['datastream_' . $key] = $new_value;
                $response[$key] = $new_value;
            }
        }

        update_option('aesirx_analytics_pro_plugin_setting', $options);

        return $response;
    }
}
