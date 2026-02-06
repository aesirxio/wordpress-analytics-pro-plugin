<?php
if ( ! defined( 'ABSPATH' ) ) exit;
use AesirxAnalytics\AesirxAnalyticsMysqlHelper;

Class AesirX_Analytics_Get_Live_Visitors_List extends AesirxAnalyticsMysqlHelper
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

        // filters where clause for events

        $total_sql =
            "SELECT COUNT(DISTINCT #__analytics_flows.uuid) as total
            from `#__analytics_flows`
            left join `#__analytics_visitors` on #__analytics_visitors.uuid = #__analytics_flows.visitor_uuid
            left join `#__analytics_events` on #__analytics_events.flow_uuid = #__analytics_flows.uuid
            WHERE " . implode(" AND ", $where_clause) .
            " GROUP BY `#__analytics_flows`.`visitor_uuid`";

        $sql =
            "SELECT #__analytics_flows.*, ip, user_agent, device, browser_name, browser_name, browser_version, domain, lang, city, isp, country_name, country_code, geo_created_at, #__analytics_visitors.uuid AS visitor_uuid,
            MAX(CASE WHEN #__analytics_event_attributes.name = 'sop_id' THEN #__analytics_event_attributes.value ELSE NULL END) AS sop_id, 
            e.url AS url
            from `#__analytics_flows`
            JOIN (
                SELECT visitor_uuid, MAX(`end`) AS last_end
                FROM `#__analytics_flows`
                GROUP BY visitor_uuid
            ) latest
            ON latest.visitor_uuid = `#__analytics_flows`.visitor_uuid
            AND latest.last_end = `#__analytics_flows`.`end`
            left join `#__analytics_visitors` on #__analytics_visitors.uuid = #__analytics_flows.visitor_uuid
            left join `wp_analytics_events` e
            ON e.uuid = (
                SELECT e2.uuid
                FROM `wp_analytics_events` e2
                WHERE e2.flow_uuid = wp_analytics_flows.uuid
                ORDER BY e2.`end` DESC
                LIMIT 1
            )
            left join `#__analytics_event_attributes` on e.uuid = #__analytics_event_attributes.event_uuid
            WHERE " . implode(" AND ", $where_clause) .
            " GROUP BY `#__analytics_flows`.`visitor_uuid`";

        $sort = parent::aesirx_analytics_add_sort(
            $params,
            [
                "start",
                "end",
                "geo.country.name",
                "geo.country.code",
                "ip",
                "device",
                "browser_name",
                "browser_version",
                "domain",
                "lang",
                "url",
                "sop_id",
            ],
            "start",
        );

        if (!empty($sort)) {
            $sql .= " ORDER BY " . implode(", ", $sort);
        }

        $list_response = parent::aesirx_analytics_get_list($sql, $total_sql, $params, [], $bind);

        if (is_wp_error($list_response)) {
            return $list_response;
        }

        $list = $list_response['collection'];

        if (empty($list)) {
            return [
                'collection' => [],
                'page' => 1,
                'page_size' => 1,
                'total_pages' => 1,
                'total_elements' => 0,
            ];
        }

        $collection = [];

        $ret = [];
        $dirs = [];

        $bind = array_map(function($e) {
            return $e['uuid'];
        }, $list);

        // doing direct database calls to custom tables
        // placeholders depends one number of $bind
        $events = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}analytics_events WHERE flow_uuid IN (" . implode(', ', array_fill(0, count($bind), '%s')) . ")", 
                ...$bind
            )
        );

        // doing direct database calls to custom tables
        // placeholders depends one number of $bind
        $attributes = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}analytics_event_attributes 
                LEFT JOIN {$wpdb->prefix}analytics_events
                ON {$wpdb->prefix}analytics_events.uuid = {$wpdb->prefix}analytics_event_attributes.event_uuid 
                WHERE {$wpdb->prefix}analytics_events.flow_uuid IN (" . implode(', ', array_fill(0, count($bind), '%s')) . ")",
                ...$bind
            )
        ); 
        
        $hash_attributes = [];
        
        // --------------------------------------------------
        // tag_event data
        // --------------------------------------------------
        $tag_events = $wpdb->get_results( // phpcs:ignore
            "SELECT event_name, domain, metric_value
            FROM {$wpdb->prefix}analytics_tag_event
            WHERE publish = 1"
        );

        $tag_metric_map = [];

        foreach ($tag_events as $tag) {
            $key = $tag->event_name;
            $tag_metric_map[$key] = (int) $tag->metric_value;
        }

        // --------------------------------------------------
        // utm data
        // --------------------------------------------------
        $utm_rules = $wpdb->get_results( // phpcs:ignore
            "SELECT link, domain, value, value_type, campaign_label
            FROM {$wpdb->prefix}analytics_utm
            WHERE publish = 1"
        );

        $utm_map = [];
        foreach ($utm_rules as $utm) {
            $normalized_link = $utm->link;
            $key = $normalized_link;

            $utm_map[$key] = [
                'value' => (int) $utm->value,
                'value_type' => $utm->value_type,
                'campaign_label' => $utm->campaign_label,
            ];
        }

        foreach ($attributes as $second) {
            $attr = (object)[
                'name' => $second->name,
                'value' => $second->value,
            ];
            if (!isset($hash_attributes[$second->event_uuid])) {
                $hash_attributes[$second->event_uuid] = [$attr];
            } else {
                $hash_attributes[$second->event_uuid][] = $attr;
            }
        }

        $hash_map = [];

        foreach ($events as $second) {
            // --------------------------------------------------
            // calculate tag_metric_value
            // --------------------------------------------------
            $tag_metric_value = 0;
            $tag_key = $second->event_name;
            $tag_metric_value = $tag_metric_map[$tag_key] ?? 0;

            // --------------------------------------------------
            // calculate utm_value and utm_campaign_label
            // --------------------------------------------------
            $utm_value = 0;
            $utm_campaign_label = null;
            $normalized_url = str_replace('/?', '?', $second->url);
            $utm_key = $normalized_url;

            if (isset($utm_map[$utm_key])) {
                $utm_rule = $utm_map[$utm_key];

                // match value_type with event_name (same as Rust)
                if ($utm_rule['value_type'] === $second->event_name) {
                    $utm_value = $utm_rule['value'];
                }

                // campaign label logic (same priority as Rust)
                if (!empty($hash_attributes[$second->uuid])) {
                    foreach ($hash_attributes[$second->uuid] as $attr) {
                        if ($attr->name === 'utm_campaign' && $attr->value !== '') {
                            $utm_campaign_label = $attr->value;
                            break;
                        }
                    }
                }

                if ($utm_campaign_label === null) {
                    $utm_campaign_label = $utm_rule['campaign_label'];
                }
            }

            $visitor_event = [
                'uuid' => $second->uuid,
                'visitor_uuid' => $second->visitor_uuid,
                'flow_uuid' => $second->flow_uuid,
                'url' => $second->url,
                'referer' => $second->referer,
                'start' => $second->start,
                'end' => $second->end,
                'event_name' => $second->event_name,
                'event_type' => $second->event_type,
                'attributes' => $hash_attributes[$second->uuid] ?? [],
                'utm_value' => $utm_value,
                'tag_metric_value' => $tag_metric_value,
                'utm_campaign_label' => $utm_campaign_label,
            ];

            if (!isset($hash_map[$second->flow_uuid])) {
                $hash_map[$second->flow_uuid] = [$second->uuid => $visitor_event];
            } else {
                $hash_map[$second->flow_uuid][$second->uuid] = $visitor_event;
            }
        }
        foreach ($list as $item) {
            $item = (object) $item;
            
            if (!empty($collection) && end($collection)['uuid'] === $item->uuid) {
                continue;
            }

            $geo = isset($item->geo_created_at) ? (object)[
                'country' => (object)[
                    'name' => $item->country_name,
                    'code' => $item->country_code,
                ],
                'city' => $item->city,
                'isp' => $item->isp,
                'created_at' => $item->geo_created_at,
            ] : null;

            $events = isset($hash_map[$item->uuid]) ? array_values($hash_map[$item->uuid]) : null;

            $collection[] = [
                'uuid' => $item->uuid,
                'flow_uuid' => $item->uuid,
                'visitor_uuid' => $item->visitor_uuid,
                'ip' => $item->ip,
                'user_agent' => $item->user_agent,
                'device' => $item->device,
                'browser_name' => $item->browser_name,
                'browser_version' => $item->browser_version,
                'domain' => $item->domain,
                'lang' => $item->lang,
                'start' => $item->start,
                'end' => $item->end,
                'geo' => $geo,
                'events' => $events,
                'url' => $item->url,
                'sop_id' => $item->sop_id,
            ];
        }


        return [
            'collection' => $collection,
            'page' => $list_response['page'],
            'page_size' => $list_response['page_size'],
            'total_pages' => $list_response['total_pages'],
            'total_elements' => $list_response['total_elements'],
        ];
    }
}
