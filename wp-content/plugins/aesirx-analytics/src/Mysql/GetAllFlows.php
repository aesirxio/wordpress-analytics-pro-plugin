<?php

use AesirxAnalytics\AesirxAnalyticsMysqlHelper;

Class AesirX_Analytics_Get_All_Flows extends AesirxAnalyticsMysqlHelper
{
    function aesirx_analytics_mysql_execute($params = [])
    {
        global $wpdb;
        $where_clause = [
            // '#__analytics_visitors.ip != ""',
            // '#__analytics_visitors.user_agent != ""',
            // '#__analytics_visitors.device != ""',
            // '#__analytics_visitors.browser_version != ""',
            // '#__analytics_visitors.browser_name != ""',
            // '#__analytics_visitors.lang != ""',
        ];
        $where_clause_event = [];
        $bind = [];
        $bind_event = [];
        $hash_map = [];
        $detail_page = false;
        parent::aesirx_analytics_add_filters($params, $where_clause, $bind);

        if ( isset($params['flow_uuid']) && !empty($params['flow_uuid'])) {
            $where_clause = ["#__analytics_flows.uuid = %s"];
            $bind = [ sanitize_text_field($params['flow_uuid'])];
            $detail_page = true;
        }

        // filters where clause for events

        $total_sql =
            "SELECT COUNT(DISTINCT #__analytics_flows.uuid) as total
            from `#__analytics_flows`
            left join `#__analytics_visitors` on #__analytics_visitors.uuid = #__analytics_flows.visitor_uuid
            left join `#__analytics_events` on #__analytics_events.flow_uuid = #__analytics_flows.uuid
            WHERE " . implode(" AND ", $where_clause);

        $sql =
            "SELECT #__analytics_flows.*, ip, user_agent, device, browser_name, browser_name, browser_version, domain, lang, city, isp, country_name, country_code, geo_created_at, #__analytics_visitors.uuid AS visitor_uuid, 
            COUNT(DISTINCT #__analytics_events.uuid) AS action, 
            CAST(SUM(CASE WHEN #__analytics_events.event_type = 'conversion' THEN 1 ELSE 0 END) as SIGNED) AS conversion, 
            CAST(SUM(CASE WHEN #__analytics_events.event_name = 'visit' THEN 1 ELSE 0 END) as SIGNED) AS pageview, 
            CAST(SUM(CASE WHEN #__analytics_events.event_name != 'visit' THEN 1 ELSE 0 END) as SIGNED) AS event, 
            MAX(CASE WHEN #__analytics_event_attributes.name = 'sop_id' THEN #__analytics_event_attributes.value ELSE NULL END) AS sop_id, 
            TIMESTAMPDIFF(SECOND, #__analytics_flows.start, #__analytics_flows.end) AS duration, 
            #__analytics_events.url AS url, 
            CAST(
                SUM(CASE WHEN #__analytics_events.event_name = 'visit' THEN 1 ELSE 0 END) * 2 +
                SUM(CASE WHEN #__analytics_events.event_name != 'visit' THEN 1 ELSE 0 END) * 5 +
                SUM(CASE WHEN #__analytics_events.event_type = 'conversion' THEN 1 ELSE 0 END) * 10
            as FLOAT) AS ux_percent, 
            CAST(SUM(CASE WHEN #__analytics_events.event_name = 'visit' THEN 1 ELSE 0 END) * 2 as FLOAT) AS visit_actions, 
            CAST(SUM(CASE WHEN #__analytics_events.event_name != 'visit' THEN 1 ELSE 0 END) * 5 as FLOAT) AS event_actions, 
            CAST(SUM(CASE WHEN #__analytics_events.event_type = 'conversion' THEN 1 ELSE 0 END) * 10 as FLOAT) AS conversion_actions 
            from `#__analytics_flows`
            left join `#__analytics_visitors` on #__analytics_visitors.uuid = #__analytics_flows.visitor_uuid
            left join `#__analytics_events` on #__analytics_events.flow_uuid = #__analytics_flows.uuid
            left join `#__analytics_event_attributes` on #__analytics_events.uuid = #__analytics_event_attributes.event_uuid
            WHERE " . implode(" AND ", $where_clause) .
            " GROUP BY #__analytics_flows.uuid";

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
                "action",
                "event",
                "conversion",
                "url",
                "ux_percent",
                "pageview",
                "bounce_rate",
                "sop_id",
                "duration",
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

        $collection = [];

        $ret = [];
        $dirs = [];

        // --------------------------------------------------
        // tag_event data
        // --------------------------------------------------
        $tag_events = $wpdb->get_results( // phpcs:ignore
            "SELECT event_name, domain, metric_value, engagement_value
            FROM {$wpdb->prefix}analytics_tag_event
            WHERE publish = 1"
        );

        $tag_metric_map = [];

        foreach ($tag_events as $tag) {
            $key = $tag->event_name;
            $tag_metric_map[$key] = [
                'metric_value' => (int) $tag->metric_value,
                'engagement_value' => (int) ($tag->engagement_value ?? 0),
            ];
        }

        // --------------------------------------------------
        // utm data
        // --------------------------------------------------
        $utm_rules = $wpdb->get_results( // phpcs:ignore
            "SELECT link, domain, value, value_type, engagement_weight, campaign_label
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
                'engagement_weight' => (int) ($utm->engagement_weight ?? 0),
                'campaign_label' => $utm->campaign_label,
            ];
        }

        if (!empty($list)) {

            if (isset($params['with']) && !empty($params['with']) || isset($params['request']['with']) && !empty($params['request']['with'])) {
                $with = $params['with'] ?? $params['request']['with'];
                if (in_array("events", $with, true)) {
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

                    foreach ($events as $second) {
                        $second->attribute = $hash_attributes[$second->uuid] ?? [];

                        // --------------------------------------------------
                        // calculate tag_metric_value
                        // --------------------------------------------------
                        $tag_metric_value = 0;
                        $tag_engagement_value = 0;
                        $tag_key = $second->event_name;

                        if (isset($tag_metric_map[$tag_key])) {
                            $tag_metric_value = $tag_metric_map[$tag_key]['metric_value'];
                            $tag_engagement_value = $tag_metric_map[$tag_key]['engagement_value'];
                        }

                        // --------------------------------------------------
                        // calculate utm_value and utm_campaign_label
                        // --------------------------------------------------
                        $utm_value = 0;
                        $utm_value_type = null;
                        $utm_engagement_weight = 0;
                        $normalized_url = str_replace('/?', '?', $second->url);
                        $utm_key = $normalized_url;
                        if (isset($utm_map[$utm_key])) {
                            $utm_rule = $utm_map[$utm_key];

                            // match value_type with event_name (same as Rust)
                            if ($utm_rule['value_type'] === $second->event_name) {
                                $utm_value = $utm_rule['value'];
                                $utm_value_type = $utm_rule['value_type'];
                                $utm_engagement_weight = $utm_rule['engagement_weight'];
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
                            'utm_value_type' => $utm_value_type,
                            'utm_engagement_weight' => $utm_engagement_weight,
                            'tag_metric_value' => $tag_metric_value,
                            'tag_engagement_value' => $tag_engagement_value,
                        ];

                        if (!isset($hash_map[$second->flow_uuid])) {
                            $hash_map[$second->flow_uuid] = [$second->uuid => $visitor_event];
                        } else {
                            $hash_map[$second->flow_uuid][$second->uuid] = $visitor_event;
                        }
                    }

                    $urls = [];

                    foreach ($list as $flow) {
                        if (!empty($hash_map[$flow['uuid']])) {
                            foreach ($hash_map[$flow['uuid']] as $event) {
                                if (!empty($event['url']) && filter_var($event['url'], FILTER_VALIDATE_URL)) {
                                    $urls[$event['url']] = true; // dedupe
                                }
                            }
                        }
                    }

                    $urls = array_keys($urls);
                    if ($detail_page === true && !empty($urls)) {
                        $og_map = parent::aesirx_analytics_get_og_map($urls);
                    }
                    $status_map = parent::aesirx_analytics_batch_head_requests($urls);
                    foreach ($hash_map as $flow_uuid => &$events) {
                        $bad_count = 0;

                        foreach ($events as &$event) {
                            $url = $event['url'] ?? null;
                            $status = ($url && isset($status_map[$url]))
                                    ? $status_map[$url]
                                    : 200;
                            $event['status_code'] = $status;

                            if ($status >= 400) {
                                $bad_count++;
                            }
                            if ($detail_page === true) {
                                $og = ($url && isset($og_map[$url])) ? $og_map[$url] : [];
                                $event['og_title'] = $og['og:title'] ?? null;
                                $event['og_description'] = $og['og:description'] ?? null;
                                $event['og_image'] = $og['og:image'] ?? null;
                            }
                        }

                        $flow_bad_user[$flow_uuid] = $bad_count > 1;
                    }

                    if (!empty($events) && $params[1] === "flow") {
                        $first = reset($events);
                        if ($first['start'] === $first['end']) {
                            $consents = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                                $wpdb->prepare(
                                    "SELECT * FROM {$wpdb->prefix}analytics_visitor_consent WHERE visitor_uuid = %s AND UNIX_TIMESTAMP(datetime) > %d",
                                    $first['visitor_uuid'],
                                    strtotime($first['start'])
                                )
                            );
                        } else {
                            $consents = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                                $wpdb->prepare(
                                    "SELECT * FROM {$wpdb->prefix}analytics_visitor_consent WHERE visitor_uuid = %s AND UNIX_TIMESTAMP(datetime) > %d AND UNIX_TIMESTAMP(datetime) < %d",
                                    $first['visitor_uuid'],
                                    strtotime($first['start']),
                                    strtotime($first['end'])
                                )
                            );
                        }
    
                        foreach ($consents as $consent) {
                            $consent_data = $first;
    
                            if ($consent->consent_uuid !== null) {
                                $consent_detail = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                                    $wpdb->prepare(
                                        "SELECT * FROM {$wpdb->prefix}analytics_consent WHERE uuid = %s",
                                        $consent->consent_uuid
                                    )
                                );

                                if (!isset($consent_detail->consent) || $consent_detail->consent !== 1) {
                                    continue;
                                }
    
                                if (!empty($consent_detail)) {
                                    $consent_attibute = [
                                        "web3id" => $consent_detail->web3id,
                                        "network" => $consent_detail->network,
                                        "datetime" => $consent_detail->datetime,
                                        "expiration" => $consent_detail->expiration,
                                        "tier" => 1,
                                    ];
    
                                    $wallet_detail = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                                        $wpdb->prepare(
                                            "SELECT * FROM {$wpdb->prefix}analytics_wallet WHERE uuid = %s",
                                            $consent_detail->wallet_uuid
                                        )
                                    );
    
                                    if (!empty($wallet_detail)) {
                                        $consent_attibute["wallet"] = $wallet_detail->address;
                                    }
    
                                    if ($consent_detail->web3id) {
                                        $consent_attibute["tier"] = 2;
                                    }
    
                                    if ($wallet_detail->address) {
                                        $consent_attibute["tier"] = 3;
                                    }
    
                                    if ($consent_detail->web3id && $wallet_detail->address) {
                                        $consent_attibute["tier"] = 4;
                                    }
    
                                    $consent_data['attributes'] = $consent_attibute;
                                }
    
                                $consent_data['uuid'] = $consent->consent_uuid;
                                $consent_data['start'] = $consent_detail->datetime;
                                $consent_data['end'] = $consent_detail->expiration;
                            } else {

                                if ($consent->consent !== 1) {
                                    continue;
                                }

                                $consent_data['start'] = $consent->datetime;
                                $consent_data['end'] = $consent->expiration;
                            }
    
                            $consent_data['event_name'] = 'Consent';
                            $consent_data['event_type'] = 'consent';
    
                            $hash_map[$consent_data->flow_uuid][] = $consent_data;
                        }
                    }
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
                if ( $params[1] === 'flows') {

                    $bad_url_count = 0;

                    if (!empty($events)) {
                        $bad_url_count = count(array_filter($events, fn($item) => isset($item->status_code) && $item->status_code !== 200));
                    }

                    $collection[] = [
                        'uuid' => $item->uuid,
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
                        'duration' => $item->duration,
                        'action' => $item->action,
                        'event' => $item->event,
                        'conversion' => $item->conversion,
                        'url' => $item->url,
                        'ux_percent' => $item->ux_percent,
                        'pageview' => $item->pageview,
                        'sop_id' => $item->sop_id,
                        'visit_actions' => $item->visit_actions,
                        'event_actions' => $item->event_actions,
                        'conversion_actions' => $item->conversion_actions,
                        'bad_user' => $bad_url_count > 1 ? true : false,
                    ];
                }
                elseif ( $params[1] === 'flow' ) {
                    $collection = [
                        'uuid' => $item->uuid,
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
                        'duration' => $item->duration,
                        'action' => $item->action,
                        'event' => $item->event,
                        'conversion' => $item->conversion,
                        'url' => $item->url,
                        'ux_percent' => $item->ux_percent,
                        'pageview' => $item->pageview,
                        'sop_id' => $item->sop_id,
                        'visit_actions' => $item->visit_actions,
                        'event_actions' => $item->event_actions,
                        'conversion_actions' => $item->conversion_actions,
                    ];
                }
            }
        }

        if ( $params[1] === 'flows') {
            return [
                'collection' => $collection,
                'page' => $list_response['page'],
                'page_size' => $list_response['page_size'],
                'total_pages' => $list_response['total_pages'],
                'total_elements' => $list_response['total_elements'],
            ];
        }
        elseif ( $params[1] === 'flow' ) {
            return $collection;
        }
    }
}
