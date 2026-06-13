<?php
/**
 * Plugin Name: WooCommerce Category Pre-Order & Back-Order Dates
 * Plugin URI: https://yourwebsite.com
 * Description: Set a back-order delivery label and an order-by/stock cutoff date on product categories. Shows configurable delivery messages on cards/product pages via shortcode, adds a required back-order confirmation at checkout, persists a note onto the order, and closes ordering (out of stock) once the cutoff passes.
 * Version: 1.0.0
 * Author: Xava Digital
 * Author URI: https://xavadigital.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wc-backorder-dates
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 9.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

define('WCBD_VERSION', '1.0.0');
define('WCBD_FILE', __FILE__);
define('WCBD_PATH', plugin_dir_path(__FILE__));
define('WCBD_URL', plugin_dir_url(__FILE__));

/**
 * Declare HPOS (custom order tables) compatibility.
 */
add_action('before_woocommerce_init', function () {
    if (class_exists('\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', WCBD_FILE, true);
    }
});

/**
 * Bootstrap the plugin once all plugins are loaded so we can check for WooCommerce.
 */
function wcbd_bootstrap() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p>'
                . esc_html__('WooCommerce Category Pre-Order & Back-Order Dates requires WooCommerce to be installed and active.', 'wc-backorder-dates')
                . '</p></div>';
        });
        return;
    }

    require_once WCBD_PATH . 'includes/class-resolver.php';
    require_once WCBD_PATH . 'includes/class-settings.php';
    require_once WCBD_PATH . 'includes/class-term-meta.php';
    require_once WCBD_PATH . 'includes/class-product-meta.php';
    require_once WCBD_PATH . 'includes/class-display.php';
    require_once WCBD_PATH . 'includes/class-cart.php';
    require_once WCBD_PATH . 'includes/class-checkout.php';
    require_once WCBD_PATH . 'includes/class-stock.php';
    require_once WCBD_PATH . 'includes/class-cron.php';
    require_once WCBD_PATH . 'includes/class-express.php';

    new WCBD_Settings();
    new WCBD_Term_Meta();
    new WCBD_Product_Meta();
    new WCBD_Display();
    new WCBD_Cart();
    new WCBD_Checkout();
    new WCBD_Stock();
    new WCBD_Cron();
    new WCBD_Express();

    add_action('wp_enqueue_scripts', 'wcbd_enqueue_assets');
}
add_action('plugins_loaded', 'wcbd_bootstrap');

/**
 * Front-end assets.
 */
function wcbd_enqueue_assets() {
    wp_enqueue_style('wcbd-frontend', WCBD_URL . 'assets/css/frontend.css', array(), WCBD_VERSION);

    // Registered here; enqueued by WCBD_Checkout when the checkout form actually renders
    // (more reliable than is_checkout() on Elementor-built checkout pages).
    wp_register_script('wcbd-checkout', WCBD_URL . 'assets/js/checkout.js', array('jquery'), WCBD_VERSION, true);
}

/**
 * Activation: schedule the hourly stock-sync cron.
 */
register_activation_hook(__FILE__, function () {
    if (!wp_next_scheduled('wcbd_sync_stock')) {
        wp_schedule_event(time(), 'hourly', 'wcbd_sync_stock');
    }
});

/**
 * Deactivation: clear the cron.
 */
register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('wcbd_sync_stock');
});
