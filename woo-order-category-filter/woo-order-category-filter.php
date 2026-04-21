<?php
/**
 * Plugin Name: WooCommerce Order Category Filter
 * Plugin URI: https://yourwebsite.com
 * Description: Filter WooCommerce orders by product category and date range to help identify orders for different club stores.
 * Version: 1.0.1
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: woo-order-category-filter
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class WooOrderCategoryFilter {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Check if WooCommerce is active
        if (!$this->is_woocommerce_active()) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        // Add filter dropdown to orders page
        add_action('restrict_manage_posts', array($this, 'add_category_filter_dropdown'), 20);

        // Add status filter dropdown
        add_action('restrict_manage_posts', array($this, 'add_status_filter_dropdown'), 20);

        // Add custom date range filter
        add_action('restrict_manage_posts', array($this, 'add_date_range_filter'), 20);
        
        // Filter orders based on category selection and date range
        add_filter('request', array($this, 'filter_orders_by_category'));
        add_filter('request', array($this, 'filter_orders_by_date_range'));
        
        // Add custom query var for category filter
        add_filter('query_vars', array($this, 'add_query_vars'));
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
            <p><?php _e('WooCommerce Order Category Filter requires WooCommerce to be installed and active.', 'woo-order-category-filter'); ?></p>
        </div>
        <?php
    }
    
    /**
     * Add category filter dropdown to orders page
     */
    public function add_category_filter_dropdown() {
        global $typenow;

        // Only add filter on shop_order post type (WooCommerce orders)
        if ('shop_order' === $typenow || (function_exists('wc_get_page_screen_id') && wc_get_page_screen_id('shop-order') === get_current_screen()->id)) {

            // Get all product categories
            $categories = get_terms(array(
                'taxonomy' => 'product_cat',
                'hide_empty' => true,
                'orderby' => 'name',
                'order' => 'ASC',
            ));

            if (!empty($categories) && !is_wp_error($categories)) {
                // Get selected categories (now supporting multiple)
                $selected_categories = isset($_GET['product_category_filter']) && is_array($_GET['product_category_filter'])
                    ? array_map('sanitize_text_field', $_GET['product_category_filter'])
                    : array();

                // Create a multi-select dropdown with custom styling
                ?>
                <div class="woo-category-filter-wrapper" style="display: inline-block; position: relative; vertical-align: middle;">
                    <button type="button" class="woo-category-filter-btn button" id="woo-category-filter-btn" style="height: 32px; line-height: 30px; padding: 0 24px 0 12px; position: relative;">
                        <span id="woo-category-filter-label">
                            <?php
                            if (empty($selected_categories)) {
                                _e('Filter by Category', 'woo-order-category-filter');
                            } else {
                                printf(_n('%d Category', '%d Categories', count($selected_categories), 'woo-order-category-filter'), count($selected_categories));
                            }
                            ?>
                        </span>
                        <span style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%);">▼</span>
                    </button>
                    <div class="woo-category-filter-dropdown" id="woo-category-filter-dropdown" style="display: none; position: absolute; top: 100%; left: 0; margin-top: 4px; background: #fff; border: 1px solid #8c8f94; border-radius: 3px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); z-index: 10000; min-width: 250px; max-height: 300px; overflow-y: auto;">
                        <div style="padding: 10px; border-bottom: 1px solid #ddd; background: #f6f7f7;">
                            <label style="margin: 0; font-weight: 600; cursor: pointer; display: block;">
                                <input type="checkbox" id="woo-select-all-cats" style="margin-right: 5px; vertical-align: middle;">
                                <?php _e('Select All', 'woo-order-category-filter'); ?>
                            </label>
                        </div>
                        <div style="padding: 5px 10px;">
                            <?php foreach ($categories as $category) : ?>
                                <label style="display: block; padding: 5px 0; margin: 0; cursor: pointer;">
                                    <input
                                        type="checkbox"
                                        name="product_category_filter[]"
                                        class="woo-cat-checkbox"
                                        value="<?php echo esc_attr($category->slug); ?>"
                                        <?php checked(in_array($category->slug, $selected_categories)); ?>
                                        style="margin-right: 5px; vertical-align: middle;"
                                    >
                                    <?php echo esc_html($category->name); ?> <span style="color: #666;">(<?php echo $category->count; ?>)</span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <script>
                jQuery(document).ready(function($) {
                    // Toggle dropdown
                    $('#woo-category-filter-btn').on('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        $('#woo-category-filter-dropdown').toggle();
                    });

                    // Close dropdown when clicking outside
                    $(document).on('click', function(e) {
                        if (!$(e.target).closest('.woo-category-filter-wrapper').length) {
                            $('#woo-category-filter-dropdown').hide();
                        }
                    });

                    // Select/deselect all
                    $('#woo-select-all-cats').on('change', function() {
                        $('.woo-cat-checkbox').prop('checked', $(this).prop('checked'));
                        updateCategoryLabel();
                    });

                    // Update "Select All" when individual checkboxes change
                    $('.woo-cat-checkbox').on('change', function() {
                        var allChecked = $('.woo-cat-checkbox:checked').length === $('.woo-cat-checkbox').length;
                        $('#woo-select-all-cats').prop('checked', allChecked);
                        updateCategoryLabel();
                    });

                    // Update label text
                    function updateCategoryLabel() {
                        var count = $('.woo-cat-checkbox:checked').length;
                        if (count === 0) {
                            $('#woo-category-filter-label').text('<?php _e('Filter by Category', 'woo-order-category-filter'); ?>');
                        } else {
                            $('#woo-category-filter-label').text(count + ' <?php echo (count($categories) > 1) ? 'Categories' : 'Category'; ?>');
                        }
                    }

                    // Initialize "Select All" state
                    var allChecked = $('.woo-cat-checkbox:checked').length === $('.woo-cat-checkbox').length && $('.woo-cat-checkbox').length > 0;
                    $('#woo-select-all-cats').prop('checked', allChecked);
                });
                </script>
                <?php
            }
        }
    }
    
    /**
     * Add custom date range filter to orders page
     */
    public function add_date_range_filter() {
        global $typenow;
        
        // Only add filter on shop_order post type (WooCommerce orders)
        if ('shop_order' === $typenow || (function_exists('wc_get_page_screen_id') && wc_get_page_screen_id('shop-order') === get_current_screen()->id)) {
            
            // Add inline styles (only once)
            static $styles_added = false;
            if (!$styles_added) {
                ?>
                <style>
                    .woo-order-date-filter {
                        height: 32px;
                        line-height: 2;
                        padding: 0 8px;
                        vertical-align: middle;
                        margin: 1px 8px 0 0;
                        border: 1px solid #8c8f94;
                        border-radius: 3px;
                        background-color: #fff;
                        color: #2c3338;
                        font-size: 14px;
                    }
                    .woo-order-date-filter:focus {
                        border-color: #2271b1;
                        outline: 2px solid transparent;
                        box-shadow: 0 0 0 1px #2271b1;
                    }
                    .woo-clear-filters-btn {
                        height: 32px;
                        line-height: 30px;
                        padding: 0 12px;
                        vertical-align: middle;
                        margin: 1px 0 0 4px;
                        font-size: 13px;
                    }
                    .woo-clear-filters-btn:hover {
                        background-color: #f6f7f7;
                        border-color: #2271b1;
                        color: #2271b1;
                    }
                    /* Fix checkbox styling */
                    .woo-category-filter-dropdown input[type="checkbox"] {
                        width: 16px;
                        height: 16px;
                        min-width: 16px;
                        min-height: 16px;
                        margin: 0 5px 0 0;
                        padding: 0;
                        vertical-align: middle;
                        border-radius: 2px;
                    }
                    .woo-category-filter-dropdown label {
                        cursor: pointer;
                        user-select: none;
                    }
                </style>
                <?php
                $styles_added = true;
            }

            $start_date = isset($_GET['order_date_start']) ? sanitize_text_field($_GET['order_date_start']) : '';
            $end_date = isset($_GET['order_date_end']) ? sanitize_text_field($_GET['order_date_end']) : '';

            ?>
            <input
                type="date"
                name="order_date_start"
                id="order_date_start"
                value="<?php echo esc_attr($start_date); ?>"
                placeholder="<?php _e('Start Date', 'woo-order-category-filter'); ?>"
                class="woo-order-date-filter"
            />
            <input
                type="date"
                name="order_date_end"
                id="order_date_end"
                value="<?php echo esc_attr($end_date); ?>"
                placeholder="<?php _e('End Date', 'woo-order-category-filter'); ?>"
                class="woo-order-date-filter"
            />
            <?php

            // Show clear filters button if any filter is active
            $has_filters = !empty($_GET['product_category_filter']) || !empty($start_date) || !empty($end_date) || !empty($_GET['woo_status_filter']);
            if ($has_filters) {
                $clear_url = remove_query_arg(array('product_category_filter', 'order_date_start', 'order_date_end', 'woo_status_filter'));
                ?>
                <a href="<?php echo esc_url($clear_url); ?>" class="button woo-clear-filters-btn">
                    <?php _e('Clear Filters', 'woo-order-category-filter'); ?>
                </a>
                <?php
            }
        }
    }

    /**
     * Add status filter dropdown
     */
    public function add_status_filter_dropdown() {
        global $typenow;

        if ('shop_order' === $typenow) {
            $selected = isset($_GET['woo_status_filter']) ? sanitize_text_field($_GET['woo_status_filter']) : '';

            ?>
            <select name="woo_status_filter" id="woo_status_filter" style="float: none;">
                <option value=""><?php _e('All Status Groups', 'woo-order-category-filter'); ?></option>
                <option value="ready_to_ship" <?php selected($selected, 'ready_to_ship'); ?>>
                    <?php _e('📦 Ready to Ship', 'woo-order-category-filter'); ?>
                </option>
                <option value="needs_attention" <?php selected($selected, 'needs_attention'); ?>>
                    <?php _e('⚠️ Needs Attention', 'woo-order-category-filter'); ?>
                </option>
                <option value="problem_orders" <?php selected($selected, 'problem_orders'); ?>>
                    <?php _e('❌ Problem Orders', 'woo-order-category-filter'); ?>
                </option>
            </select>
            <?php
        }
    }

    /**
     * Filter orders based on selected category (now supports multiple) and status
     */
    public function filter_orders_by_category($vars) {
        global $typenow;

        // Don't run on AJAX requests
        if (wp_doing_ajax()) {
            return $vars;
        }

        // Don't run in admin unless we're on the orders list page
        if (is_admin() && !isset($_GET['product_category_filter']) && !isset($_GET['woo_status_filter'])) {
            return $vars;
        }

        // Only filter on shop_order post type
        if ('shop_order' !== $typenow && (!isset($vars['post_type']) || 'shop_order' !== $vars['post_type'])) {
            return $vars;
        }

        // Apply category filter
        if (isset($_GET['product_category_filter']) && !empty($_GET['product_category_filter'])) {
            // Handle both single and multiple category selection
            $category_slugs = is_array($_GET['product_category_filter'])
                ? array_map('sanitize_text_field', $_GET['product_category_filter'])
                : array(sanitize_text_field($_GET['product_category_filter']));

            // Get all orders that contain products from ANY of the selected categories
            $order_ids = $this->get_orders_by_categories($category_slugs);

            if (!empty($order_ids)) {
                $vars['post__in'] = $order_ids;
            } else {
                // No orders found, return empty result
                $vars['post__in'] = array(0);
            }
        }

        // Apply status filter
        if (isset($_GET['woo_status_filter']) && !empty($_GET['woo_status_filter'])) {
            $status_filter = sanitize_text_field($_GET['woo_status_filter']);

            switch ($status_filter) {
                case 'ready_to_ship':
                    $vars['post_status'] = 'wc-processing';
                    break;

                case 'needs_attention':
                    $vars['post_status'] = array('wc-on-hold', 'wc-pending');
                    break;

                case 'problem_orders':
                    $vars['post_status'] = array('wc-cancelled', 'wc-refunded', 'wc-failed');
                    break;
            }
        }

        return $vars;
    }

    /**
     * Get order IDs that contain products from multiple categories
     */
    private function get_orders_by_categories($category_slugs) {
        global $wpdb;

        // Ensure we have an array
        if (!is_array($category_slugs)) {
            $category_slugs = array($category_slugs);
        }

        if (empty($category_slugs)) {
            return array();
        }

        // Get all category term IDs
        $category_term_ids = array();
        foreach ($category_slugs as $slug) {
            $category = get_term_by('slug', $slug, 'product_cat');
            if ($category && !is_wp_error($category)) {
                $category_term_ids[] = $category->term_id;
            }
        }

        if (empty($category_term_ids)) {
            return array();
        }

        // Get all product IDs in these categories (including child categories)
        $product_ids = get_posts(array(
            'post_type' => 'product',
            'numberposts' => -1,
            'post_status' => 'publish',
            'fields' => 'ids',
            'tax_query' => array(
                array(
                    'taxonomy' => 'product_cat',
                    'field' => 'term_id',
                    'terms' => $category_term_ids,
                    'operator' => 'IN', // Match ANY of the selected categories
                    'include_children' => true,
                ),
            ),
        ));

        if (empty($product_ids)) {
            return array();
        }

        // Query to find orders containing these products
        $order_ids = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT order_items.order_id
            FROM {$wpdb->prefix}woocommerce_order_items as order_items
            LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta as order_item_meta ON order_items.order_item_id = order_item_meta.order_item_id
            WHERE order_items.order_item_type = 'line_item'
            AND order_item_meta.meta_key = '_product_id'
            AND order_item_meta.meta_value IN (" . implode(',', array_fill(0, count($product_ids), '%d')) . ")
        ", $product_ids));

        return $order_ids;
    }

    /**
     * Filter orders by custom date range
     */
    public function filter_orders_by_date_range($vars) {
        global $typenow;

        // Only filter on shop_order post type
        if ('shop_order' === $typenow || (isset($vars['post_type']) && 'shop_order' === $vars['post_type'])) {

            $start_date = isset($_GET['order_date_start']) ? sanitize_text_field($_GET['order_date_start']) : '';
            $end_date = isset($_GET['order_date_end']) ? sanitize_text_field($_GET['order_date_end']) : '';

            // If we have at least one date, apply the filter
            if (!empty($start_date) || !empty($end_date)) {

                // Initialize date_query if not already set
                if (!isset($vars['date_query'])) {
                    $vars['date_query'] = array();
                }

                // Build the date query
                $date_query = array(
                    'relation' => 'AND',
                );

                if (!empty($start_date)) {
                    $date_query[] = array(
                        'after' => $start_date,
                        'inclusive' => true,
                    );
                }

                if (!empty($end_date)) {
                    $date_query[] = array(
                        'before' => $end_date . ' 23:59:59',
                        'inclusive' => true,
                    );
                }

                $vars['date_query'] = $date_query;
            }
        }

        return $vars;
    }

    /**
     * Add custom query vars
     */
    public function add_query_vars($vars) {
        $vars[] = 'product_category_filter';
        $vars[] = 'order_date_start';
        $vars[] = 'order_date_end';
        return $vars;
    }
}

// Initialize the plugin
function woo_order_category_filter_init() {
    new WooOrderCategoryFilter();
}
add_action('plugins_loaded', 'woo_order_category_filter_init');

