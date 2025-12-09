<?php
/**
 * Plugin Name: WooCommerce Order Category Filter
 * Plugin URI: https://yourwebsite.com
 * Description: Filter WooCommerce orders by product category and date range to help identify orders for different club stores.
 * Version: 1.0.0
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

        // Add custom date range filter
        add_action('restrict_manage_posts', array($this, 'add_date_range_filter'), 20);

        // Filter orders based on category selection and date range
        add_filter('request', array($this, 'filter_orders_by_category'));
        add_filter('request', array($this, 'filter_orders_by_date_range'));

        // Add custom query var for category filter
        add_filter('query_vars', array($this, 'add_query_vars'));

        // Add admin styles
        add_action('admin_head', array($this, 'add_admin_styles'));
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
                $selected_category = isset($_GET['product_category_filter']) ? sanitize_text_field($_GET['product_category_filter']) : '';

                echo '<select name="product_category_filter" id="product_category_filter">';
                echo '<option value="">' . __('All Product Categories', 'woo-order-category-filter') . '</option>';

                foreach ($categories as $category) {
                    printf(
                        '<option value="%s"%s>%s (%d)</option>',
                        esc_attr($category->slug),
                        selected($selected_category, $category->slug, false),
                        esc_html($category->name),
                        $category->count
                    );
                }

                echo '</select>';
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
            $has_filters = !empty($_GET['product_category_filter']) || !empty($start_date) || !empty($end_date);
            if ($has_filters) {
                $clear_url = remove_query_arg(array('product_category_filter', 'order_date_start', 'order_date_end'));
                ?>
                <a href="<?php echo esc_url($clear_url); ?>" class="button woo-clear-filters-btn">
                    <?php _e('Clear Filters', 'woo-order-category-filter'); ?>
                </a>
                <?php
            }
            ?>
        }
    }

    /**
     * Add admin styles for date inputs
     */
    public function add_admin_styles() {
        global $typenow;

        if ('shop_order' === $typenow || (function_exists('wc_get_page_screen_id') && wc_get_page_screen_id('shop-order') === get_current_screen()->id)) {
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
            </style>
            <?php
        }
    }
    
    /**
     * Filter orders based on selected category
     */
    public function filter_orders_by_category($vars) {
        global $typenow;
        
        // Only filter on shop_order post type
        if (('shop_order' === $typenow || (isset($vars['post_type']) && 'shop_order' === $vars['post_type'])) 
            && isset($_GET['product_category_filter']) 
            && !empty($_GET['product_category_filter'])) {
            
            $category_slug = sanitize_text_field($_GET['product_category_filter']);
            
            // Get all orders that contain products from the selected category
            $order_ids = $this->get_orders_by_category($category_slug);
            
            if (!empty($order_ids)) {
                $vars['post__in'] = $order_ids;
            } else {
                // No orders found, return empty result
                $vars['post__in'] = array(0);
            }
        }
        
        return $vars;
    }
    
    /**
     * Get order IDs that contain products from a specific category
     */
    private function get_orders_by_category($category_slug) {
        global $wpdb;
        
        // Get the category term
        $category = get_term_by('slug', $category_slug, 'product_cat');
        
        if (!$category) {
            return array();
        }
        
        // Get all product IDs in this category (including child categories)
        $product_ids = get_posts(array(
            'post_type' => 'product',
            'numberposts' => -1,
            'post_status' => 'publish',
            'fields' => 'ids',
            'tax_query' => array(
                array(
                    'taxonomy' => 'product_cat',
                    'field' => 'term_id',
                    'terms' => $category->term_id,
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

