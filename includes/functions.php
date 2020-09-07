<?php
/**
 * All of our utility functions that are used throughout the plugin.
 *
 * @package ForceRefresh
 */

// phpcs:disable WordPress.Security.NonceVerification

namespace JordanLeven\Plugins\ForceRefresh;

/**
 * Main function to generate the admin HTML.
 *
 * @return  void
 */
function force_refresh_specific_page_refresh_html() {
    define( 'HTML_CLASS_META_BOX', 'force-refresh-meta-box' );
    define( 'FILE_NAME_META_BOX_ADMIN', 'force-refresh-meta-box-admin-js' );
    // Include the admin JS.
    add_script( FILE_NAME_META_BOX_ADMIN, '/dist/js/force-refresh-meta-box-admin.js', true );
    // Get the current screen.
    $current_screen = get_current_screen();
    // Create the data we're going to localize to the script.
    $localized_data = array(
        // Wrap in inner array to preserve primitive types.
        'localData' => array(
            // Add the API URL for the script.
            'apiUrl'          => get_stylesheet_directory_uri(),
            // Add the API URL for the script.
            'siteId'          => get_current_blog_id(),
            // Create a nonce for the user.
            'nonce'           => wp_create_nonce( WP_FORCE_REFRESH_ACTION ),
            // Create a nonce for the user.
            'postId'          => get_the_ID(),
            'postType'        => $current_screen->post_type,
            'postName'        => get_the_title(),
            'targetClass'     => HTML_CLASS_META_BOX,
            // Add the refresh interval.
            'refreshInterval' => get_option(
                WP_FORCE_REFRESH_OPTION_REFRESH_INTERVAL_IN_SECONDS,
                WP_FORCE_REFRESH_OPTION_REFRESH_INTERVAL_IN_SECONDS_DEFAULT
            ),
        ),
    );
    // Localize the data.
    wp_localize_script( FILE_NAME_META_BOX_ADMIN, 'forceRefreshLocalJs', $localized_data );
    // Now that it's registered, enqueue the script.
    wp_enqueue_script( FILE_NAME_META_BOX_ADMIN );
    echo '<div class="' . esc_html( HTML_CLASS_META_BOX ) . '"></div>';
}

/**
 * Hook used to enqueue CSS and JS, as well as add the optional Force Refresh button to the
 * WordPress Admin Bar.
 *
 * @return void
 */
add_action(
    'admin_head',
    function () {
      // Get this option from the database. Default value is null/false.
        $show_force_refresh_in_wp_admin_bar = get_option(
            WP_FORCE_REFRESH_OPTION_SHOW_IN_WP_ADMIN_BAR
        ) === 'true';
        // If the option to show Force Refresh in the admin bar is enabled, then we need to show the
        // item in the WordPress Admin Bar.
      if ( $show_force_refresh_in_wp_admin_bar ) {
          // Show the Force Refresh option in the WordPress Admin Bar.
        add_action(
            'wp_before_admin_bar_render',
            __NAMESPACE__ . '\\show_force_refresh_in_wp_admin_bar',
            999
        );
      }
        // Since a Force Refresh can take place from any page, we also need to add the Handlebars
        // template for a notice.
        add_handlebars(
            WP_FORCE_REFRESH_HANDLEBARS_ADMIN_NOTICE_TEMPLATE_ID,
            'force-refresh-main-admin-notice.handlebars'
        );
        // Include the admin CSS.
        add_style( 'force-refresh-admin-css', '/dist/css/force-refresh-admin.css' );
        // Add the Force Refresh script.
        add_force_refresh_script();
    }
);

/**
 * Hook used to save admin actions for Force Refresh.
 */
add_action(
    'admin_init',
    function () {
      // If we are saving data from the Force Refresh options.
      if (
          isset( $_POST['force-refresh-admin-options-save'] ) &&
          'true' === $_POST['force-refresh-admin-options-save']
        ) {
        // Get updated options for viewing the refresh button in the WP Admin bar.
        $show_in_admin_bar = isset( $_POST['show-in-wp-admin-bar'] )
        ? sanitize_text_field(
            wp_unslash( $_POST['show-in-wp-admin-bar'] )
        ) : false;
          // Update the show in Admin Bar option.
          update_option( WP_FORCE_REFRESH_OPTION_SHOW_IN_WP_ADMIN_BAR, $show_in_admin_bar );
          // Get updated options for the refresh interval.
          $refresh_interval = isset( $_POST['refresh-interval'] )
            ? sanitize_text_field( wp_unslash( $_POST['refresh-interval'] ) ) : null;
          // If the new refresh interval option came through all right.
        if ( $refresh_interval ) {
            // Update the refresh interval.
            update_option( WP_FORCE_REFRESH_OPTION_REFRESH_INTERVAL_IN_SECONDS, $refresh_interval );
        }
      }
    }
);

/**
 * Function to show the Force Refresh option in the WP Admin bar.
 *
 * @return void
 */
function show_force_refresh_in_wp_admin_bar() {
  // Globalize the WP Admin Bar object.
  global $wp_admin_bar;

  // If the user isn't able to request a refresh, then stop eval.
  if ( ! user_can_request_force_refresh() ) {
    return;
  }

  // Add the item to show up in the WP Admin Bar.
  $args = array(
      'id'    => 'force-refresh',
      'title' =>
        '<i class="fa fa-refresh" aria-hidden="true"></i> <span>Force Refresh Site</span>',
      'href'  => null,
  );
  // Add the menu.
  $wp_admin_bar->add_menu( $args );
}

/**
 * Function to determine whether or not the currently logged-in user is able to request a refresh.
 *
 * @return  bool true if the use is able to request a refresh
 */
