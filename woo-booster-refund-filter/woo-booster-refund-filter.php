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
     * Text used both as the visible marker and as an idempotency guard so we
     * never annotate the same item name twice within one request.
     */
    const REFUND_MARKER = 'REFUNDED';

    /**
     * Whether the current request is generating a Booster packing slip, in
     * which case per-item refunds should be flagged on the document.
     *
     * @var bool
     */
    private $generating_packing_slip = false;

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

        // Also flag refunds on single-order packing slips (the "Create/View
        // Packing Slip" button on the order screen), which does not go through
        // the bulk-action hooks above. init covers both admin and served PDFs.
        add_action('init', array($this, 'detect_packing_slip_request'), 1);

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

        // Flag per-item refunds on packing slips (but not on invoices/receipts).
        $this->maybe_enable_refund_annotation($action);

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

        // Flag per-item refunds on packing slips (but not on invoices/receipts).
        $this->maybe_enable_refund_annotation($action);

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
     * Enable per-item refund flagging for the current packing-slip request.
     *
     * Partially refunded orders keep a shippable status (processing/completed)
     * and therefore stay in the export, but Booster renders every original line
     * item at full quantity with no indication that some pieces were refunded.
     * When we detect a packing-slip action we hook the order items so each
     * refunded line is clearly marked on the printed slip. We deliberately skip
     * invoices/receipts/credit notes, where a "DO NOT PACK" note is misleading.
     *
     * @param string $action The bulk action being processed.
     */
    private function maybe_enable_refund_annotation($action) {
        if ($this->generating_packing_slip) {
            return;
        }

        $action = strtolower($action);

        // Monetary documents should not carry packing instructions.
        foreach (array('invoice', 'receipt', 'credit', 'proforma') as $keyword) {
            if (strpos($action, $keyword) !== false) {
                return;
            }
        }

        $this->enable_refund_annotation();
    }

    /**
     * Detect a single-order Booster packing-slip request (the "Create/View
     * Packing Slip" button on the order edit screen) and arm the annotation.
     *
     * This path bypasses the bulk-action hooks, so we sniff the request for a
     * packing-slip signal instead. We key off the word "packing" so we never
     * fire on invoices, receipts or ordinary page loads. Runs on init at
     * priority 1, before Booster serves/generates the document.
     */
    public function detect_packing_slip_request() {
        if ($this->generating_packing_slip || empty($_REQUEST)) {
            return;
        }

        // Flatten request keys and scalar values into one lowercase haystack.
        $parts = array();
        foreach ($_REQUEST as $key => $value) {
            $parts[] = $key;
            if (is_scalar($value)) {
                $parts[] = $value;
            }
        }
        $haystack = strtolower(implode(' ', $parts));

        // Only act on packing-slip documents; invoices/receipts are excluded.
        if (strpos($haystack, 'packing') === false) {
            return;
        }

        $this->enable_refund_annotation();
    }

    /**
     * Arm the per-item refund annotation for the current request.
     *
     * Uses a late filter priority so we annotate the final name/quantity after
     * other plugins have had their say.
     */
    private function enable_refund_annotation() {
        if ($this->generating_packing_slip) {
            return;
        }

        $this->generating_packing_slip = true;

        add_filter('woocommerce_order_get_items', array($this, 'annotate_refunded_items'), 999, 3);

        error_log('WOO BOOSTER FILTER - Refund annotation enabled for packing slip');
    }

    /**
     * Append a visible refund flag to each line item that has been (partially
     * or fully) refunded, so warehouse staff never pack refunded garments.
     *
     * Only mutates the in-memory name used for rendering; the order is never
     * saved during PDF generation, so nothing is persisted.
     *
     * @param array    $items The order line items, keyed by item ID.
     * @param WC_Order $order The order the items belong to.
     * @param array    $types The item types requested.
     * @return array
     */
    public function annotate_refunded_items($items, $order, $types) {
        if (!is_a($order, 'WC_Abstract_Order')) {
            return $items;
        }

        foreach ($items as $item) {
            // Only product line items carry a shippable quantity.
            if (!($item instanceof WC_Order_Item_Product)) {
                continue;
            }

            $name = $item->get_name();

            // Idempotency guard: get_items() may be called several times per
            // request, so never double-annotate a name we already flagged.
            if (strpos($name, self::REFUND_MARKER) !== false) {
                continue;
            }

            $ordered_qty = (int) $item->get_quantity();
            if ($ordered_qty <= 0) {
                continue;
            }

            // get_qty_refunded_for_item() returns 0 or a negative number.
            $refunded_qty = abs((int) $order->get_qty_refunded_for_item($item->get_id()));
            if ($refunded_qty <= 0) {
                continue;
            }

            if ($refunded_qty >= $ordered_qty) {
                $name .= ' — *** FULLY REFUNDED — DO NOT PACK ***';
                $pack_qty = 0;
            } else {
                $pack_qty = $ordered_qty - $refunded_qty;
                $name .= sprintf(
                    ' — *** %d REFUNDED — PACK ONLY %d ***',
                    $refunded_qty,
                    $pack_qty
                );
            }

            $item->set_name($name);

            // Show the net quantity to pack in the slip's quantity column so a
            // packer never over-picks. Display-only: the order is not saved
            // during PDF generation, so the real line item is untouched.
            $item->set_quantity($pack_qty);
        }

        return $items;
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
