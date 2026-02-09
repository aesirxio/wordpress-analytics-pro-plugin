<?php
/**
 * Plugin Name: AesirX Analytics
 * Plugin URI: https://analytics.aesirx.io?utm_source=wpplugin&utm_medium=web&utm_campaign=wordpress&utm_id=aesirx&utm_term=wordpress&utm_content=analytics
 * Description: Aesirx Analytics plugin. When you join forces with AesirX, you're not just becoming a Partner - you're also becoming a freedom fighter in the battle for privacy! Earn 25% Affiliate Commission <a href="https://aesirx.io/partner?utm_source=wpplugin&utm_medium=web&utm_campaign=wordpress&utm_id=aesirx&utm_term=wordpress&utm_content=analytics">[Click to Join]</a>
 * Version: 1.0.1
 * Author: aesirx.io
 * Author URI: https://aesirx.io/
 * Domain Path: /languages
 * Text Domain: aesirx-analytics
 * Requires PHP: 7.4
 * License: GPL v3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * 
 **/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use AesirxAnalytics\CliFactory;
use AesirxAnalytics\Track\ApiTracker;
use AesirxAnalytics\Track\CliTracker;
use AesirxAnalyticsLib\Exception\ExceptionWithResponseCode;
use AesirxAnalytics\Route\Middleware\IsBackendMiddleware;
use AesirxAnalyticsLib\RouterFactory;
use Pecee\Http\Request;
use Pecee\SimpleRouter\Exceptions\NotFoundHttpException;
use Pecee\SimpleRouter\Route\RouteUrl;
use AesirxAnalytics\Migrator\MigratorMysql;

require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';
require_once plugin_dir_path(__FILE__) . 'includes/settings.php';

include_once(ABSPATH . 'wp-admin/includes/plugin.php');

function aesirx_analytics_pro_plugin_check_consent_active(): bool {
    return is_plugin_active('aesirx-consent/aesirx-consent.php');
}

function aesirx_analytics_pro_config_is_ok(string $isStorage = null): bool {
  $options = get_option('aesirx_analytics_pro_plugin_options');
    $res = (!empty($options['storage'])
        && (
            ($options['storage'] === 'internal')
            || ($options['storage'] === 'external' && !empty($options['domain']))
        ));

    if ($res
        && !is_null($isStorage))
    {
        $res = $options['storage'] === $isStorage;
    }

    return $res;
}
if (aesirx_analytics_pro_config_is_ok()) {
    add_action('wp_enqueue_scripts', function (): void {
        if (!aesirx_analytics_pro_plugin_check_consent_active()) {
            wp_register_script('aesirx-analytics', plugins_url('assets/vendor/statistic.js', __FILE__), [], '1.0.1',  array(
                'in_footer' => false,
            ));
        }
        wp_enqueue_script('aesirx-analytics');

        $options = get_option('aesirx_analytics_pro_plugin_options');
        $origin = wp_parse_url( home_url(), PHP_URL_SCHEME ) . '://' . wp_parse_url( home_url(), PHP_URL_HOST );
        $domain =
            ($options['storage'] ?? 'internal') === 'internal'
                ? $origin
                : rtrim($options['domain'] ?? '', '/');

        $trackEcommerce = ($options['track_ecommerce'] ?? 'false') === 'true' ? 'true': 'false';
        $clientId = $options['clientid'] ?? '';
        $secret = $options['secret'] ?? '';

        $domain = $origin;

        wp_add_inline_script(
            'aesirx-analytics',
            'window.aesirx1stparty="' . esc_attr($domain) . '";
            window.aesirxClientID="' . esc_attr($clientId) . '";
            window.aesirxClientSecret="' . esc_attr($secret) . '";
            window.aesirxTrackEcommerce="' . esc_attr($trackEcommerce) . '";',
            'before');
    });

    add_action( 'init', function (): void {
        $options = get_option('aesirx_analytics_pro_plugin_options');

        if (is_admin()
            || ($options['track_ecommerce'] ?? 'false') !== 'true'
            || !get_transient('aesirx_analytics_session'))
        {
            return;
        }

        $flowUuid = get_transient('aesirx_analytics_session') ? sanitize_text_field(get_transient('aesirx_analytics_session')) : null;

        if (is_null($flowUuid))
        {
            return;
        }

        if (aesirx_analytics_config_is_ok('internal'))
        {
            $tracker = new CliTracker(CliFactory::getCli());
        }
        else
        {
            $tracker = new ApiTracker(rtrim($options['domain'] ?? '', '/'));
        }

        (new \AesirxAnalytics\Integration\Woocommerce($tracker, $flowUuid))
        ->registerHooks();
    });
}

if (is_plugin_active('wp-crontrol/wp-crontrol.php')) {
    add_action('analytics_cron_geo', 'aesirx_analytics_cron_geo_handler');
    function aesirx_analytics_cron_geo_handler() {
        if (aesirx_analytics_pro_config_is_ok('internal')) {
            CliFactory::getCli()->processAnalytics(['job', 'geo']);
        }
    }
    if (!wp_next_scheduled('analytics_cron_geo')) {
      wp_schedule_event(time(), 'hourly', 'analytics_cron_geo');
    }
}

