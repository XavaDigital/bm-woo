<?php
/**
 * Plugin Name: WooCommerce Booster Refund Filter
 * Plugin URI: https://yourwebsite.com
 * Description: Prevents refunded, cancelled, and failed orders from being included in Booster for WooCommerce bulk packing slip exports. Also adds quick status filters to the orders page.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: woo-booster-refund-filter
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class WooBoosterRefundFilter {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Check if WooCommerce is active
        if (!$this->is_woocommerce_active()) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        // Filter out refunded/cancelled/failed orders from bulk actions
        add_filter('handle_bulk_actions-edit-shop_order', array($this, 'filter_bulk_action_orders'), 5, 3);
        add_filter('handle_bulk_actions-woocommerce_page_wc-orders', array($this, 'filter_bulk_action_orders'), 5, 3);

        // Hook earlier in the process to intercept Booster actions
        add_action('admin_init', array($this, 'intercept_bulk_action'), 1);
        add_action('load-edit.php', array($this, 'intercept_bulk_action'), 1);

        // Add admin notice when orders are excluded
        add_action('admin_notices', array($this, 'show_excluded_orders_notice'));
        
        // Add quick status filter links to orders page
        add_filter('views_edit-shop_order', array($this, 'add_status_filter_links'));
        add_filter('views_woocommerce_page_wc-orders', array($this, 'add_status_filter_links'));
        
        // Apply status filter when selected
        add_filter('request', array($this, 'filter_orders_by_status'));
        add_filter('woocommerce_order_query_args', array($this, 'filter_hpos_orders_by_status'));
    }
    
    /**
     * Check if WooCommerce is active
     */
    private function is_woocommerce_active() {
        return class_exists('WooCommerce');
    }
    
    /**
     * Display admin notice if WooCommerce is not active
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php _e('WooCommerce Booster Refund Filter requires WooCommerce to be installed and active.', 'woo-booster-refund-filter'); ?></p>
        </div>
        <?php
    }
    
    /**
     * Intercept bulk actions early to filter order IDs
     * This runs before Booster processes the bulk action
     */
    public function intercept_bulk_action() {
        // Only run on the orders page
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && !in_array($screen->id, array('edit-shop_order', 'woocommerce_page_wc-orders'))) {
            return;
        }

        // Check if this is a bulk action request
        if (!isset($_REQUEST['action']) && !isset($_REQUEST['action2'])) {
            return;
        }

        // Get the action (can be from action or action2 dropdown)
        $action = '';
        if (isset($_REQUEST['action']) && $_REQUEST['action'] != '-1') {
            $action = sanitize_text_field($_REQUEST['action']);
        } elseif (isset($_REQUEST['action2']) && $_REQUEST['action2'] != '-1') {
            $action = sanitize_text_field($_REQUEST['action2']);
        }

        if (empty($action)) {
            return;
        }

        // Check if orders are selected
        if (!isset($_REQUEST['post']) || !is_array($_REQUEST['post'])) {
            return;
        }

        // Don't interfere with WooCommerce native actions
        $wc_native_actions = array(
            'mark_processing',
            'mark_on-hold',
            'mark_completed',
            'mark_cancelled',
            'trash',
            'untrash',
            'delete',
            'remove_personal_data'
        );

        if (in_array($action, $wc_native_actions)) {
            return;
        }

        error_log('WOO BOOSTER FILTER - Intercepting bulk action: ' . $action);

        // List of keywords that indicate a Booster PDF/packing slip action
        $booster_keywords = array(
            'pdf',
            'packing',
            'invoice',
            'wcj',
            'merge',
            'print',
            'document',
            'export_pdf',
            'bulk_print'
        );

        // Check if this is a Booster-related action
        $is_booster_action = false;
        foreach ($booster_keywords as $keyword) {
            if (stripos($action, $keyword) !== false) {
                $is_booster_action = true;
                error_log('WOO BOOSTER FILTER - Matched keyword: ' . $keyword);
                break;
            }
        }

        if (!$is_booster_action) {
            error_log('WOO BOOSTER FILTER - Not a Booster action, skipping');
            return;
        }

        // Filter the order IDs
        $post_ids = array_map('intval', $_REQUEST['post']);
        $excluded_statuses = array('refunded', 'cancelled', 'failed');
        $excluded_orders = array();
        $filtered_post_ids = array();

        foreach ($post_ids as $post_id) {
            $order = wc_get_order($post_id);

            if (!$order) {
                continue;
            }

            $order_status = $order->get_status();

            // Exclude refunded, cancelled, and failed orders
            if (in_array($order_status, $excluded_statuses)) {
                $excluded_orders[] = array(
                    'id' => $post_id,
                    'number' => $order->get_order_number(),
                    'status' => $order_status
                );
                error_log('WOO BOOSTER FILTER - Excluding order #' . $post_id . ' (Status: ' . $order_status . ')');
            } else {
                $filtered_post_ids[] = $post_id;
            }
        }

        error_log('WOO BOOSTER FILTER - Excluded ' . count($excluded_orders) . ' orders, keeping ' . count($filtered_post_ids) . ' orders');

        // Store excluded orders for admin notice
        if (!empty($excluded_orders)) {
            set_transient('woo_booster_excluded_orders', $excluded_orders, 60);
        }

        // Replace the order IDs in all relevant globals
        if (!empty($filtered_post_ids)) {
            $_REQUEST['post'] = $filtered_post_ids;
            $_GET['post'] = $filtered_post_ids;
            $_POST['post'] = $filtered_post_ids;
        } else {
            // No valid orders - set empty array
            $_REQUEST['post'] = array();
            $_GET['post'] = array();
            $_POST['post'] = array();
        }
    }

    /**
     * Filter orders before bulk actions are processed (fallback method)
     * This prevents refunded, cancelled, and failed orders from being included in Booster exports
     */
    public function filter_bulk_action_orders($redirect_to, $action, $post_ids) {
        // Log the action name for debugging
        error_log('WOO BOOSTER FILTER - Bulk action detected: ' . $action);

        // List of keywords that indicate a Booster PDF/packing slip action
        // Including common Booster action patterns
        $booster_keywords = array(
            'pdf',
            'packing',
            'invoice',
            'wcj',
            'merge',
            'print',
            'document',
            'export_pdf',
            'bulk_print'
        );

        // Check if this is a Booster-related action
        $is_booster_action = false;
        foreach ($booster_keywords as $keyword) {
            if (stripos($action, $keyword) !== false) {
                $is_booster_action = true;
                error_log('WOO BOOSTER FILTER - Matched keyword: ' . $keyword);
                break;
            }
        }

        // If not a Booster action, return early
        if (!$is_booster_action) {
            error_log('WOO BOOSTER FILTER - Not a Booster action, skipping filter');
            return $redirect_to;
        }

        error_log('WOO BOOSTER FILTER - Processing ' . count($post_ids) . ' orders');

        $excluded_statuses = array('refunded', 'cancelled', 'failed');
        $excluded_orders = array();
        $filtered_post_ids = array();

        foreach ($post_ids as $post_id) {
            $order = wc_get_order($post_id);

            if (!$order) {
                continue;
            }

            $order_status = $order->get_status();

            // Exclude refunded, cancelled, and failed orders
            if (in_array($order_status, $excluded_statuses)) {
                $excluded_orders[] = array(
                    'id' => $post_id,
                    'number' => $order->get_order_number(),
                    'status' => $order_status
                );
                error_log('WOO BOOSTER FILTER - Excluding order #' . $post_id . ' (Status: ' . $order_status . ')');
            } else {
                $filtered_post_ids[] = $post_id;
            }
        }

        error_log('WOO BOOSTER FILTER - Excluded ' . count($excluded_orders) . ' orders, keeping ' . count($filtered_post_ids) . ' orders');

        // Store excluded orders in transient for admin notice
        if (!empty($excluded_orders)) {
            set_transient('woo_booster_excluded_orders', $excluded_orders, 60);
        }

        // Replace the post_ids in the $_REQUEST global so Booster only processes valid orders
        if (!empty($filtered_post_ids)) {
            $_REQUEST['post'] = $filtered_post_ids;
            $_GET['post'] = $filtered_post_ids;
            $_POST['post'] = $filtered_post_ids;
        } else {
            // No valid orders to process
            $_REQUEST['post'] = array();
            $_GET['post'] = array();
            $_POST['post'] = array();
        }

        return $redirect_to;
    }
    
    /**
     * Show admin notice about excluded orders
     */
    public function show_excluded_orders_notice() {
        $excluded_orders = get_transient('woo_booster_excluded_orders');
        
        if (!$excluded_orders) {
            return;
        }
        
        delete_transient('woo_booster_excluded_orders');
        
        $count = count($excluded_orders);
        ?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <strong><?php printf(_n('%d order was excluded', '%d orders were excluded', $count, 'woo-booster-refund-filter'), $count); ?></strong>
                <?php _e('from the export because they are refunded, cancelled, or failed:', 'woo-booster-refund-filter'); ?>
            </p>
            <ul style="margin-left: 20px;">
                <?php foreach ($excluded_orders as $order_info) : ?>
                    <li>
                        <?php printf(
                            __('Order #%s (Status: %s)', 'woo-booster-refund-filter'),
                            $order_info['number'],
                            ucfirst($order_info['status'])
                        ); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
    }

    /**
     * Add quick status filter links
     */
    public function add_status_filter_links($views) {
        global $wpdb;

        // Get order counts for each status
        $processing_count = $this->get_order_count_by_status('processing');
        $on_hold_count = $this->get_order_count_by_status('on-hold');
        $pending_count = $this->get_order_count_by_status('pending');
        $completed_count = $this->get_order_count_by_status('completed');
        $cancelled_count = $this->get_order_count_by_status('cancelled');
        $refunded_count = $this->get_order_count_by_status('refunded');
        $failed_count = $this->get_order_count_by_status('failed');

        // Get current filter
        $current_status = isset($_GET['woo_quick_status']) ? sanitize_text_field($_GET['woo_quick_status']) : '';

        // Get base URL
        $base_url = admin_url('edit.php?post_type=shop_order');

        // Add custom quick filters
        $custom_views = array();

        // "Ready to Ship" filter (Processing orders only)
        if ($processing_count > 0) {
            $class = ($current_status === 'processing') ? 'current' : '';
            $custom_views['ready_to_ship'] = sprintf(
                '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
                esc_url(add_query_arg('woo_quick_status', 'processing', $base_url)),
                $class,
                __('📦 Ready to Ship', 'woo-booster-refund-filter'),
                $processing_count
            );
        }

        // "Needs Attention" filter (On-hold + Pending)
        $needs_attention_count = $on_hold_count + $pending_count;
        if ($needs_attention_count > 0) {
            $class = ($current_status === 'needs_attention') ? 'current' : '';
            $custom_views['needs_attention'] = sprintf(
                '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
                esc_url(add_query_arg('woo_quick_status', 'needs_attention', $base_url)),
                $class,
                __('⚠️ Needs Attention', 'woo-booster-refund-filter'),
                $needs_attention_count
            );
        }

        // "Problem Orders" filter (Cancelled + Refunded + Failed)
        $problem_count = $cancelled_count + $refunded_count + $failed_count;
        if ($problem_count > 0) {
            $class = ($current_status === 'problem_orders') ? 'current' : '';
            $custom_views['problem_orders'] = sprintf(
                '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
                esc_url(add_query_arg('woo_quick_status', 'problem_orders', $base_url)),
                $class,
                __('❌ Problem Orders', 'woo-booster-refund-filter'),
                $problem_count
            );
        }

        // Add a separator and merge with existing views
        if (!empty($custom_views)) {
            $views = array_merge($custom_views, array('separator' => '|'), $views);
        }

        return $views;
    }

    /**
     * Get order count by status
     */
    private function get_order_count_by_status($status) {
        // Remove 'wc-' prefix if present
        $status = str_replace('wc-', '', $status);

        $args = array(
            'status' => $status,
            'limit' => -1,
            'return' => 'ids',
        );

        $orders = wc_get_orders($args);
        return count($orders);
    }

    /**
     * Filter orders by quick status filter (classic editor)
     */
    public function filter_orders_by_status($vars) {
        global $typenow;

        // Only apply on shop_order post type
        if ('shop_order' !== $typenow) {
            return $vars;
        }

        if (!isset($_GET['woo_quick_status']) || empty($_GET['woo_quick_status'])) {
            return $vars;
        }

        $status_filter = sanitize_text_field($_GET['woo_quick_status']);

        switch ($status_filter) {
            case 'processing':
                $vars['post_status'] = 'wc-processing';
                break;

            case 'needs_attention':
                $vars['post_status'] = array('wc-on-hold', 'wc-pending');
                break;

            case 'problem_orders':
                $vars['post_status'] = array('wc-cancelled', 'wc-refunded', 'wc-failed');
                break;
        }

        return $vars;
    }

    /**
     * Filter orders by quick status filter (HPOS)
     */
    public function filter_hpos_orders_by_status($args) {
        if (!isset($_GET['woo_quick_status']) || empty($_GET['woo_quick_status'])) {
            return $args;
        }

        $status_filter = sanitize_text_field($_GET['woo_quick_status']);

        switch ($status_filter) {
            case 'processing':
                $args['status'] = 'processing';
                break;

            case 'needs_attention':
                $args['status'] = array('on-hold', 'pending');
                break;

            case 'problem_orders':
                $args['status'] = array('cancelled', 'refunded', 'failed');
                break;
        }

        return $args;
    }
}

// Initialize the plugin
function woo_booster_refund_filter_init() {
    new WooBoosterRefundFilter();
}
add_action('plugins_loaded', 'woo_booster_refund_filter_init');
