<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

add_action('admin_init', function () {
  register_setting('aesirx_analytics_pro_plugin_options', 'aesirx_analytics_pro_plugin_options', function (
    $value
  ) {
    $valid = true;
    $input = (array) $value;

    if ($input['storage'] === 'internal') {
      if (empty($input['license'])) {
        add_settings_error(
          'aesirx_analytics_pro_plugin_options',
          'license',
          esc_html__('Please register your license at Signup.aesirx.io to enable the external first-party server.', 'aesirx-analytics'),
          'warning'
        );
      }
    } elseif ($input['storage'] === 'external') {
      if (empty($input['domain'])) {
        $valid = false;
        add_settings_error(
          'aesirx_analytics_pro_plugin_options',
          'domain',
          esc_html__('Domain is empty.', 'aesirx-analytics')
        );
      } elseif (filter_var($input['domain'], FILTER_VALIDATE_URL) === false) {
        $valid = false;
        add_settings_error(
          'aesirx_analytics_pro_plugin_options',
          'domain',
          esc_html__('Invalid domain format.', 'aesirx-analytics')
        );
      }
    }

    // Ignore the user's changes and use the old database value.
    if (!$valid) {
      $value = get_option('aesirx_analytics_pro_plugin_options');
    }

    return $value;
  });
  add_settings_section(
    'aesirx_analytics_settings',
    '',
    function () {
      echo "";
    },
    'aesirx_analytics_plugin'
  );

  add_settings_section(
    'aesirx_analytics_register_license',
    '',
    function () {
      // using custom function to escape HTML
      $isRegisted = true;
      echo wp_kses("
      <div class='aesirx_analytics_register_license'>
        ".($isRegisted ? "<img width='255px' height='96px' src='". plugins_url( 'aesirx-analytics/assets/images-plugin/banner_1.png')."' />" :"")."
        <div class='aesirx_analytics_register_license_notice'>
        ".aesirx_analytics_license_info()."
        </div>
        ".($isRegisted ? "" :"
          <p>".esc_html__("Haven't got Shield of Privacy ID yet?", 'aesirx-analytics')."</p>
        ")."
        ".($isRegisted ? "
          <a class='aesirx_btn_success cta-button' target='_blank' href='https://aesirx.io/licenses'>
            ".esc_html__("Manage License Here", 'aesirx-analytics')."
            <img width='20px' height='20px' src='". plugins_url( 'aesirx-analytics/assets/images-plugin/external_link_icon.png')."' />
          </a>
        " :"
          <button class='aesirx_btn_success cta-button' type='button' id='sign-up-button'>
            ".esc_html__("Sign up now", 'aesirx-analytics')."
            <img width='20px' height='20px' src='". plugins_url( 'aesirx-analytics/assets/images-plugin/external_link_icon.png')."' />
          </button>
        ")."
      </div>", aesirx_analytics_escape_html());
    },
    'aesirx_analytics_register_license'
  );

  function aesirx_analytics_pro_warning_missing_license() {
    $options = get_option('aesirx_analytics_pro_plugin_options');

    if (!$options || (empty($options['license']) && $options['storage'] === "internal")) {
      ?>
        <div class="notice-warning notice notice-bi" style="display: none;">
            <p><?php echo esc_html__( 'Please register your license at signup.aesirx.io.', 'aesirx-analytics' ); ?></p>
        </div>
      <?php
    }
  }
  add_action( 'admin_notices', 'aesirx_analytics_pro_warning_missing_license' );

  function aesirx_analytics_warning_missing_crontrol() {

    if (!is_plugin_active('wp-crontrol/wp-crontrol.php')) {
      if (get_option('aesirx_analytics_crontrol_notice_dismissed')) {
        return;
      }
      ?>
        <div class="notice-warning notice notice-bi aesirx-analytics-notice is-dismissible" style="display: none;">
          <?php echo wp_kses("<p>".esc_html__("To activate GEO tracking of analytics data, the WP Control Plugin has to be installed / activated.", 'aesirx-analytics' )."</p>
                                  <p>".sprintf(__("To install it click <a style='color: #2271b1' target='_blank' href='%1\$s'>here</a> and remember to activate the plugin.", 'aesirx-analytics' ), '/wp-admin/plugin-install.php?tab=plugin-information&plugin=wp-crontrol')."</p>",
            aesirx_analytics_escape_html()); ?>
        </div>
      <?php
    }
  }
  add_action( 'admin_notices', 'aesirx_analytics_warning_missing_crontrol' );

  function aesirx_dismiss_crontrol_notice() {
    update_option('aesirx_analytics_crontrol_notice_dismissed', true);
    wp_die();
  }
  add_action('wp_ajax_aesirx_dismiss_crontrol_notice', 'aesirx_dismiss_crontrol_notice');

  add_settings_field(
    'aesirx_analytics_storage',
    esc_html__('AesirX First-Party Server', 'aesirx-analytics'),
    function () {

      $options = get_option('aesirx_analytics_pro_plugin_options', []);
      $checked = 'checked="checked"';
      $storage = $options['storage'] ?? 'internal';
      // using custom function to escape HTML in label
      echo wp_kses('
      <label>' . esc_html__('Internal', 'aesirx-analytics') . ' <input type="radio" class="analytic-storage-class" name="aesirx_analytics_pro_plugin_options[storage]" ' .
          ($storage === 'internal' ? $checked : '') .
          ' value="internal"  /></label>
      <label>' . esc_html__('External', 'aesirx-analytics') . ' <input type="radio" class="analytic-storage-class" name="aesirx_analytics_pro_plugin_options[storage]" ' .
          ($storage === 'external' ? $checked : '') .
          ' value="external" /></label>', aesirx_analytics_escape_html());
          echo wp_kses('<p class="description"><strong>'.esc_html__('Internal Storage', 'aesirx-analytics').': </strong>'.esc_html__('Stores analytics data directly within the WordPress database (WP DB). This option does not offer additional control over the data, as it is part of the core website infrastructure. It may be less secure since it shares space with other WordPress data and could impact performance, especially with high traffic or large datasets.', 'aesirx-analytics').'</p>',aesirx_analytics_escape_html());
          echo wp_kses('<p class="description"><strong>'.esc_html__('External Storage (First-Party Server)', 'aesirx-analytics').': </strong>'.esc_html__('Stores analytics data on a dedicated first-party server, isolating the data from the WordPress database. This improves security and performance by keeping analytics data separate, reducing the load on the WordPress site. It also supports enhanced Web3 functionality, making it a more secure and efficient solution for handling data.', 'aesirx-analytics').'</p>',aesirx_analytics_escape_html());
          echo '<script>
          jQuery(document).ready(function() {
          function switch_radio(test) {
            if (test === "internal") {
              jQuery("#aesirx_analytics_domain").parents("tr").hide();
              jQuery("#aesirx_analytics_clientid").parents("tr").hide();
              jQuery("#aesirx_analytics_secret").parents("tr").hide();
              jQuery("#aesirx_analytics_geo_cron_time").parents("tr").show();
              jQuery("#aesirx_analytics-enable_cronjob").parents("tr").show();
            } else {
              jQuery("#aesirx_analytics_domain").parents("tr").show();
              jQuery("#aesirx_analytics_clientid").parents("tr").show();
              jQuery("#aesirx_analytics_secret").parents("tr").show();
              jQuery("#aesirx_analytics_geo_cron_time").parents("tr").hide();
              jQuery("#aesirx_analytics-enable_cronjob").parents("tr").hide();
            }
          }
          jQuery("input.analytic-storage-class").click(function() {
          switch_radio(jQuery(this).val())
          });
        switch_radio("' . esc_html($storage) . '");
      });
      </script>';
      $manifest = json_decode(
        file_get_contents(plugin_dir_path(__DIR__) . 'assets-manifest.json', true)
      );

      if ($manifest->entrypoints->plugin->assets) {
        foreach ($manifest->entrypoints->plugin->assets->js as $js) {
          wp_enqueue_script('aesrix_bi' . md5($js), plugins_url($js, __DIR__), false, '1.0', true);
        }
      }
    },
    'aesirx_analytics_plugin',
    'aesirx_analytics_settings'
  );

  add_settings_field(
    'aesirx_analytics_license',
    esc_html__('License', 'aesirx-analytics'),
    function () {
      $options = get_option('aesirx_analytics_pro_plugin_options', []);
      echo "<div class='input_container'>";
      echo wp_kses("
        <input id='aesirx_analytics_license' 
                class='aesirx_consent_input'
                placeholder='" . esc_attr__('License', 'aesirx-analytics') . "'
                name='aesirx_analytics_pro_plugin_options[license]'
                type='text' value='" .esc_attr($options['license'] ?? '') ."' />", aesirx_analytics_escape_html());
      echo wp_kses("
        <div class='input_information'>
          <img width='20px' height='20px' src='". plugins_url( 'aesirx-analytics/assets/images-plugin/infor_icon.png')."' />
          ".sprintf(__("<div class='input_information_content'>
          <a href='%1\$s' target='_blank'>Sign up</a> on the AesirX platform to obtain your Shield of Privacy ID and free license.</div>", 'aesirx-analytics'), 'https://signup.aesirx.io')."
        </div>
      ", aesirx_analytics_escape_html());
      echo "</div>";
    },
    'aesirx_analytics_plugin',
    'aesirx_analytics_settings'
  );

  add_settings_field(
    'aesirx_analytics_domain',
    __('Domain <i>(Use next format: http://example.com:1000/)</i>', 'aesirx-analytics'),
    function () {
      $options = get_option('aesirx_analytics_pro_plugin_options', []);
      echo "<div class='input_container'>";
      echo wp_kses("
        <input id='aesirx_analytics_domain' 
                class='aesirx_consent_input'
                placeholder='" . esc_attr__('Domain', 'aesirx-analytics') . "'
                name='aesirx_analytics_pro_plugin_options[domain]'
                type='text' value='" .esc_attr($options['domain'] ?? '') ."' />", aesirx_analytics_escape_html());
      echo wp_kses("
        <div class='input_information'>
          <img width='20px' height='20px' src='". plugins_url( 'aesirx-analytics/assets/images-plugin/infor_icon.png')."' />
          ".sprintf(__("<div class='input_information_content'>
          You can setup 1st party server at <a href='%1\$s' target='_blank'>%1\$s</a>.</div>", 'aesirx-analytics'), 'https://aesirx.io/documentation/first-party-server/install-guide/1st-party')."
        </div>
      ", aesirx_analytics_escape_html());
      echo "</div>";
    },
    'aesirx_analytics_plugin',
    'aesirx_analytics_settings'
  );

  add_settings_field(
    'aesirx_analytics_clientid',
    esc_html__('Client ID', 'aesirx-analytics'),
    function () {
      $options = get_option('aesirx_analytics_pro_plugin_options', []);
      echo "<div class='input_container'>";
      echo wp_kses("
        <input id='aesirx_analytics_clientid' 
                class='aesirx_consent_input'
                placeholder='" . esc_attr__('SSO Client ID', 'aesirx-analytics') . "'
                name='aesirx_analytics_pro_plugin_options[clientid]'
                type='text' value='" .esc_attr($options['clientid'] ?? '') ."' />", aesirx_analytics_escape_html());
      echo wp_kses("
        <div class='input_information'>
          <img width='20px' height='20px' src='". plugins_url( 'aesirx-analytics/assets/images-plugin/infor_icon.png')."' />
          ".sprintf(__("<div class='input_information_content'>
          Provided SSO CLIENT ID from <a href='%1\$s' target='_blank'>%1\$s</a>.</div>", 'aesirx-analytics'), 'https://aesirx.io/licenses')."
        </div>
      ", aesirx_analytics_escape_html());
      echo "</div>";
    },
    'aesirx_analytics_plugin',
    'aesirx_analytics_settings'
  );

  add_settings_field(
    'aesirx_analytics_secret',
    esc_html__('Client Secret', 'aesirx-analytics'),
    function () {
      $options = get_option('aesirx_analytics_pro_plugin_options', []);
      echo "<div class='input_container'>";
      echo wp_kses("<input id='aesirx_analytics_secret' class='aesirx_consent_input' placeholder='".esc_attr__('SSO Client Secret', 'aesirx-analytics')."' name='aesirx_analytics_pro_plugin_options[secret]' type='text' value='" .
      esc_attr($options['secret'] ?? '') .
      "' />", aesirx_analytics_escape_html());
      echo wp_kses("
        <div class='input_information'>
          <img width='20px' height='20px' src='". plugins_url( 'aesirx-analytics/assets/images-plugin/infor_icon.png')."' />
          ".sprintf(__("<div class='input_information_content'>
          Provided SSO Client Secret from <a href='%1\$s' target='_blank'>%1\$s</a>.</div>", 'aesirx-analytics'), 'https://aesirx.io/licenses')."
        </div>
      ", aesirx_analytics_escape_html());
      echo "</div>";
    },
    'aesirx_analytics_plugin',
    'aesirx_analytics_settings'
  );
  
  if (is_plugin_active('wp-crontrol/wp-crontrol.php')) {
    add_settings_field(
      'aesirx_analytics_enable_cronjob',
      esc_html__('Enable cronjob', 'aesirx-analytics'),
      function () {
          $options = get_option('aesirx_analytics_pro_plugin_options', []);
          $checked = 'checked="checked"';
          $storage = $options['enable_cronjob'] ?? 'true';
          // using custom function to escape HTML
          echo wp_kses('
          <label>' . esc_html__('Yes', 'aesirx-analytics') . ' <input type="radio" id="aesirx_analytics-enable_cronjob" name="aesirx_analytics_pro_plugin_options[enable_cronjob]" ' .
               ($storage === 'true' ? $checked : '') .
               ' value="true"  /></label>
          <label>' . esc_html__('No', 'aesirx-analytics') . ' <input type="radio" id="aesirx_analytics-enable_cronjob" name="aesirx_analytics_pro_plugin_options[enable_cronjob]" ' .
               ($storage === 'false' ? $checked : '') .
               ' value="false" /></label>',aesirx_analytics_escape_html());
          echo wp_kses('<p class="description"><strong>'.esc_html__('Description', 'aesirx-analytics').': </strong>'.esc_html__('This setting allows you to capture the geographic location of users when tracking their activity. The location data can be used to improve personalized experiences or for location-based analytics.', 'aesirx-analytics').'</p>',aesirx_analytics_escape_html());
      },
      'aesirx_analytics_plugin',
      'aesirx_analytics_settings'
    );

    add_settings_field(
      'aesirx_analytics_geo_cron_time',
      esc_html__('Geo cron time', 'aesirx-analytics'),
      function () {
        $options = get_option('aesirx_analytics_pro_plugin_options', []);
        echo "<div class='input_container'>";
        echo wp_kses("<input id='aesirx_analytics_geo_cron_time' class='aesirx_consent_input' placeholder='".esc_attr__('Geo cron time', 'aesirx-analytics')."' name='aesirx_analytics_pro_plugin_options[geo_cron_time]' type='text' value='" .
        esc_attr($options['geo_cron_time'] ?? '') .
        "' />", aesirx_analytics_escape_html());
        echo wp_kses("
          <div class='input_information'>
            <img width='20px' height='20px' src='". plugins_url( 'aesirx-analytics/assets/images-plugin/infor_icon.png')."' />
            ".sprintf(__("<div class='input_information_content'>
            This function runs a cron job at set intervals ('X' time) to refresh and update the user’s location data. This ensures that location tracking remains accurate over time without requiring manual intervention.</div>", 'aesirx-analytics'), '')."
          </div>
        ", aesirx_analytics_escape_html());
        echo "</div>";
      },
      'aesirx_analytics_plugin',
      'aesirx_analytics_settings'
    );
  }

  add_settings_field(
    'aesirx_analytics_track_ecommerce',
    esc_html__('Track Ecommerce', 'aesirx-analytics'),
    function () {

        $options = get_option('aesirx_analytics_pro_plugin_options', []);
        $checked = 'checked="checked"';
        $storage = $options['track_ecommerce'] ?? 'true';
        // using custom function to escape HTML
        echo wp_kses('
        <label>' . esc_html__('Yes', 'aesirx-analytics') . ' <input type="radio" class="analytic-track_ecommerce-class" name="aesirx_analytics_pro_plugin_options[track_ecommerce]" ' .
             ($storage === 'true' ? $checked : '') .
             ' value="true"  /></label>
        <label>' . esc_html__('No', 'aesirx-analytics') . ' <input type="radio" class="analytic-track_ecommerce-class" name="aesirx_analytics_pro_plugin_options[track_ecommerce]" ' .
             ($storage === 'false' ? $checked : '') .
             ' value="false" /></label>',aesirx_analytics_escape_html());
        echo wp_kses('<p class="description"><strong>'.esc_html__('Description', 'aesirx-analytics').': </strong>'.esc_html__('If enabled, this feature will track key Woo events, including Add to Cart, Checkout, and Search Product. This allows website owners to gather data on shopping behaviors and optimize the eCommerce experience.', 'aesirx-analytics').'</p>',aesirx_analytics_escape_html());
    },
    'aesirx_analytics_plugin',
    'aesirx_analytics_settings'
  );

  add_settings_section(
    'aesirx_analytics_info',
    '',
    function () {
      // using custom function to escape HTML
      echo wp_kses("<div class='aesirx_analytics_info'><div class='wrap'>".esc_html__("Need Help? Access Our Comprehensive Documentation Hub", 'aesirx-analytics')."
      <p class='banner-description'>".sprintf(__("Explore How-To Guides, instructions, and tutorials to get the most from AesirX Consent Shield. Whether you're a </br> developer or admin, find all you need to configure and optimize your privacy setup.", 'aesirx-analytics'))."</p>
      <p class='banner-description-bold'>".esc_html__("Ready to take the next step? Discover the latest features and best practices.", 'aesirx-analytics')."</p><div>
      <a target='_blank' href='https://aesirx.io/documentation'><img src='". plugins_url( 'aesirx-analytics/assets/images-plugin/icon_button.svg')."' />".esc_html__('ACCESS THE DOCUMENTATION HUB', 'aesirx-analytics')."</a></div>",aesirx_analytics_escape_html());
    },
    'aesirx_analytics_info'
  );
});

add_action('admin_menu', function () {
  add_options_page(
    esc_html__('Aesirx Analytics', 'aesirx-analytics'),
    esc_html__('Aesirx Analytics', 'aesirx-analytics'),
    'manage_options',
    'aesirx-analytics-plugin',
    function () {
      ?>
      <h2 id="aesirx_heading" class="aesirx_heading"><?php echo esc_html__('AesirX Analytics & CMP', 'aesirx-analytics'); ?></h2>
      <?php echo wp_kses_post(
        /* translators: %s: URL to aesir.io read mor details */
        sprintf('<p id="aesirx_description" class="description"><strong>'. esc_html__('Note: ', 'aesirx-analytics') . '</strong>' . esc_html__('Please set Permalink Settings in WP so it is NOT set as plain.', 'aesirx-analytics') .'</p>')
      ); ?>
      <div class="aesirx_analytics_wrapper">
        <div class="form_wrapper">
          <form action="options.php" method="post">
            <?php
              settings_fields('aesirx_analytics_pro_plugin_options');
              do_settings_sections('aesirx_analytics_plugin');
              wp_nonce_field('aesirx_analytics_settings_save', 'aesirx_analytics_settings_nonce');
            ?>
            <button type="submit" class="submit_button aesirx_btn_success">
              <?php
                echo wp_kses("
                  <img width='20px' height='20px' src='". plugins_url( 'aesirx-analytics/assets/images-plugin/save_icon.png')."' />
                  ".esc_html__("Save settings", 'aesirx-analytics')."
                ", aesirx_analytics_escape_html()); 
              ?>
            </button>
          </form>
        </div>
			<?php
        echo '<div class="aesirx_analytics_right_section">';
        do_settings_sections('aesirx_analytics_register_license');
        echo '</div>';
        echo '</div>';
        do_settings_sections('aesirx_analytics_info');
    }
  );
  add_menu_page(
    'AesirX BI Dashboard',
    'AesirX Analytics',
    'manage_options',
    'aesirx-bi-dashboard',
    function () {
      ?><div id="biapp" class="aesirxui"></div><?php
    },
    plugins_url( 'aesirx-analytics/assets/images-plugin/AesirX_BI_icon.png'),
    3
  );
  add_submenu_page(
    'aesirx-bi-dashboard',
    'AesirX BI Dashboard',
    'Dashboard',
    'manage_options',
    'aesirx-bi-dashboard',
    function () {
      ?><div id="biapp" class="aesirxui"></div><?php
    },
    3);
  add_submenu_page(
    'aesirx-bi-dashboard',
    'AesirX BI Acquisition',
    'Acquisition',
    'manage_options',
    'aesirx-bi-acquisition',
    function () {
      ?><div id="biapp" class="aesirxui"></div><?php
    },
    3);
  add_submenu_page(
    'aesirx-bi-acquisition',
    'AesirX BI Acquisition Search Engine',
    'Acquisition Search Engine',
    'manage_options',
    'aesirx-bi-acquisition-search-engines',
    function () {
      ?><div id="biapp" class="aesirxui"></div><?php
    },
    3);
  add_submenu_page(
    'aesirx-bi-acquisition',
    'AesirX BI Acquisition Campaigns',
    'Acquisition Campaigns',
    'manage_options',
    'aesirx-bi-acquisition-campaigns',
    function () {
      ?><div id="biapp" class="aesirxui"></div><?php
    },
    3);
  add_submenu_page(
    'aesirx-bi-dashboard',
    'AesirX BI Behavior',
    'Behavior',
    'manage_options',
    'aesirx-bi-behavior',
    function () {
      ?><div id="biapp" class="aesirxui"></div><?php
    },
    3);
  add_submenu_page(
    'aesirx-bi-behavior',
    'AesirX BI Behavior Events',
    'Behavior Events',
    'manage_options',
    'aesirx-bi-behavior-events',
    function () {
      ?><div id="biapp" class="aesirxui"></div><?php
    },
    3);
  add_submenu_page(
    'aesirx-bi-behavior',
    'AesirX BI Behavior Events Generator',
    'Behavior Events Generator',
    'manage_options',
    'aesirx-bi-behavior-events-generator',
    function () {
      ?><div id="biapp" class="aesirxui"></div><?php
    },
    3);
  add_submenu_page(
    'aesirx-bi-behavior',
    'AesirX BI Behavior Tag Value Mapping',
    'Behavior Tag Value Mapping',
    'manage_options',
    'aesirx-bi-tag-events',
    function () {
      ?><div id="biapp" class="aesirxui"></div><?php
    },
    3);
  add_submenu_page(
    'aesirx-bi-behavior',
    'AesirX BI Behavior Tag Value Mapping Link',
    'Behavior Tag Value Mapping Link',
    'manage_options',
    'aesirx-bi-tag-events-link',
    function () {
      ?><div id="biapp" class="aesirxui"></div><?php
    },
    3);
  add_submenu_page(
    'aesirx-bi-behavior',
    'AesirX BI Behavior Tag Value Mapping Edit',
    'Behavior Tag Value Mapping Edit',
    'manage_options',
    'aesirx-bi-tag-events-edit',
    function () {
      ?><div id="biapp" class="aesirxui"></div><?php
    },
    3);
  add_submenu_page(
    'aesirx-bi-behavior',
    'AesirX BI Behavior Outlinks',
    'Behavior Outlinks',
    'manage_options',
    'aesirx-bi-behavior-outlinks',
    function () {
      ?><div id="biapp" class="aesirxui"></div><?php
    },
    3);
  add_submenu_page(
    'aesirx-bi-behavior',
    'AesirX BI Behavior User Flow',
    'Behavior User Flow',
    'manage_options',
    'aesirx-bi-behavior-users-flow',
    function () {
      ?><div id="biapp" class="aesirxui"></div><?php
    },
    3);
  add_submenu_page(
    'aesirx-bi-dashboard',
    'AesirX BI UTM Tracking',
    'Tracking',
    'manage_options',
    'aesirx-bi-utm-tracking',
    function () {
      ?><div id="biapp" class="aesirxui"></div><?php
    },
    5);
  add_submenu_page(
    'aesirx-bi-utm-tracking',
    'AesirX BI UTM Tracking Generator',
    'UTM Tracking Generator',
    'manage_options',
    'aesirx-bi-utm-links-add',
    function () {
      ?><div id="biapp" class="aesirxui"></div><?php
    },
    5);
  add_submenu_page(
    'aesirx-bi-utm-tracking',
    'AesirX BI UTM Value Mapping',
    'UTM Value Mapping',
    'manage_options',
    'aesirx-bi-utm-links',
    function () {
      ?><div id="biapp" class="aesirxui"></div><?php
    },
    5);
  add_submenu_page(
    'aesirx-bi-utm-tracking',
    'AesirX BI UTM Tracking Link',
    'UTM Tracking Link',
    'manage_options',
    'aesirx-bi-utm-links-link',
    function () {
      ?><div id="biapp" class="aesirxui"></div><?php
    },
    5);
  add_submenu_page(
    'aesirx-bi-utm-tracking',
    'AesirX BI UTM Tracking Edit',
    'UTM Tracking Edit',
    'manage_options',
    'aesirx-bi-utm-links-edit',
    function () {
      ?><div id="biapp" class="aesirxui"></div><?php
    },
    5);
  add_submenu_page(
    'aesirx-bi-dashboard',
    'AesirX BI Visitors',
    'Visitors',
    'manage_options',
    'aesirx-bi-visitors',
    function () {
      ?><div id="biapp" class="aesirxui"></div><?php
    },
    6);
  add_submenu_page(
    'aesirx-bi-visitors',
    'AesirX BI Visitors Locations',
    'Locations',
    'manage_options',
    'aesirx-bi-visitors-locations',
    function () {
      ?><div id="biapp" class="aesirxui"></div><?php
    },
    6);
  add_submenu_page(
    'aesirx-bi-visitors',
    'AesirX BI Visitors Flow',
    'Flow',
    'manage_options',
    'aesirx-bi-visitors-flow',
    function () {
      ?><div id="biapp" class="aesirxui"></div><?php
    },
    6);

  add_submenu_page(
    'aesirx-bi-visitors',
    'AesirX BI Visitors Platforms',
    'Platforms',
    'manage_options',
    'aesirx-bi-visitors-platforms',
    function () {
      ?><div id="biapp" class="aesirxui"></div><?php
    },
    6);
  add_submenu_page(
    'aesirx-bi-visitors',
    'AesirX BI Visitors Realtime',
    'Realtime',
    'manage_options',
    'aesirx-bi-visitors-realtime',
    function () {
      ?><div id="biapp" class="aesirxui"></div><?php
    },
    6);
  add_submenu_page(
    'aesirx-bi-visitors',
    'AesirX BI Visitors Flow Detail',
    'Flow',
    'manage_options',
    'aesirx-bi-flow',
    function () {
      ?><div id="biapp" class="aesirxui"></div><?php
    },
    6);
  add_submenu_page(
    'aesirx-bi-dashboard',
    'AesirX BI User Experience',
    'User Experience',
    'manage_options',
    'aesirx-bi-flow-list',
    function () {
      ?><div id="biapp" class="aesirxui"></div><?php
    },
    7);
  add_submenu_page(
    'aesirx-bi-dashboard',
    'AesirX BI Woocommerce',
    'Woo',
    'manage_options',
    'aesirx-bi-woocommerce',
    function () {
      ?><div id="biapp" class="aesirxui"></div><?php
    },
    8);
  add_submenu_page(
    'aesirx-bi-woocommerce',
    'AesirX BI Woocommerce Product',
    'Woo Product',
    'manage_options',
    'aesirx-bi-woocommerce-product',
    function () {
      ?><div id="biapp" class="aesirxui"></div><?php
    },
    8);
});

add_action('admin_init', 'aesirx_analytics_redirect_config', 1);
function aesirx_analytics_redirect_config() {
  $current_url = home_url(add_query_arg(null, null));
  $parsed_url = wp_parse_url($current_url);
  
  if (isset($parsed_url['query'])) {
    $query_params = wp_parse_args($parsed_url['query']);

    $query_params = array_map('sanitize_text_field', $query_params);

    if (isset($query_params['page']) && strpos($query_params['page'], 'aesirx-bi') !== false) {
      if (!isset($query_params['aesirx_analytics_nonce']) || !wp_verify_nonce($query_params['aesirx_analytics_nonce'], 'aesirx_analytics_submenu')) {
        wp_die('Nonce verification failed');
      }

      $checked_page = array('aesirx-bi-dashboard', 'aesirx-bi-visitors', 'aesirx-bi-behavior', 'aesirx-bi-utm-tracking', 'aesirx-bi-woocommerce');
    
      if (in_array($query_params['page'], $checked_page, true) && !aesirx_analytics_pro_config_is_ok()) {
        wp_redirect('/wp-admin/options-general.php?page=aesirx-analytics-plugin');
        die;
      }
    }
  }
}

add_action('admin_enqueue_scripts', function ($hook) {
  if ($hook === 'toplevel_page_aesirx-bi-dashboard' || 
      $hook === 'toplevel_page_aesirx-bi-visitors' || 
      $hook === 'toplevel_page_aesirx-bi-behavior' || 
      $hook === 'toplevel_page_aesirx-bi-utm-tracking' || 
      $hook === 'toplevel_page_aesirx-bi-woocommerce' || 
      $hook === 'toplevel_page_aesirx-bi-acquisition' || 
      $hook === 'aesirx-bi_page_aesirx-bi-visitors' ||
      $hook === 'aesirx-bi_page_aesirx-bi-flow-list' ||
      $hook === 'admin_page_aesirx-bi-visitors-locations' || 
      $hook === 'admin_page_aesirx-bi-visitors-flow' || 
      $hook === 'admin_page_aesirx-bi-visitors-platforms' || 
      $hook === 'admin_page_aesirx-bi-visitors-realtime' || 
      $hook === 'admin_page_aesirx-bi-flow' || 
      $hook === 'aesirx-bi_page_aesirx-bi-behavior' ||
      $hook === 'admin_page_aesirx-bi-behavior-events' ||
      $hook === 'admin_page_aesirx-bi-behavior-events-generator' ||
      $hook === 'admin_page_aesirx-bi-behavior-outlinks' ||
      $hook === 'admin_page_aesirx-bi-behavior-users-flow' ||
      $hook === 'admin_page_aesirx-bi-tag-events' ||
      $hook === 'admin_page_aesirx-bi-tag-events-link' ||
      $hook === 'admin_page_aesirx-bi-tag-events-edit' ||
      $hook === 'aesirx-bi_page_aesirx-bi-utm-tracking' ||
      $hook === 'admin_page_aesirx-bi-utm-links' ||
      $hook === 'admin_page_aesirx-bi-utm-links-add' ||
      $hook === 'admin_page_aesirx-bi-utm-links-link' ||
      $hook === 'admin_page_aesirx-bi-utm-links-edit' ||
      $hook === 'aesirx-bi_page_aesirx-bi-acquisition' ||
      $hook === 'admin_page_aesirx-bi-acquisition-search-engines' ||
      $hook === 'admin_page_aesirx-bi-acquisition-campaigns' ||
      $hook === 'aesirx-bi_page_aesirx-bi-woocommerce' ||
      $hook === 'admin_page_aesirx-bi-woocommerce-product' ||
      $hook === 'admin_page_aesirx-bi-acquisition-campaigns') {
    wp_enqueue_script('aesirx-analytics-notice', plugins_url('assets/vendor/aesirx-analytics-notice.js', __DIR__), array('jquery'), false, true);
    $analyticsProOptions = get_option('aesirx_analytics_pro_plugin_options');

    $protocols = ['http://', 'https://'];
    $aesirxDomain = str_replace($protocols, '', site_url());
    $aesirxStreams = [['name' => get_bloginfo('name'), 'domain' => $aesirxDomain]];
    $aesirxEndpoint =
      ($analyticsProOptions['storage'] ?? 'internal') === 'internal'
        ? get_bloginfo('url')
        : rtrim($analyticsProOptions['domain'] ?? '', '/');

    $aesirxManifest = json_decode(
      file_get_contents(plugin_dir_path(__DIR__) . 'assets-manifest.json', true)
    );

    if ($aesirxManifest->entrypoints->bi->assets) {
      foreach ($aesirxManifest->entrypoints->bi->assets->js as $js) {
        wp_enqueue_script('aesrix_bi' . md5($js), plugins_url($js, __DIR__), false, '1.0', true);
      }
    }

    $aesirxClientId = $analyticsProOptions['clientid'];
    $aesirxClientSecret = $analyticsProOptions['secret'];
    $aesirxUTMCurrency = $analyticsProOptions['utm_currency'] ?? 'USD';
    $aesirxRealtimeSync = $analyticsProOptions['realtime_sync'] ?? '30';

    $aesirxJWT = $analyticsProOptions['storage'] === "external" ? 'window.env.REACT_APP_HEADER_JWT="true"' : '';

    $cmp_link = aesirx_analytics_pro_plugin_check_consent_active()
      ? admin_url( 'admin.php?page=aesirx-consent-management-plugin' )
      : '';
    wp_register_script( 'aesrix_bi_window', '', array(), '1.0', false );

    wp_enqueue_script('aesrix_bi_window');

    wp_add_inline_script(
      'aesrix_bi_window',
      'window.env = {};
      window.aesirxClientID = "' . esc_html($aesirxClientId) . '";
		  window.aesirxClientSecret = "' . esc_html($aesirxClientSecret) . '";
      window.env.REACT_APP_BI_ENDPOINT_URL = "' . esc_url($aesirxEndpoint) . '";
		  window.env.REACT_APP_DATA_STREAM = JSON.stringify(' . wp_json_encode($aesirxStreams) . ');
      window.env.REACT_APP_CMP_LINK = "' . esc_url( $cmp_link ) . '";
		  window.env.PUBLIC_URL= "' . esc_url(plugin_dir_url(__DIR__)) . '";
      window.env.STORAGE= "' . esc_html($analyticsProOptions['storage']) . '";
      window.env.LICENSE= "' . esc_html($analyticsProOptions['license']) . '";
      window.env.REACT_APP_WOOCOMMERCE_MENU= "' . esc_html($analyticsProOptions['track_ecommerce']) . '";
      window.env.REACT_APP_UTM_CURRENCY= "' . esc_html($aesirxUTMCurrency) . '";
      window.env.REACT_APP_REALTIME_SYNC= "' . esc_html($aesirxRealtimeSync) . '";
      
      ' . htmlspecialchars($aesirxJWT, ENT_NOQUOTES),
    );
  }
});

/**
 * Custom escape function for Aesirx Analytics.
 * Escapes HTML attributes in a string using a specified list of allowed HTML elements and attributes.
 *
 * @param string $string The input string to escape HTML attributes from.
 * @return string The escaped HTML string.
 */

 function aesirx_analytics_escape_html() {
  $allowed_html = array(
    'input' => array(
        'type'  => array(),
        'id'    => array(),
        'name'  => array(),
        'value' => array(),
        'class' => array(),
        'checked' => array(),
        'placeholder' => array(),
     ),
     'strong' => array(),
     'a' => array(
      'href'  => array(),
      'target'    => array(),
      'class'    => array(),
      'style'    => array(),
     ),
     'p' => array(
      'class' => array(),
      'span' => array(
        'class' => array(),
      ),
     ),
     'span' => array(
      'class' => array(),
     ),
     'h3' => array(),
     'ul' => array(
      'class' => array(),
     ),
     'li' => array(),
     'br' => array(),
     'label' => array(
      'for'  => array(),
      'class'  => array(),
     ),
     'img' => array(
      'src'  => array(),
      'class'  => array(),
      'width'  => array(),
      'height'  => array(),
     ),
     'iframe' => array(
      'src'  => array(),
     ),
     'div' => array(
        'id' => array(),
        'class' => array(),
     ),
     'button' => array(
        'type'  => array(),
        'id'    => array(),
        'name'  => array(),
        'value' => array(),
        'class' => array(),
    ),
  );

  return $allowed_html;
}

function aesirx_analytics_add_nonce_menu_item() {
  ?>
  <script type="text/javascript">
  jQuery(document).ready(function($) {
    $('#adminmenu .toplevel_page_aesirx-bi-dashboard > a').attr('href', function() {
      return aesirx_analytics_add_nonce_url($(this));
    });

    $('#adminmenu .toplevel_page_aesirx-bi-dashboard ul li').each(function() {
      const link = $(this).find('a');
      if (link.length) {
        link.attr('href', aesirx_analytics_add_nonce_url(link));
      }
    });
    $('#adminmenu #toplevel_page_aesirx-bi-dashboard .wp-submenu').css('display', 'none');
    function aesirx_analytics_add_nonce_url(url) {
      const originalHref = url.attr('href');
      const page = originalHref.match(/[?&]page=([^&]*)/);
      var nonce = '<?php echo esc_html(wp_create_nonce("aesirx_analytics_submenu")); ?>';
      return originalHref + '&aesirx_analytics_nonce=' + nonce;
    }
  });
  </script>
  <?php
}
add_action('admin_footer', 'aesirx_analytics_add_nonce_menu_item');

function aesirx_analytics_license_info() {
  $options = get_option('aesirx_analytics_pro_plugin_options', []);
  $domain = isset($_SERVER['SERVER_NAME']) ? sanitize_text_field($_SERVER['SERVER_NAME']) : '';
  $domain = preg_replace('/^www\./', '', $domain);
  $isTrial = false;

  if (!empty($options['license'])) {
    $response = aesirx_analytics_get_api('https://api.aesirx.io/index.php?webserviceClient=site&webserviceVersion=1.0.0&option=member&task=validateWPLicense&api=hal&license=' . $options['license']);
    $bodyCheckLicense = wp_remote_retrieve_body($response);
    $decodedDomains = json_decode($bodyCheckLicense)->result->domain_list->decoded ?? [];
    $domainList = array_column($decodedDomains, 'domain');
    $domainList = array_map(function ($d) {
        return preg_replace('/^www\./', '', $d);
    }, $domainList);
    $currentLicense = $options['current_license'] ?? '';

    if (is_array($response) && isset($response['response']['code']) && $response['response']['code'] === 200) {
      $isTrial = json_decode($bodyCheckLicense)->result->isTrial ?? false;
      if ($isTrial !== true) {
        if(!json_decode($bodyCheckLicense)->result->success || json_decode($bodyCheckLicense)->result->subscription_product !== "product-aesirx-cmp") {
          if($currentLicense) {
            $options['current_license'] = '';
            update_option('aesirx_analytics_pro_plugin_options', $options);
          }
          return  wp_kses(sprintf(__("Your license is expried or not found. Please update new license <a href='%1\$s' target='_blank'>%1\$s</a>.", 'aesirx-analytics'), 'https://aesirx.io/licenses'), aesirx_analytics_escape_html());
        } else if(!in_array($domain, $domainList, true)) {
          if( !isset($options['isDomainValid']) || $options['isDomainValid'] !== 'false') {
            $options['isDomainValid'] = 'false';
            $options['verify_domain'] = round(microtime(true) * 1000);
            update_option('aesirx_analytics_pro_plugin_options', $options);
          }
          return  wp_kses(sprintf(__("Your domain is not match with your license. Please update domain in your license <a href='%1\$s' target='_blank'>%1\$s</a> and click <span class='verify_domain'>here</span> to verify again.", 'aesirx-analytics'), 'https://aesirx.io/licenses'), aesirx_analytics_escape_html());
        } else {
          if(!isset($options['isDomainValid']) || $options['isDomainValid'] === 'false') {
            $options['isDomainValid'] = 'true';
            $options['verify_domain'] = round(microtime(true) * 1000);
            update_option('aesirx_analytics_pro_plugin_options', $options);
          }
          $dateExpired = new DateTime(json_decode($bodyCheckLicense)->result->date_expired);
          $currentDate = new DateTime();
          $interval = $currentDate->diff($dateExpired);
          $daysLeft = $interval->days;
          if ($interval->y > 2) {
            // License is considered lifetime
              return wp_kses(
                __("You are using a lifetime license.", 'aesirx-analytics'),
                aesirx_analytics_escape_html()
            );
          } else {
            $parts = [];
            if ($interval->y > 0) {
                $parts[] = $interval->y . ' ' . _n('year', 'years', $interval->y, 'aesirx-analytics');
            }
            if ($interval->m > 0) {
                $parts[] = $interval->m . ' ' . _n('month', 'months', $interval->m, 'aesirx-analytics');
            }
            if ($interval->d > 0 || empty($parts)) {
                $parts[] = $interval->d . ' ' . _n('day', 'days', $interval->d, 'aesirx-analytics');
            }
            $timeLeft = implode(', ', $parts);
            return wp_kses(
                sprintf(
                    __("Your license ends in %1\$s. Please update your license <a href='%2\$s' target='_blank'>%2\$s</a>.", 'aesirx-analytics'),
                    $timeLeft,
                    'https://aesirx.io/licenses'
                ),
                aesirx_analytics_escape_html()
            );
          }
        }
      }
    } else {
      $error_message = is_array($response) && isset($response['response']['message'])
        ? $response['response']['message']
        : (is_wp_error($response) ? $response->get_error_message() : __('Unknown error', 'aesirx-analytics'));
      return wp_kses(
          sprintf(
              __("Check license failed: %1\$s. Please contact the administrator or update your license.", 'aesirx-analytics'),
              $error_message,
          ),
          aesirx_analytics_escape_html()
      );
    }
  };
  if(empty($options['license']) || $isTrial) {
    $checkTrial = aesirx_analytics_get_api('https://api.aesirx.io/index.php?webserviceClient=site&webserviceVersion=1.0.0&option=member&task=validateWPDomain&api=hal&domain='.rawurlencode($domain));
    $body = wp_remote_retrieve_body($checkTrial);
    if($body) {
      if(json_decode($body)->result->success) {
        $dateExpired = new DateTime(json_decode($body)->result->date_expired);
        $currentDate = new DateTime();
        $interval = $currentDate->diff($dateExpired);
        $daysLeft = $interval->days;
        $hoursLeft = $interval->h;
        if ($daysLeft === 0) {
          $hoursLeft = max(1, $hoursLeft); // Ensure at least 1 hour is shown
          return wp_kses(
              sprintf(
                  __("Your trial license ends in %1\$s hour(s). Please update your license <a href='%2\$s' target='_blank'>here</a>.", 'aesirx-analytics'),
                  $hoursLeft,
                  'https://aesirx.io/licenses'
              ),
              aesirx_analytics_escape_html()
          );
        }
        if ($interval->y > 2) {
          // License is considered lifetime
            return wp_kses(
              __("You are using a lifetime license.", 'aesirx-analytics'),
              aesirx_analytics_escape_html()
          );
        } else {
          $parts = [];
          if ($interval->y > 0) {
              $parts[] = $interval->y . ' ' . _n('year', 'years', $interval->y, 'aesirx-analytics');
          }
          if ($interval->m > 0) {
              $parts[] = $interval->m . ' ' . _n('month', 'months', $interval->m, 'aesirx-analytics');
          }
          if ($interval->d > 0 || empty($parts)) {
              $parts[] = $interval->d . ' ' . _n('day', 'days', $interval->d, 'aesirx-analytics');
          }
          $timeLeft = implode(', ', $parts);
          return wp_kses(
              sprintf(
                  __("Your trial license ends in %1\$s. Please update your license <a href='%2\$s' target='_blank'>%2\$s</a>.", 'aesirx-analytics'),
                  $timeLeft,
                  'https://aesirx.io/licenses'
              ),
              aesirx_analytics_escape_html()
          );
        }
      } else {
        if(json_decode($body)->result->date_expired) {
          return wp_kses(sprintf(__("Your free trials has ended. Please update your license. <a href='%1\$s' target='_blank'>%1\$s</a>.", 'aesirx-analytics'), 'https://aesirx.io/licenses'), aesirx_analytics_escape_html());
        } else {
          return aesirx_analytics_trigger_trial();
        }
      }
    }
  }
}

function aesirx_analytics_get_api($url) {
  $response = wp_remote_get( $url );
  if ( is_wp_error( $response )) {
    add_settings_error(
      'aesirx_analytics_pro_plugin_options',
      'trial',
      esc_html__('Something went wrong. Please contact the administrator', 'aesirx-analytics'),
      'error'
    );
    return false;
  } else {
    return $response;
  }
}