add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
    $url = esc_url(add_query_arg('page', 'aesirx-analytics-plugin', get_admin_url() . 'admin.php'));
    $links[] = sprintf(
        '<a href="%s">%s</a>',
        esc_url($url),
        esc_html__('Settings', 'aesirx-analytics')
    );
  return $links;
});

add_action( 'parse_request', 'aesirx_analytics_pro_url_handler' );


function aesirx_analytics_pro_url_handler()
{
    $options = get_option('aesirx_analytics_pro_plugin_options');

    if (($options['storage'] ?? 'internal') !== 'internal') {
        return;
    }

    $callCommand = function (array $command): string {
        try
        {
            $data = CliFactory::getCli()->processAnalytics($command);
        }
        catch (Exception $e)
        {
            $data = wp_json_encode([
                'error' => $e->getMessage()
            ]);
        }

        if (!headers_sent()) {
            header( 'Content-Type: application/json; charset=utf-8' );
        }
        return $data;
    };

    try {
        $router = (new RouterFactory(
            $callCommand,
            new IsBackendMiddleware(),
            null,
            site_url( '', 'relative' ))
        )
            ->getSimpleRouter();

        $router->addRoute(
            (new RouteUrl('/remember_flow/{flow}', static function (string $flow): string {

                set_transient('analytics_flow_uuid', $flow, HOUR_IN_SECONDS);

                return wp_json_encode(true);
            }))
                ->setWhere(['flow' => '[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}'])
                ->setRequestMethods([Request::REQUEST_TYPE_POST])
        );

        echo wp_kses_post($router->start());
    } catch (Throwable $e) {
        if ($e instanceof NotFoundHttpException) {
        return;
        }

        if ($e instanceof ExceptionWithResponseCode) {
            $code = $e->getResponseCode();
        } else {
            $code = 500;
        }

        if (!headers_sent()) {
            header( 'Content-Type: application/json; charset=utf-8' );
        }
        http_response_code($code);
        echo wp_json_encode([
            'error' => $e->getMessage(),
        ]);
    }

    die();
}

register_activation_hook(__FILE__, 'aesirx_analytics_pro_initialize_function');
function aesirx_analytics_pro_initialize_function() {
    global $wpdb;

    //Add migration table
    MigratorMysql::aesirx_analytics_create_migrator_table_query();
    $migration_list = array_column(MigratorMysql::aesirx_analytics_fetch_rows(), 'name');

    $files = glob(plugin_dir_path( __FILE__ ) . 'src/Migration/*.php');
    foreach ($files as $file) {
        $realpath = realpath($file);
        if ($realpath && strpos($realpath, plugin_dir_path(__FILE__) . 'src/Migration/') === 0) {
            include_once $realpath; // Safe inclusion
            $file_name = basename($realpath, ".php");
            if (!in_array($file_name, $migration_list, true)) {
                MigratorMysql::aesirx_analytics_add_migration_query($file_name);
                $sql = $aesirx_analytics_pro_sql ?? []; // Ensure $sql is an array
                foreach ($sql as $each_query) {
                    // Used placeholders and $wpdb->prepare() in variable $each_query
                    // Need $wpdb->query() for ALTER TABLE
                    $wpdb->query($each_query); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
                }
            }
        }
    }
    add_option('aesirx_analytics_pro_do_activation_redirect', true);
}

function aesirx_analytics_pro_display_update_notice(  ) {
    $notice = get_transient( 'aesirx_analytics_update_notice' );
    if( $notice ) {

        $notice = json_decode($notice, true);

        if ($notice instanceof Throwable)
        {
            /* translators: %s: error message */
            // using custom function to escape HTML in error message
            echo wp_kses('<div class="notice notice-error"><p>' . esc_html__('Problem with Aesirx Analytics plugin install', 'aesirx-analytics') . '</p></div>', array(
                'p' => array(
                    'class' => array(),
                    'span' => array(
                    'class' => array(),
                ),
                'div' => array(
                    'id' => array(),
                    'class' => array(),
                ),
            )));
        }

        delete_transient( 'aesirx_analytics_update_notice' );
    }
}

add_action( 'admin_notices', 'aesirx_analytics_pro_display_update_notice' );


add_action('admin_init', function () {
    if (!get_option('aesirx_analytics_pro_do_activation_redirect')) {
        return;
    }
    delete_option('aesirx_analytics_pro_do_activation_redirect');
    if (wp_doing_ajax() || wp_doing_cron()) {
        return;
    }
    if (isset($_GET['page']) && $_GET['page'] === 'aesirx-analytics-plugin') {
        return;
    }
    wp_safe_redirect(
        admin_url('options-general.php?page=aesirx-analytics-plugin')
    );
    exit;
});

add_filter( 'site_transient_update_plugins', function( $value ) {
    if ( isset( $value->response['aesirx-analytics/aesirx-analytics.php'] ) ) {
        unset( $value->response['aesirx-analytics/aesirx-analytics.php'] );
    }
    return $value;
});