<?php
/*
 * Plugin Name: MK Fixture Generator
 * Plugin URI: https://ivytag.live
 * Description: Simple Sports/Match Fixture Generator based on SportsPress plugin.
 * Version: 1.0.1
 * Author: Mikael Kraft
 * Author URI: https://x.com/mikael_kraft
 * Requires at least: 6.0
 * Requires PHP: 7.2
 * Text Domain: mk-fixture-generator
 * Domain Path: /languages
 * Depends: sportspress
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Check if SportsPress (lite or Pro) is active
if (!function_exists('is_plugin_active')) {
    include_once ABSPATH . 'wp-admin/includes/plugin.php';
}

$is_sportspress_active = is_plugin_active('sportspress/sportspress.php');
$is_sportspress_pro_active = is_plugin_active('sportspress-pro/sportspress-pro.php');

if (!$is_sportspress_active && !$is_sportspress_pro_active) {
    add_action('admin_notices', function() {
        echo '<div class="error"><p><strong>MK Fixture Generator</strong> requires either SportsPress (lite) or SportsPress Pro to be installed and active. Please activate one of these plugins.</p></div>';
    });
    return;
}

// Optional: Encourage upgrading to Pro if only lite is active
if ($is_sportspress_active && !$is_sportspress_pro_active) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-info"><p><strong>MK Fixture Generator</strong> detected SportsPress (lite). Upgrade to <a href="https://www.themeboy.com/sportspress-pro/" target="_blank">SportsPress Pro</a> for enhanced features!</p></div>';
    });
}

// Include necessary files
require_once plugin_dir_path(__FILE__) . 'includes/admin-page.php';
require_once plugin_dir_path(__FILE__) . 'includes/fixture-generator.php';

// Register the admin menu
add_action('admin_menu', 'mkfg_register_admin_page');

function mkfg_register_admin_page() {
    add_submenu_page(
        'sportspress',              // Parent slug (SportsPress menu)
        'MK Fixture Generator',     // Page title
        'Fixture Generator',        // Menu title
        'manage_options',           // Capability
        'mkfg-fixture-generator',   // Menu slug
        'mkfg_admin_page_callback'  // Callback function
    );
}