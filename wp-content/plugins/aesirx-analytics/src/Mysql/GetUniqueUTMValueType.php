<?php

use AesirxAnalytics\AesirxAnalyticsMysqlHelper;

class AesirX_Analytics_Get_Unique_UTM_Value_Type extends AesirxAnalyticsMysqlHelper
{
    public function aesirx_analytics_mysql_execute($params = [])
    {
        global $wpdb;

        $table = $wpdb->prefix . 'analytics_utm';

        $where = [];
        $bind  = [];

        // 1️⃣ Domain filter (filter[domain][0]=aesirx.io)
        if (!empty($params['filter']['domain']) && is_array($params['filter']['domain'])) {
            $domains = array_values(array_filter($params['filter']['domain']));

            if (!empty($domains)) {
                $placeholders = implode(',', array_fill(0, count($domains), '%s'));
                $where[] = "domain IN ($placeholders)";
                $bind = array_merge($bind, $domains);
            }
        }

        // 2️⃣ value_type must exist and not be empty
        $where[] = "value_type IS NOT NULL";
        $where[] = "value_type != ''";

        $where_sql = '';
        if (!empty($where)) {
            $where_sql = 'WHERE ' . implode(' AND ', $where);
        }

        // 3️⃣ Main SQL (distinct like Mongo $group)
        $sql = "
            SELECT DISTINCT value_type
            FROM $table
            $where_sql
            ORDER BY value_type ASC
        ";

        // 4️⃣ Total count SQL
        $total_sql = "
            SELECT COUNT(DISTINCT value_type)
            FROM $table
            $where_sql
        ";

        // 5️⃣ Use shared list helper
        return parent::aesirx_analytics_get_list(
            $sql,
            $total_sql,
            $params,
            ['value_type'],
            $bind
        );
    }
}