function user_can_request_force_refresh() {
  return current_user_can( WP_FORCE_REFRESH_CAPABILITY );
}

/**
 * Main function to manage settings for Force Refresh.
 *
 * @return void
 */
function manage_force_refresh() {
    // See what the existing settings are for showing Force Refresh in the WordPress Admin bar.
    $preset_option_show_force_refresh_in_wp_admin_bar = get_option(
        WP_FORCE_REFRESH_OPTION_SHOW_IN_WP_ADMIN_BAR,
        false,
    );
    // See what the existing settings are for the refresh interval.
    $preset_option_refresh_interval = get_option(
        WP_FORCE_REFRESH_OPTION_REFRESH_INTERVAL_IN_SECONDS,
        WP_FORCE_REFRESH_OPTION_REFRESH_INTERVAL_IN_SECONDS_DEFAULT
    );
    // Add the script.
    add_force_refresh_script();
    // Render the HTML.
    render_handlebars(
        'force-refresh-main-admin.handlebars',
        array(
            'site_name' => get_bloginfo(),
            'options'   => array(
                // For the Show Force Refresh in Admin Menu option.
                'preset_value_force_refresh_in_admin_bar_show' =>
                  'true' === $preset_option_show_force_refresh_in_wp_admin_bar ? 'selected' : null,
                'preset_value_force_refresh_in_admin_bar_hide' =>
                  'false' === $preset_option_show_force_refresh_in_wp_admin_bar ? 'selected' : null,

                // For the refresh interval option.
                'preset_value_force_refresh_refresh_interval_30' =>
                  '30' === $preset_option_refresh_interval ? 'selected' : null,
                'preset_value_force_refresh_refresh_interval_60' =>
                  '60' === $preset_option_refresh_interval ? 'selected' : null,
                'preset_value_force_refresh_refresh_interval_90' =>
                  '90' === $preset_option_refresh_interval ? 'selected' : null,
                'preset_value_force_refresh_refresh_interval_120' =>
                  '120' === $preset_option_refresh_interval ? 'selected' : null,
            ),
        )
    );
}

/**
 * Function to add the Force Refresh script, which contains the nonce required to initiate the call.
 *
 * @return void
 */
function add_force_refresh_script() {
    // Include the admin JS.
    add_script( 'force-refresh-main-admin-js', '/dist/js/force-refresh-main-admin.js', true );
    // Create the data we're going to localize to the script.
    $localized_data = array();
    // Add the API URL for the script.
    $localized_data['api_url'] = get_stylesheet_directory_uri();
    // Add the API URL for the script.
    $localized_data['site_id'] = get_current_blog_id();
    // Create a nonce for the user.
    $localized_data['nonce'] = wp_create_nonce( WP_FORCE_REFRESH_ACTION );
    // Add the ID of the handlebars notice.
    $localized_data['handlebars_admin_notice_template_id'] =
      WP_FORCE_REFRESH_HANDLEBARS_ADMIN_NOTICE_TEMPLATE_ID;
    // Add the refresh interval.
    $localized_data['refresh_interval'] = get_option(
        WP_FORCE_REFRESH_OPTION_REFRESH_INTERVAL_IN_SECONDS,
        WP_FORCE_REFRESH_OPTION_REFRESH_INTERVAL_IN_SECONDS_DEFAULT
    );
    // Localize the data.
    wp_localize_script( 'force-refresh-main-admin-js', 'force_refresh_local_js', $localized_data );
    // Now that it's registered, enqueue the script.
    wp_enqueue_script( 'force-refresh-main-admin-js' );
}

/**
 * Function for enqueueing styles for this plugin.
 *
 * @param string $handle The stylesheet handle.
 * @param string $path The path to the stylesheet (relative to the CSS dist directory).
 *
 * @return void
 */
function add_style( $handle, $path ) {
    // Get the file path.
    $file_path = get_force_refresh_plugin_directory() . $path;
    // If the file doesn't exist, throw an error.
  if ( ! file_exists( $file_path ) ) {
      echo '<div class="notice notice-error">';
      echo esc_html( "<p>${path} is missing.</p>" );
      echo '</div>';
  } else {
    // Get the file version.
    $file_version = filemtime( $file_path );
    // Enqueue the style.
    wp_enqueue_style( $handle, get_force_refresh_plugin_url( $path ), array(), $file_version );
  }
}

/**
 * Function for adding scripts for this plugin.
 *
 * @param string  $handle The script handle.
 *
 * @param string  $path The path to the script (relative to the JS dist directory).
 *
 * @param boolean $register Whether we should simply register the script instead of enqueing it.
 */
function add_script( $handle, $path, $register = false ) {
    // Get the file path.
    $file_path = get_force_refresh_plugin_directory() . $path;
    // If the file doesn't exist, throw an error.
  if ( ! file_exists( $file_path ) ) {
      echo '<div class="notice notice-error">';
      echo esc_html( "<p>${path} is missing.</p>" );
      echo '</div>';
  } else {
    // Get the file version.
    $file_version = filemtime( $file_path );
    // If we want to only register the script.
    if ( $register ) {
        wp_register_script(
            $handle,
            get_force_refresh_plugin_url( $path ),
            array( 'jquery', 'jquery-ui-core' ),
            $file_version,
            true,
        );
    } else {
        // Enqueue the style.
        wp_enqueue_script(
            $handle,
            get_force_refresh_plugin_url( $path ),
            array( 'jquery', 'jquery-ui-core' ),
            $file_version,
            true,
        );
    }
  }
}

// Include the handlebars function.
require_once __DIR__ . '/function-handlebars.php';
// All ajax calls from browsers.
require_once __DIR__ . '/ajax-calls-client.php';
// All ajax calls from admins.
require_once __DIR__ . '/ajax-calls-admin.php';
