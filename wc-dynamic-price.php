<?php
/**
 * Plugin Name: WooCommerce Dynamic Price Calculator
 * Description: Changes product price based on quantity and custom fields: "Minimum Price" and "Coefficient"
 * Version: 1.0.0
 * Author: Cascade AI
 * Text Domain: wc-dynamic-price
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * WC requires at least: 3.0
 * WC tested up to: 8.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

// Define plugin constants
define('WC_DYNAMIC_PRICE_VERSION', '1.0.0');
define('WC_DYNAMIC_PRICE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WC_DYNAMIC_PRICE_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include the main plugin class
require_once WC_DYNAMIC_PRICE_PLUGIN_DIR . 'includes/class-wc-dynamic-price.php';

// Initialize the plugin
function wc_dynamic_price_init() {
    $plugin = new WC_Dynamic_Price();
    $plugin->init();
}
add_action('plugins_loaded', 'wc_dynamic_price_init');

// Activation hook
register_activation_hook(__FILE__, 'wc_dynamic_price_activate');
function wc_dynamic_price_activate() {
    // Activation tasks if needed
    flush_rewrite_rules();
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'wc_dynamic_price_deactivate');
function wc_dynamic_price_deactivate() {
    // Deactivation tasks if needed
    flush_rewrite_rules();
}